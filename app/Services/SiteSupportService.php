<?php

namespace App\Services;

use App\Models\Page;
use App\Models\Site;
use Illuminate\Support\Facades\File;

class SiteSupportService
{
    public function __construct(
        private SiteRuntimeService $runtime,
        private NextMetadataPatcher $nextMetadata,
    ) {}

    /**
     * @return array{
     *   deployment_mode: string,
     *   deployment_mode_source: string,
     *   editor_workflow: string,
     *   visual_editing_supported: bool,
     *   summary: string,
     *   detail: string
     * }
     */
    public function siteProfile(Site $site): array
    {
        $deploymentMode = $this->deploymentMode($site);
        $deploymentModeSource = $this->runtime->deploymentModeSource($site);
        $visualEditingSupported = $this->supportsVisualEditing($site);
        $editorWorkflow = $visualEditingSupported ? 'visual_html' : 'code_first';

        return [
            'deployment_mode' => $deploymentMode,
            'deployment_mode_source' => $deploymentModeSource,
            'editor_workflow' => $editorWorkflow,
            'visual_editing_supported' => $visualEditingSupported,
            'summary' => $visualEditingSupported
                ? 'This site type supports true visual editing when regions map back to markup or markdown source.'
                : 'This site type is preview-assisted and code-first. Visual save is intentionally disabled to avoid unsafe source edits.',
            'detail' => trim(
                ($deploymentModeSource === 'inferred'
                    ? 'Deployment mode is currently auto-inferred from the project type and build configuration. Save settings to pin it explicitly. '
                    : '')
                . ($deploymentMode === 'runtime'
                    ? 'Deployments start and health-check a local runtime process behind Nginx.'
                    : 'Deployments publish static build artifacts that Nginx serves directly.')
            ),
        ];
    }

    /**
     * @return array{
     *   default_mode: string,
     *   visual_editing_supported: bool,
     *   visual_notice: string,
     *   visual_hint: string,
     *   meta_editing_mode: string,
     *   meta_editing_supported: bool,
     *   meta_notice: string,
     *   schema_editing_supported: bool,
     *   schema_notice: string
     * }
     */
    public function editorProfile(Site $site, Page $page): array
    {
        $visualEditingSupported = $this->supportsVisualEditing($site, $page);
        $metaEditingMode = $this->metaEditingMode($site, $page);
        $schemaEditingSupported = $this->supportsSchemaEditing($site, $page);

        return [
            'default_mode' => $visualEditingSupported ? 'visual' : 'code',
            'visual_editing_supported' => $visualEditingSupported,
            'visual_notice' => $visualEditingSupported
                ? 'Click a highlighted region to edit it.'
                : 'Preview-assisted mode. Click a highlighted region to inspect it, then switch to Code mode to change the source safely.',
            'visual_hint' => $visualEditingSupported
                ? 'Green regions can be edited and pushed from Visual mode. Amber regions still need Code mode.'
                : 'This page is backed by component source, so visual save is intentionally disabled. The preview helps you locate content, not rewrite JSX automatically.',
            'meta_editing_mode' => $metaEditingMode,
            'meta_editing_supported' => $metaEditingMode !== 'unsupported',
            'meta_notice' => $this->metaEditingNotice($site, $page, $metaEditingMode),
            'schema_editing_supported' => $schemaEditingSupported,
            'schema_notice' => $schemaEditingSupported
                ? 'Structured data will be written back to the source HTML for this page.'
                : 'Structured data editing is disabled here because this page is source-managed by a framework component. Add schema in code instead.',
        ];
    }

    public function deploymentMode(Site $site): string
    {
        return $this->runtime->deploymentMode($site);
    }

    public function supportsVisualEditing(Site $site, ?Page $page = null): bool
    {
        if (in_array($site->project_type, ['static_html', 'php_site', 'hugo', 'eleventy'], true)) {
            return true;
        }

        if ($page && $this->isDirectVisualSourcePath($page->file_path)) {
            return true;
        }

        return false;
    }

    public function metaEditingMode(Site $site, Page $page): string
    {
        if ($this->isHtmlHeadEditablePath($page->file_path)) {
            return 'html';
        }

        if ($site->project_type === 'nextjs' && $this->isScriptLikePath($page->file_path)) {
            $fullPath = "{$site->repo_path}/{$page->file_path}";

            if (File::exists($fullPath) && $this->nextMetadata->canPatch(File::get($fullPath))) {
                return 'next_metadata';
            }
        }

        return 'unsupported';
    }

    public function supportsSchemaEditing(Site $site, Page $page): bool
    {
        return $this->isHtmlHeadEditablePath($page->file_path);
    }

    private function metaEditingNotice(Site $site, Page $page, string $mode): string
    {
        return match ($mode) {
            'html' => 'Pixelkraft can write SEO tags directly into this page source.',
            'next_metadata' => 'Pixelkraft can update this Next.js page because it exposes a literal `export const metadata` object.',
            default => $site->project_type === 'nextjs'
                ? 'SEO editing is disabled here because this page does not expose a patchable `export const metadata` object. Edit the owning page, layout, or shared metadata helper in Code mode.'
                : 'SEO editing is disabled here because this page is managed by framework source rather than direct HTML. Use Code mode for SEO changes.',
        };
    }

    private function isDirectVisualSourcePath(?string $path): bool
    {
        if (! $path) {
            return false;
        }

        $normalized = strtolower($path);

        if (str_ends_with($normalized, '.blade.php')) {
            return true;
        }

        return in_array(pathinfo($normalized, PATHINFO_EXTENSION), ['html', 'htm', 'md', 'markdown', 'njk', 'liquid', 'twig', 'php'], true);
    }

    private function isHtmlHeadEditablePath(?string $path): bool
    {
        if (! $path) {
            return false;
        }

        $normalized = strtolower($path);

        if (str_ends_with($normalized, '.blade.php')) {
            return true;
        }

        return in_array(pathinfo($normalized, PATHINFO_EXTENSION), ['html', 'htm', 'njk', 'liquid', 'twig', 'php'], true);
    }

    private function isScriptLikePath(?string $path): bool
    {
        if (! $path) {
            return false;
        }

        return in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), ['js', 'jsx', 'ts', 'tsx'], true);
    }
}
