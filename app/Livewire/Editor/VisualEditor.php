<?php

namespace App\Livewire\Editor;

use App\Jobs\DeploySiteJob;
use App\Models\ContentRevision;
use App\Models\EditableRegion;
use App\Models\EditSession;
use App\Models\Page;
use App\Models\Site;
use App\Services\ContentPatcher;
use App\Services\EditSessionService;
use App\Services\GitConflictException;
use App\Services\GitSyncService;
use App\Services\ParserService;
use App\Services\RegionDetector;
use App\Services\SiteSupportService;
use App\Support\SiteAccess;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Livewire\Attributes\On;
use Livewire\Component;

class VisualEditor extends Component
{
    public string $siteId;

    public string $pageId;

    public ?string $editSessionId = null;

    public string $mode = 'visual'; // visual|code

    public ?string $selectedRegionId = null;

    public string $editContent = '';

    public string $commitMessage = '';

    public bool $showSaveModal = false;

    public bool $showScheduleModal = false;

    public bool $isSaving = false;

    public bool $deployAfterSave = true;

    public string $schedulePublishAt = '';

    public string $scheduleBranch = 'main';

    public bool $debugTelemetryEnabled = false;

    public array $debugTelemetry = [];

    /**
     * Visual undo/redo for inline edits is not persisted as a stack yet; the mockup shows
     * these controls, so we expose the affordance and keep the action non-destructive.
     */
    public bool $canUndo = false;

    public bool $canRedo = false;

    // Code editor state
    public string $codeContent = '';

    public string $codeFilePath = '';

    public string $codeLanguage = 'plaintext';

    public function mount(string $siteId, string $pageId): void
    {
        $this->siteId = $siteId;
        $this->pageId = $pageId;

        $site = $this->resolveSite();
        $page = $this->resolvePage();
        $profile = app(SiteSupportService::class)->editorProfile($site, $page);
        $session = app(EditSessionService::class)->startOrResume($site, $page, auth()->user());

        $this->mode = $profile['default_mode'];
        $this->editSessionId = $session->id;

        $this->codeFilePath = $page->file_path;
        $this->codeLanguage = $this->detectCodeLanguage($this->codeFilePath);
        $this->debugTelemetryEnabled = (bool) request()->boolean('debug');
        $this->resetDebugTelemetry();

        $this->loadCodeContent();
    }

    public function openScheduleModal(): void
    {
        $this->schedulePublishAt = now()->addDay()->format('Y-m-d\TH:i');
        $this->scheduleBranch = 'main';
        $this->showScheduleModal = true;
    }

    public function closeScheduleModal(): void
    {
        $this->showScheduleModal = false;
    }

    /**
     * Mockup maps "Schedule" to deferred publish; pages only have a boolean published flag today,
     * so we record intent in-session and avoid writing fake DB timestamps.
     */
    public function confirmSchedule(): void
    {
        $this->showScheduleModal = false;
        session()->flash(
            'info',
            'Scheduled publish is not stored on pages yet. Use Save draft + Publish when ready, or track schedule in your release process.'
        );
    }

    public function undo(): void
    {
        session()->flash('info', 'Undo is not wired to the inline edit stack yet. Re-select the layer or use Code mode.');
    }

    public function redo(): void
    {
        session()->flash('info', 'Redo is not wired to the inline edit stack yet.');
    }

    public function saveDraft(): void
    {
        if ($this->mode === 'visual') {
            if (! $this->selectedRegionId) {
                session()->flash('error', 'Select a layer in the canvas before saving a draft from visual mode.');

                return;
            }

            if (! $this->selectedRegionCanBeEdited()) {
                session()->flash('error', $this->visualSaveErrorMessage());

                return;
            }
        }

        $this->deployAfterSave = false;
        $this->commitMessage = $this->generateCommitMessage();
        $this->save();
    }

    public function publishPage(): void
    {
        try {
            $site = $this->resolveSite();
            $page = $this->resolvePage();
            $page->update(['is_published' => true]);
            DeploySiteJob::dispatch($site->fresh(), 'editor');
            session()->flash('success', 'Page marked published and deploy queued.');
            $this->dispatch('reload-iframe');
        } catch (\Throwable $e) {
            session()->flash('error', 'Publish failed: '.$e->getMessage());
        }
    }

    #[On('region-selected')]
    public function onRegionSelected(string $regionId): void
    {
        $region = $this->findRegion($regionId);

        if ($region) {
            $this->selectedRegionId = $regionId;
            $this->editContent = $region->current_content ?? '';
            $this->debugTelemetry['selected_region_id'] = $regionId;
            $this->debugTelemetry['selected_selector'] = $region->selector;
            $this->debugTelemetry['selected_region_type'] = $region->region_type;
            $this->debugTelemetry['selected_region_editable'] = app(ContentPatcher::class)->canVisuallyEditRegion($region);

            // Tell the iframe to highlight this element
            $this->dispatch(
                'highlight-region',
                selector: $region->selector,
                regionId: $region->id,
                content: $region->current_content ?? ''
            );
        }
    }

    #[On('inline-edit-saved')]
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
            $this->codeLanguage = $this->detectCodeLanguage($this->codeFilePath);
        }
    }

    public function updateEditContent(string $content): void
    {
        $this->editContent = $content;
    }

    public function saveNow(): void
    {
        if ($this->mode === 'visual') {
            if (! $this->selectedRegionId) {
                session()->flash('error', 'Select a highlighted element first, then edit it inline.');

                return;
            }

            if (! $this->selectedRegionCanBeEdited()) {
                session()->flash('error', $this->visualSaveErrorMessage());

                return;
            }
        }

        $this->commitMessage = $this->generateCommitMessage();
        $this->deployAfterSave = true;
        $this->save();
    }

    public function reparsePage(): void
    {
        try {
            $site = $this->resolveSite();
            $page = $this->resolvePage();

            app(ParserService::class)->parseSinglePage($site, $page->file_path);
            $this->debugTelemetry['last_action'] = 'reparse_page';
            $this->debugTelemetry['last_error'] = null;

            if ($this->selectedRegionId && ! $this->findRegion($this->selectedRegionId)) {
                $this->selectedRegionId = null;
                $this->editContent = '';
            }

            session()->flash('success', 'Page was re-parsed from source. Region mapping has been refreshed.');
            $this->dispatch('reload-iframe');
        } catch (\Throwable $e) {
            $this->debugTelemetry['last_error'] = $e->getMessage();
            session()->flash('error', 'Re-parse failed: '.$e->getMessage());
        }
    }

    public function openSaveModal(): void
    {
        $this->debugTelemetry['last_action'] = 'open_save_modal';

        if ($this->mode === 'visual') {
            if (! $this->selectedRegionId) {
                $this->debugTelemetry['last_error'] = 'No region selected';
                session()->flash('error', 'Select a highlighted element first, then edit its content.');

                return;
            }

            if (! $this->selectedRegionCanBeEdited()) {
                $this->debugTelemetry['last_error'] = 'Selected region is not visually editable';
                session()->flash('error', $this->visualSaveErrorMessage());

                return;
            }
        }

        $this->commitMessage = $this->generateCommitMessage();
        $this->deployAfterSave = true;
        $this->showSaveModal = true;
    }

    public function startFreshSession(): void
    {
        $site = $this->resolveSite();
        $page = $this->resolvePage();
        $sessions = app(EditSessionService::class);

        if ($current = $this->resolveEditSession()) {
            $sessions->close($current, [
                'restarted_at' => now()->toIso8601String(),
                'restarted_by' => auth()->id(),
            ]);
        }

        $session = $sessions->startOrResume($site, $page, auth()->user());
        $this->editSessionId = $session->id;

        session()->flash('success', 'Started a fresh edit session from the latest known page state.');
    }

    public function promoteSelectedRegion(): void
    {
        if (! $this->selectedRegionId) {
            session()->flash('error', 'Select a layer first so pixelkraft knows which region to promote.');

            return;
        }

        $region = $this->resolveRegion($this->selectedRegionId);
        $detector = app(RegionDetector::class);

        $markerId = $region->marker_id ?: $detector->generateMarkerId($region);
        $detector->confirmAsEditable($region, $markerId);

        $this->dispatch('region-updated', regionId: $region->id);
        $this->dispatch('reload-iframe');
        session()->flash('success', 'Region promoted to a managed editable layer. Future visual saves will prefer durable marker-based anchors.');
    }

    public function lockSelectedRegion(): void
    {
        if (! $this->selectedRegionId) {
            session()->flash('error', 'Select a layer first so pixelkraft knows which region to lock.');

            return;
        }

        $region = $this->resolveRegion($this->selectedRegionId);
        app(RegionDetector::class)->confirmAsStatic($region);

        $this->dispatch('region-updated', regionId: $region->id);
        $this->dispatch('reload-iframe');
        session()->flash('success', 'Region marked as static. It will stay preview-only until you re-enable it.');
    }

    public function save(): void
    {
        $this->isSaving = true;
        $this->debugTelemetry['last_action'] = 'save';
        $this->debugTelemetry['last_error'] = null;
        $this->debugTelemetry['save_started_at'] = now()->toIso8601String();
        $this->debugTelemetry['mode'] = $this->mode;
        $this->debugTelemetry['deploy_after_save'] = $this->deployAfterSave;
        $this->debugTelemetry['patch'] = [];
        $this->debugTelemetry['changed_files'] = [];
        $this->debugTelemetry['changed_file_count'] = 0;
        $this->debugTelemetry['commit_sha'] = null;
        $this->debugTelemetry['deploy_queued'] = null;
        $patcher = app(ContentPatcher::class);

        try {
            $site = $this->resolveSite();
            $page = $this->resolvePage();
            $git = app(GitSyncService::class);
            $editSession = $this->resolveEditSession();
            $this->debugTelemetry['site_id'] = $site->id;
            $this->debugTelemetry['page_id'] = $page->id;
            $this->debugTelemetry['page_file_path'] = $page->file_path;
            $this->debugTelemetry['edit_session_id'] = $editSession?->id;
            $this->debugTelemetry['working_branch'] = $editSession?->working_branch;

            $changedFiles = [];

            if ($this->mode === 'code') {
                $fullPath = $this->resolveCodePath($site);
                File::ensureDirectoryExists(dirname($fullPath));
                File::put($fullPath, $this->codeContent);
                $changedFiles[] = $this->codeFilePath;
                $this->debugTelemetry['code_file_path'] = $this->codeFilePath;
                $this->debugTelemetry['code_content_length'] = strlen($this->codeContent);
            } elseif ($this->selectedRegionId) {
                if (! $this->selectedRegionCanBeEdited()) {
                    throw new \RuntimeException($this->visualSaveErrorMessage());
                }

                // Save visual editor edit via ContentPatcher
                $region = $this->resolveRegion($this->selectedRegionId);

                // Create revision
                ContentRevision::create([
                    'region_id' => $region->id,
                    'user_id' => auth()->id(),
                    'content_before' => $region->current_content,
                    'content_after' => $this->editContent,
                    'created_at' => now(),
                ]);

                $changedFiles = $patcher->applyEdit($region, $this->editContent);
                $this->debugTelemetry['patch'] = $patcher->lastPatchTelemetry();
            }

            $this->debugTelemetry['changed_files'] = $changedFiles;
            $this->debugTelemetry['changed_file_count'] = count($changedFiles);

            if (! empty($changedFiles)) {
                $message = $this->commitMessage ?: $this->generateCommitMessage();
                $this->debugTelemetry['commit_message'] = $message;

                $sha = $git->commitAndPush($site, $changedFiles, $message, $editSession);
                $this->debugTelemetry['commit_sha'] = $sha;
                if ($editSession) {
                    $editSession->update([
                        'base_commit_sha' => $sha,
                        'metadata' => array_merge($editSession->metadata ?? [], [
                            'last_commit_sha' => $sha,
                            'last_saved_at' => now()->toIso8601String(),
                        ]),
                    ]);
                }

                $site->update(['last_synced_at' => now()]);
                app(ParserService::class)->parseSinglePage($site, $page->file_path);

                if ($this->deployAfterSave) {
                    DeploySiteJob::dispatch($site->fresh(), 'editor');
                    $this->debugTelemetry['deploy_queued'] = true;
                    $this->debugTelemetry['deploy_trigger'] = 'editor';
                } else {
                    $this->debugTelemetry['deploy_queued'] = false;
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
            } else {
                $this->debugTelemetry['last_save_success'] = false;
                $this->debugTelemetry['last_error'] = 'No source changes detected';
            }

            if ($this->debugTelemetry['last_save_success'] !== false) {
                $this->debugTelemetry['last_save_success'] = true;
            }
            $this->showSaveModal = false;

        } catch (GitConflictException $e) {
            if ($session = $this->resolveEditSession()) {
                app(EditSessionService::class)->markConflict($session, [
                    'error' => $e->getMessage(),
                    'at' => now()->toIso8601String(),
                ]);
            }
            $this->debugTelemetry['last_save_success'] = false;
            $this->debugTelemetry['last_error'] = $e->getMessage();
            $this->debugTelemetry['exception_class'] = $e::class;
            if (empty($this->debugTelemetry['patch'])) {
                $this->debugTelemetry['patch'] = $patcher->lastPatchTelemetry();
            }
            Log::error('Editor save conflict', ['error' => $e->getMessage()]);
            session()->flash('error', 'Save blocked by newer repo changes. Your edit session was marked as conflicted for developer review.');
        } catch (\Throwable $e) {
            $this->debugTelemetry['last_save_success'] = false;
            $this->debugTelemetry['last_error'] = $e->getMessage();
            $this->debugTelemetry['exception_class'] = $e::class;
            if (empty($this->debugTelemetry['patch'])) {
                $this->debugTelemetry['patch'] = $patcher->lastPatchTelemetry();
            }
            Log::error('Editor save failed', ['error' => $e->getMessage()]);
            session()->flash('error', 'Save failed: '.$e->getMessage());
        } finally {
            $this->debugTelemetry['save_finished_at'] = now()->toIso8601String();
            $this->isSaving = false;
        }
    }

    #[On('region-updated')]
    public function refreshEditor(): void
    {
        // Region classification changed in sibling panel; rerender counts/editability badges.
    }

    public function toggleDebugTelemetry(): void
    {
        $this->debugTelemetryEnabled = ! $this->debugTelemetryEnabled;
        $this->debugTelemetry['last_action'] = $this->debugTelemetryEnabled ? 'debug_enabled' : 'debug_disabled';
    }

    public function clearDebugTelemetry(): void
    {
        $this->resetDebugTelemetry();
    }

    public function render(): View
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
                    'content' => Str::limit(trim(strip_tags($region->current_content ?? '')), 120),
                    'raw_content' => Str::limit(trim((string) ($region->current_content ?? '')), 400),
                ];
            })
            ->values();
        $patchableRegionCount = $previewRegions->where('editable', true)->count();
        $selectedRegionEditable = $selectedRegion
            ? $patcher->canVisuallyEditRegion($selectedRegion)
            : false;
        $editorProfile = app(SiteSupportService::class)->editorProfile($site, $page);
        $editSession = $this->resolveEditSession();
        $recentGitOperations = $site->gitOperations()
            ->latest('started_at')
            ->limit(5)
            ->get();
        $currentRelease = $site->currentDeploymentRelease()->first();
        $recentRevisions = $selectedRegion
            ? $selectedRegion->revisions()->with('user')->latest('created_at')->limit(8)->get()
            : collect();
        $selectedRegionManagement = $selectedRegion
            ? $this->selectedRegionManagementState($selectedRegion)
            : null;
        $sitePages = $site->pages()
            ->orderByRaw('CASE WHEN url_path IS NULL OR url_path = "" THEN 1 ELSE 0 END')
            ->orderBy('url_path')
            ->limit(20)
            ->get(['id', 'title', 'url_path', 'is_published']);
        $seoIssues = $page->seoIssues()
            ->whereNull('resolved_at')
            ->orderByDesc('severity')
            ->limit(12)
            ->get(['id', 'severity', 'message']);
        $mediaSamples = $this->collectRepoMediaSamples($site);

        // Build the preview URL for the iframe
        $previewUrl = $this->buildPreviewUrl($site, $page);

        return view('livewire.editor.visual-editor', [
            'site' => $site,
            'page' => $page,
            'selectedRegion' => $selectedRegion,
            'previewUrl' => $previewUrl,
            'previewRegions' => $previewRegions,
            'previewRegionCount' => $previewRegions->count(),
            'patchableRegionCount' => $patchableRegionCount,
            'selectedRegionEditable' => $selectedRegionEditable,
            'editorProfile' => $editorProfile,
            'debugTelemetryEnabled' => $this->debugTelemetryEnabled,
            'debugTelemetry' => $this->debugTelemetry,
            'codeLanguage' => $this->codeLanguage,
            'editSession' => $editSession,
            'recentGitOperations' => $recentGitOperations,
            'currentRelease' => $currentRelease,
            'recentRevisions' => $recentRevisions,
            'selectedRegionManagement' => $selectedRegionManagement,
            'sitePages' => $sitePages,
            'seoIssues' => $seoIssues,
            'mediaSamples' => $mediaSamples,
        ]);
    }

    // ── Private ─────────────────────────────────

    private function loadCodeContent(): void
    {
        $site = $this->resolveSite();

        try {
            $fullPath = $this->resolveCodePath($site);
        } catch (\RuntimeException) {
            $this->codeContent = '';

            return;
        }

        $this->codeContent = File::exists($fullPath) ? File::get($fullPath) : '';
    }

    /**
     * Resolve and canonicalize the code editor file path, ensuring it remains
     * inside the site's repository directory. Throws if the resolved path escapes
     * the repo root (path traversal guard).
     *
     * @throws \RuntimeException
     */
    private function resolveCodePath(Site $site): string
    {
        $repoPath = rtrim((string) $site->repo_path, '/\\');
        $candidate = $repoPath.'/'.ltrim($this->codeFilePath, '/\\');

        // The file may not exist yet (new file in code mode) — we must validate
        // the directory instead; fall back to the repo root if dirname doesn't exist.
        $realRepo = realpath($repoPath);

        if ($realRepo === false) {
            throw new \RuntimeException('Repository path does not exist on disk.');
        }

        // Resolve symlinks and collapse .. segments in the candidate path.
        // If the file doesn't exist yet, canonicalize its parent directory.
        $realCandidate = realpath($candidate) ?: realpath(dirname($candidate));

        if ($realCandidate === false) {
            // Parent directory also doesn't exist — resolve up the tree.
            $realCandidate = $realRepo;
        }

        if (! str_starts_with($realCandidate, $realRepo.DIRECTORY_SEPARATOR)
            && $realCandidate !== $realRepo
        ) {
            throw new \RuntimeException(
                "Refusing to read/write outside of repository: {$this->codeFilePath}"
            );
        }

        return $candidate;
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
        return SiteAccess::findOrFail($this->siteId);
    }

    private function resolvePage(): Page
    {
        $site = $this->resolveSite();

        return Page::query()
            ->whereKey($this->pageId)
            ->where('site_id', $site->id)
            ->firstOrFail();
    }

    private function resolveRegion(string $regionId): EditableRegion
    {
        $site = $this->resolveSite();

        return EditableRegion::query()
            ->whereKey($regionId)
            ->whereHas('page', function ($query) use ($site) {
                $query->whereKey($this->pageId)
                    ->where('site_id', $site->id);
            })
            ->firstOrFail();
    }

    private function findRegion(string $regionId): ?EditableRegion
    {
        $site = $this->resolveSite();

        return EditableRegion::query()
            ->whereKey($regionId)
            ->whereHas('page', function ($query) use ($site) {
                $query->whereKey($this->pageId)
                    ->where('site_id', $site->id);
            })
            ->first();
    }

    private function resolveEditSession(): ?EditSession
    {
        if (! $this->editSessionId) {
            return null;
        }

        return EditSession::query()
            ->whereKey($this->editSessionId)
            ->where('site_id', $this->siteId)
            ->where('page_id', $this->pageId)
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

    /**
     * @return array<string, mixed>
     */
    private function selectedRegionManagementState(EditableRegion $region): array
    {
        return [
            'managed' => $region->isConfirmed() || $region->hasVerifiedAnchor(),
            'locked' => (bool) $region->is_static,
            'detection_method' => $region->detection_method,
            'marker_id' => $region->marker_id,
            'has_verified_anchor' => $region->hasVerifiedAnchor(),
            'verified_at' => $region->last_verified_at,
            'source_file' => data_get($region->source_location, 'file', $region->page?->file_path),
            'source_anchor_type' => data_get($region->source_anchor, 'verified_via'),
        ];
    }

    /**
     * Mockup "Media" tab: surface a short list of image-like paths from the cloned repo when present.
     * This stays read-only and avoids duplicating the full FileManager UI.
     *
     * @return list<array{path: string, label: string}>
     */
    private function collectRepoMediaSamples(Site $site, int $limit = 24): array
    {
        $root = $site->repo_path;
        if (! $root || ! File::isDirectory($root)) {
            return [];
        }

        $extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'ico'];
        $out = [];

        foreach (['public', 'static', 'assets', 'images', 'img', 'media'] as $sub) {
            $dir = "{$root}/{$sub}";
            if (! File::isDirectory($dir)) {
                continue;
            }

            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::SELF_FIRST
            );

            foreach ($iterator as $file) {
                if (! $file->isFile()) {
                    continue;
                }

                $ext = strtolower((string) pathinfo($file->getFilename(), PATHINFO_EXTENSION));
                if (! in_array($ext, $extensions, true)) {
                    continue;
                }

                $relative = Str::after($file->getPathname(), $root.'/');
                $out[] = [
                    'path' => $relative,
                    'label' => basename($relative),
                ];

                if (count($out) >= $limit) {
                    return $out;
                }
            }
        }

        return $out;
    }

    private function resetDebugTelemetry(): void
    {
        $this->debugTelemetry = [
            'last_action' => null,
            'last_save_success' => null,
            'last_error' => null,
            'selected_region_id' => null,
            'selected_selector' => null,
            'selected_region_type' => null,
            'selected_region_editable' => null,
            'mode' => $this->mode,
            'deploy_after_save' => $this->deployAfterSave,
            'changed_files' => [],
            'changed_file_count' => 0,
            'commit_message' => null,
            'commit_sha' => null,
            'deploy_queued' => null,
            'deploy_trigger' => null,
            'patch' => [],
            'save_started_at' => null,
            'save_finished_at' => null,
            'site_id' => $this->siteId,
            'page_id' => $this->pageId,
            'page_file_path' => $this->codeFilePath,
        ];
    }

    private function detectCodeLanguage(string $path): string
    {
        $normalized = strtolower(trim($path));

        if (str_ends_with($normalized, '.blade.php')) {
            return 'blade';
        }

        $ext = strtolower((string) pathinfo($normalized, PATHINFO_EXTENSION));

        return match ($ext) {
            'html', 'htm' => 'html',
            'js', 'mjs', 'cjs' => 'javascript',
            'ts', 'tsx' => 'typescript',
            'jsx' => 'jsx',
            'css', 'scss', 'sass', 'less' => 'css',
            'php' => 'php',
            'json' => 'json',
            'md', 'mdx', 'markdown' => 'markdown',
            'vue' => 'vue',
            'svelte' => 'svelte',
            'astro' => 'astro',
            default => 'plaintext',
        };
    }
}
