<?php

namespace App\Livewire\Editor;

use App\Models\ContentRevision;
use App\Models\EditableRegion;
use App\Models\Page;
use App\Models\Site;
use App\Services\ContentPatcher;
use App\Services\GitSyncService;
use Livewire\Component;
use Illuminate\Support\Facades\Log;

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

    // Code editor state
    public string $codeContent = '';
    public string $codeFilePath = '';

    protected $listeners = [
        'region-selected' => 'onRegionSelected',
        'region-updated'  => '$refresh',
        'iframe-element-clicked' => 'onIframeElementClicked',
        'inline-edit-saved' => 'onInlineEditSaved',
    ];

    public function mount(string $siteId, string $pageId): void
    {
        $this->siteId = $siteId;
        $this->pageId = $pageId;

        $page = $this->resolvePage();

        $this->codeFilePath = $page->file_path;

        if (! $this->supportsVisualEditing()) {
            $this->mode = 'code';
        }

        $this->loadCodeContent();
    }

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

    public function onIframeElementClicked(string $selector, string $content, string $tagName): void
    {
        if (! $this->supportsVisualEditing()) {
            return;
        }

        // Find or create a region for this element
        $page = $this->resolvePage();

        $region = $page->editableRegions()
            ->where('selector', $selector)
            ->first();

        if ($region) {
            $this->selectedRegionId = $region->id;
            $this->editContent = $region->current_content ?? $content;
        } else {
            $this->editContent = $content;
            $this->selectedRegionId = null;
        }
    }

    public function onInlineEditSaved(string $regionId, string $newContent): void
    {
        $this->selectedRegionId = $regionId;
        $this->editContent = $newContent;
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
        if ($this->mode === 'visual' && ! $this->supportsVisualEditing()) {
            session()->flash('error', 'Visual editing is preview-only for component-based pages. Switch to Code mode to edit safely.');
            return;
        }

        $this->commitMessage = $this->generateCommitMessage();
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

            if ($this->mode === 'visual' && ! $this->supportsVisualEditing()) {
                throw new \RuntimeException('Visual editing is preview-only for component-based pages. Use Code mode to make changes.');
            }

            if ($this->mode === 'code') {
                // Save code editor content directly to file
                $fullPath = "{$site->repo_path}/{$this->codeFilePath}";
                file_put_contents($fullPath, $this->codeContent);
                $changedFiles[] = $this->codeFilePath;
            } elseif ($this->selectedRegionId) {
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

                $sha = $git->commitAndPush($site, $changedFiles, $message);

                $site->update(['last_synced_at' => now()]);
                app(\App\Services\ParserService::class)->parseSinglePage($site, $page->file_path);

                // Refresh code content if in code mode
                if ($this->mode === 'code') {
                    $this->loadCodeContent();
                }

                session()->flash('success', 'Changes saved and pushed to GitHub.');

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

        // Build the preview URL for the iframe
        $previewUrl = $this->buildPreviewUrl($site, $page);

        return view('livewire.editor.visual-editor', [
            'site'           => $site,
            'page'           => $page,
            'selectedRegion' => $selectedRegion,
            'previewUrl'     => $previewUrl,
            'visualEditingEnabled' => $this->supportsVisualEditing($selectedRegion),
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

    private function supportsVisualEditing(?EditableRegion $region = null): bool
    {
        $site = $this->resolveSite();
        $page = $this->resolvePage();
        $extension = strtolower(pathinfo($page->file_path, PATHINFO_EXTENSION));
        $sourceLocation = $region?->source_location ?? [];

        if (in_array($site->project_type, ['nextjs', 'react', 'vue', 'svelte', 'nuxt'], true)
            && ! in_array($extension, ['html', 'htm'], true)) {
            return false;
        }

        return ($sourceLocation['source_type'] ?? null) !== 'component';
    }
}
