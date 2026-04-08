<?php

namespace App\Livewire\Editor;

use App\Jobs\DeploySiteJob;
use App\Models\ContentRevision;
use App\Models\EditableRegion;
use App\Models\Page;
use App\Models\Site;
use App\Services\ContentPatcher;
use App\Services\GitSyncService;
use App\Services\SiteSupportService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Livewire\Attributes\On;
use Livewire\Component;

class VisualEditor extends Component
{
    public string $siteId;
    public string $pageId;

    public string $mode = 'visual'; // visual|code
    public ?string $selectedRegionId = null;
    public string $editContent = '';
    public string $commitMessage = '';
    public bool $showSaveModal = false;
    public bool $isSaving = false;
    public bool $deployAfterSave = true;

    // Code editor state
    public string $codeContent = '';
    public string $codeFilePath = '';

    public function mount(string $siteId, string $pageId): void
    {
        $this->siteId = $siteId;
        $this->pageId = $pageId;

        $site = $this->resolveSite();
        $page = $this->resolvePage();
        $profile = app(SiteSupportService::class)->editorProfile($site, $page);

        $this->mode = $profile['default_mode'];

        $this->codeFilePath = $page->file_path;

        $this->loadCodeContent();
    }

    #[On('region-selected')]
    public function onRegionSelected(string $regionId): void
    {
        $region = $this->findRegion($regionId);

        if ($region) {
            $this->selectedRegionId = $regionId;
            $this->editContent = $region->current_content ?? '';

            // Tell the iframe to highlight this element
            $this->dispatch('highlight-region', selector: $region->selector);
        }
    }

    #[On('inline-edit-saved')]
    public function onInlineEditSaved(string $regionId, string $newContent): void
    {
        $this->selectedRegionId = $regionId;
        $this->editContent = $newContent;
    }

    #[On('region-updated')]
    public function onRegionUpdated(): void
    {
        // Trigger a re-render so editability counts/badges stay in sync.
    }

    public function toggleMode(): void
    {
        $this->setMode($this->mode === 'visual' ? 'code' : 'visual');
    }

    public function setMode(string $mode): void
    {
        if (! in_array($mode, ['visual', 'code'], true)) {
            return;
        }

        $this->mode = $mode;

        if ($this->mode === 'code') {
            $this->loadCodeContent();
        }
    }

    public function updateEditContent(string $content): void
    {
        $this->editContent = $content;
    }

    public function openSaveModal(): void
    {
        if ($this->mode === 'visual') {
            if (! $this->selectedRegionId) {
                session()->flash('error', 'Select a highlighted element first, then edit its content.');
                return;
            }

            if (! $this->selectedRegionCanBeEdited()) {
                session()->flash('error', $this->visualSaveErrorMessage());
                return;
            }
        }

        $this->commitMessage = $this->generateCommitMessage();
        $this->deployAfterSave = true;
        $this->showSaveModal = true;
    }

    public function save(): void
    {
        $this->isSaving = true;

        try {
            $site = $this->resolveSite();
            $page = $this->resolvePage();
            $patcher = app(ContentPatcher::class);
            $git = app(GitSyncService::class);

            $changedFiles = [];

            if ($this->mode === 'code') {
                // Save code editor content directly to file
                $fullPath = "{$site->repo_path}/{$this->codeFilePath}";
                file_put_contents($fullPath, $this->codeContent);
                $changedFiles[] = $this->codeFilePath;
            } elseif ($this->selectedRegionId) {
                if (! $this->selectedRegionCanBeEdited()) {
                    throw new \RuntimeException($this->visualSaveErrorMessage());
                }

                // Save visual editor edit via ContentPatcher
                $region = $this->resolveRegion($this->selectedRegionId);

                // Create revision
                ContentRevision::create([
                    'region_id'      => $region->id,
                    'user_id'        => auth()->id(),
                    'content_before' => $region->current_content,
                    'content_after'  => $this->editContent,
                    'created_at'     => now(),
                ]);

                $changedFiles = $patcher->applyEdit($region, $this->editContent);
            }

            if (! empty($changedFiles)) {
                $message = $this->commitMessage ?: $this->generateCommitMessage();

                $git->commitAndPush($site, $changedFiles, $message);

                $site->update(['last_synced_at' => now()]);
                app(\App\Services\ParserService::class)->parseSinglePage($site, $page->file_path);

                if ($this->deployAfterSave) {
                    DeploySiteJob::dispatch($site->fresh(), 'editor');
                }

                // Refresh code content if in code mode
                if ($this->mode === 'code') {
                    $this->loadCodeContent();
                }

                session()->flash(
                    'success',
                    $this->deployAfterSave
                        ? 'Changes saved, pushed to GitHub, and deploy queued.'
                        : 'Changes saved and pushed to GitHub.'
                );

                // Dispatch event to refresh iframe
                $this->dispatch('reload-iframe');
            }

            $this->showSaveModal = false;

        } catch (\Throwable $e) {
            Log::error("Editor save failed", ['error' => $e->getMessage()]);
            session()->flash('error', 'Save failed: ' . $e->getMessage());
        } finally {
            $this->isSaving = false;
        }
    }

    #[On('region-updated')]
    public function refreshEditor(): void
    {
        // Region classification changed in sibling panel; rerender counts/editability badges.
    }

    public function render()
    {
        $site = $this->resolveSite();
        $page = Page::with('editableRegions')
            ->whereKey($this->pageId)
            ->where('site_id', $site->id)
            ->firstOrFail();

        $selectedRegion = $this->selectedRegionId
            ? $this->findRegion($this->selectedRegionId)
            : null;
        $patcher = app(ContentPatcher::class);
        $previewRegions = $page->editableRegions
            ->map(function (EditableRegion $region) use ($patcher) {
                return [
                    'id' => $region->id,
                    'selector' => $region->selector,
                    'type' => $region->region_type,
                    'editable' => $patcher->canVisuallyEditRegion($region),
                    'content' => Str::limit(trim(strip_tags($region->current_content ?? '')), 80),
                ];
            })
            ->values();
        $patchableRegionCount = $previewRegions->where('editable', true)->count();
        $selectedRegionEditable = $selectedRegion
            ? $patcher->canVisuallyEditRegion($selectedRegion)
            : false;
        $editorProfile = app(SiteSupportService::class)->editorProfile($site, $page);

        // Build the preview URL for the iframe
        $previewUrl = $this->buildPreviewUrl($site, $page);

        return view('livewire.editor.visual-editor', [
            'site'           => $site,
            'page'           => $page,
            'selectedRegion' => $selectedRegion,
            'previewUrl'     => $previewUrl,
            'previewRegions' => $previewRegions,
            'previewRegionCount' => $previewRegions->count(),
            'patchableRegionCount' => $patchableRegionCount,
            'selectedRegionEditable' => $selectedRegionEditable,
            'editorProfile' => $editorProfile,
        ]);
    }

    // ── Private ─────────────────────────────────

    private function loadCodeContent(): void
    {
        $site = $this->resolveSite();
        $fullPath = "{$site->repo_path}/{$this->codeFilePath}";

        $this->codeContent = file_exists($fullPath) ? file_get_contents($fullPath) : '';
    }

    private function buildPreviewUrl(Site $site, Page $page): string
    {
        return route('editor.preview', [
            'site' => $site->id,
            'page' => $page->id,
        ]);
    }

    private function generateCommitMessage(): string
    {
        $page = $this->resolvePage();
        $pageName = $page?->title ?? $page?->url_path ?? 'page';

        if ($this->selectedRegionId) {
            $region = $this->findRegion($this->selectedRegionId);
            $regionType = $region?->region_type ?? 'content';

            return "Update {$regionType} on {$pageName}";
        }

        return "Update {$pageName}";
    }

    private function resolveSite(): Site
    {
        return Site::findOrFail($this->siteId);
    }

    private function resolvePage(): Page
    {
        return Page::query()
            ->whereKey($this->pageId)
            ->where('site_id', $this->siteId)
            ->firstOrFail();
    }

    private function resolveRegion(string $regionId): EditableRegion
    {
        return EditableRegion::query()
            ->whereKey($regionId)
            ->whereHas('page', function ($query) {
                $query->whereKey($this->pageId)
                    ->where('site_id', $this->siteId);
            })
            ->firstOrFail();
    }

    private function findRegion(string $regionId): ?EditableRegion
    {
        return EditableRegion::query()
            ->whereKey($regionId)
            ->whereHas('page', function ($query) {
                $query->whereKey($this->pageId)
                    ->where('site_id', $this->siteId);
            })
            ->first();
    }

    private function selectedRegionCanBeEdited(): bool
    {
        if (! $this->selectedRegionId) {
            return false;
        }

        $region = $this->findRegion($this->selectedRegionId);

        return $region ? app(ContentPatcher::class)->canVisuallyEditRegion($region) : false;
    }

    private function visualSaveErrorMessage(): string
    {
        $site = $this->resolveSite();
        $page = $this->resolvePage();

        if (! app(SiteSupportService::class)->supportsVisualEditing($site, $page)) {
            return 'Visual save is disabled for this page type. Use Code mode to edit the source safely.';
        }

        return 'This region is preview-only because pixelkraft could not map it back to a unique source edit safely. Use Code mode for this one.';
    }
}
