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

        $page = Page::findOrFail($pageId);
        $site = Site::findOrFail($siteId);

        $this->codeFilePath = $page->file_path;
        $this->loadCodeContent();
    }

    public function onRegionSelected(string $regionId): void
    {
        $this->selectedRegionId = $regionId;
        $region = EditableRegion::find($regionId);

        if ($region) {
            $this->editContent = $region->current_content ?? '';

            // Tell the iframe to highlight this element
            $this->dispatch('highlight-region', selector: $region->selector);
        }
    }

    public function onIframeElementClicked(string $selector, string $content, string $tagName): void
    {
        // Find or create a region for this element
        $page = Page::findOrFail($this->pageId);

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
        $this->mode = $this->mode === 'visual' ? 'code' : 'visual';

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
        $this->commitMessage = $this->generateCommitMessage();
        $this->showSaveModal = true;
    }

    public function save(): void
    {
        $this->isSaving = true;

        try {
            $site = Site::findOrFail($this->siteId);
            $page = Page::findOrFail($this->pageId);
            $patcher = app(ContentPatcher::class);
            $git = app(GitSyncService::class);

            $changedFiles = [];

            if ($this->mode === 'code') {
                // Save code editor content directly to file
                $fullPath = "{$site->repo_path}/{$this->codeFilePath}";
                file_put_contents($fullPath, $this->codeContent);
                $changedFiles[] = $this->codeFilePath;
            } elseif ($this->selectedRegionId) {
                // Save visual editor edit via ContentPatcher
                $region = EditableRegion::findOrFail($this->selectedRegionId);

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
        $site = Site::findOrFail($this->siteId);
        $page = Page::with('editableRegions')->findOrFail($this->pageId);

        $selectedRegion = $this->selectedRegionId
            ? EditableRegion::find($this->selectedRegionId)
            : null;

        // Build the preview URL for the iframe
        $previewUrl = $this->buildPreviewUrl($site, $page);

        return view('livewire.editor.visual-editor', [
            'site'           => $site,
            'page'           => $page,
            'selectedRegion' => $selectedRegion,
            'previewUrl'     => $previewUrl,
        ]);
    }

    // ── Private ─────────────────────────────────

    private function loadCodeContent(): void
    {
        $site = Site::findOrFail($this->siteId);
        $fullPath = "{$site->repo_path}/{$this->codeFilePath}";

        $this->codeContent = file_exists($fullPath) ? file_get_contents($fullPath) : '';
    }

    private function buildPreviewUrl(Site $site, Page $page): string
    {
        // If site has a domain and is live, use that
        if ($site->domain && $site->deploy_status === 'live') {
            return "https://{$site->domain}" . ($page->url_path ?? '/');
        }

        // Otherwise serve the file locally via a preview route
        return route('editor.preview', [
            'site' => $site->id,
            'page' => $page->id,
        ]);
    }

    private function generateCommitMessage(): string
    {
        $page = Page::find($this->pageId);
        $pageName = $page?->title ?? $page?->url_path ?? 'page';

        if ($this->selectedRegionId) {
            $region = EditableRegion::find($this->selectedRegionId);
            $regionType = $region?->region_type ?? 'content';

            return "Update {$regionType} on {$pageName}";
        }

        return "Update {$pageName}";
    }
}
