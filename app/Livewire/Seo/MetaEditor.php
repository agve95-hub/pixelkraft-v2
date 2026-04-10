<?php

namespace App\Livewire\Seo;

use App\Models\Page;
use App\Services\GitSyncService;
use App\Services\NextMetadataPatcher;
use App\Services\SeoAnalyzer;
use App\Services\SiteSupportService;
use App\Support\SiteAccess;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\File;
use Livewire\Component;

class MetaEditor extends Component
{
    public string $pageId;
    public string $focusKeyword = '';

    public string $title = '';
    public string $metaDescription = '';
    public string $metaKeywords = '';
    public string $ogTitle = '';
    public string $ogDescription = '';
    public string $ogImage = '';
    public string $canonicalUrl = '';

    public array $analysis = [];
    public bool $metaEditingSupported = false;
    public string $metaEditingMode = 'unsupported';
    public string $metaEditingNotice = '';

    private ?string $resolvedSiteId = null;

    private function resolvePage(): Page
    {
        $query = Page::query()->with('site')->whereKey($this->pageId);

        if ($this->resolvedSiteId !== null) {
            $query->where('site_id', $this->resolvedSiteId);
        }

        return $query->firstOrFail();
    }

    public function mount(): void
    {
        $page = $this->resolvePage();
        $this->resolvedSiteId = SiteAccess::findOrFail($page->site_id)->id;

        $this->title = $page->title ?? '';
        $this->metaDescription = $page->meta_description ?? '';
        $this->metaKeywords = $page->meta_keywords ?? '';
        $this->ogTitle = $page->og_title ?? '';
        $this->ogDescription = $page->og_description ?? '';
        $this->ogImage = $page->og_image ?? '';
        $this->canonicalUrl = $page->canonical_url ?? '';
        $this->focusKeyword = collect(explode(',', (string) ($page->meta_keywords ?? '')))
            ->map(fn (string $value) => trim($value))
            ->filter()
            ->first() ?? '';
        $this->refreshSupportState($page);

        $this->runAnalysis();
    }

    public function save(): void
    {
        $page = $this->resolvePage();
        SiteAccess::findOrFail($page->site_id);
        $this->refreshSupportState($page);

        if (! $this->metaEditingSupported) {
            session()->flash('error', $this->metaEditingNotice);
            return;
        }

        $attributes = [
            'title'            => $this->title ?: null,
            'meta_description' => $this->metaDescription ?: null,
            'meta_keywords'    => $this->metaKeywords ?: null,
            'og_title'         => $this->ogTitle ?: null,
            'og_description'   => $this->ogDescription ?: null,
            'og_image'         => $this->ogImage ?: null,
            'canonical_url'    => $this->canonicalUrl ?: null,
        ];

        try {
            $this->patchSourceFile($page);
            $page->update($attributes);
        } catch (\Throwable $e) {
            session()->flash('error', 'SEO save failed: ' . $e->getMessage());
            return;
        }

        $this->runAnalysis();

        session()->flash('success', 'SEO meta tags updated.');
    }

    public function runAnalysis(): void
    {
        $page = $this->resolvePage();
        $analyzer = app(SeoAnalyzer::class);
        $this->analysis = $analyzer->analyze($page, $this->focusKeyword);
    }

    public function render(): View
    {
        $page = $this->resolvePage();

        return view('livewire.seo.meta-editor', [
            'page' => $page,
            'metaEditingSupported' => $this->metaEditingSupported,
            'metaEditingNotice' => $this->metaEditingNotice,
        ]);
    }

    private function patchSourceFile(Page $page): void
    {
        $site = $page->site;

        if (! app(GitSyncService::class)->isCloned($site)) {
            throw new \RuntimeException('Repository is not cloned yet.');
        }

        $fullPath = "{$site->repo_path}/{$page->file_path}";

        if (! File::exists($fullPath)) {
            throw new \RuntimeException('Source file not found.');
        }

        $html = File::get($fullPath);

        if ($this->metaEditingMode === 'next_metadata') {
            $html = app(NextMetadataPatcher::class)->patch($html, [
                'title' => $this->title,
                'description' => $this->metaDescription,
                'keywords' => $this->metaKeywords,
                'canonical' => $this->canonicalUrl,
                'og_title' => $this->ogTitle,
                'og_description' => $this->ogDescription,
                'og_image' => $this->ogImage,
            ]);
        } elseif ($this->metaEditingMode === 'html') {
            $html = $this->upsertMetaTag($html, 'title', null, $this->title);
            $html = $this->upsertMetaTag($html, 'meta', 'description', $this->metaDescription);
            $html = $this->upsertMetaTag($html, 'meta', 'keywords', $this->metaKeywords);
            $html = $this->upsertMetaTag($html, 'og', 'og:title', $this->ogTitle);
            $html = $this->upsertMetaTag($html, 'og', 'og:description', $this->ogDescription);
            $html = $this->upsertMetaTag($html, 'og', 'og:image', $this->ogImage);
            $html = $this->upsertCanonical($html, $this->canonicalUrl);
        } else {
            throw new \RuntimeException($this->metaEditingNotice);
        }

        File::put($fullPath, $html);

        $git = app(GitSyncService::class);
        $git->commitAndPush($site, [$page->file_path], "Update SEO meta for {$page->url_path}");
    }

    private function upsertMetaTag(string $html, string $type, ?string $name, string $value): string
    {
        if ($type === 'title') {
            if (preg_match('/<title[^>]*>.*?<\/title>/si', $html)) {
                return preg_replace('/<title[^>]*>.*?<\/title>/si', '<title>' . e($value) . '</title>', $html, 1);
            }
            if (preg_match('/<head[^>]*>/i', $html, $m, PREG_OFFSET_CAPTURE)) {
                $pos = $m[0][1] + strlen($m[0][0]);
                return substr_replace($html, "\n    <title>" . e($value) . "</title>", $pos, 0);
            }
            return $html;
        }

        if ($type === 'og') {
            $attr = 'property';
            $pattern = '/<meta\s+[^>]*' . $attr . '=["\']' . preg_quote($name, '/') . '["\'][^>]*>/i';
            $replacement = '<meta ' . $attr . '="' . $name . '" content="' . e($value) . '">';
        } else {
            $attr = 'name';
            $pattern = '/<meta\s+[^>]*' . $attr . '=["\']' . preg_quote($name, '/') . '["\'][^>]*>/i';
            $replacement = '<meta ' . $attr . '="' . $name . '" content="' . e($value) . '">';
        }

        if (empty($value)) {
            return $html;
        }

        if (preg_match($pattern, $html)) {
            return preg_replace($pattern, $replacement, $html, 1);
        }

        // Insert before </head>
        if (preg_match('/<\/head>/i', $html, $m, PREG_OFFSET_CAPTURE)) {
            return substr_replace($html, "    {$replacement}\n", $m[0][1], 0);
        }

        return $html;
    }

    private function upsertCanonical(string $html, string $url): string
    {
        if (empty($url)) {
            return $html;
        }

        $tag = '<link rel="canonical" href="' . e($url) . '">';

        if (preg_match('/<link[^>]*rel=["\']canonical["\'][^>]*>/i', $html)) {
            return preg_replace('/<link[^>]*rel=["\']canonical["\'][^>]*>/i', $tag, $html, 1);
        }

        if (preg_match('/<\/head>/i', $html, $m, PREG_OFFSET_CAPTURE)) {
            return substr_replace($html, "    {$tag}\n", $m[0][1], 0);
        }

        return $html;
    }

    private function refreshSupportState(Page $page): void
    {
        $support = app(SiteSupportService::class);
        $site = $page->site;

        $this->metaEditingMode = $support->metaEditingMode($site, $page);
        $this->metaEditingSupported = $this->metaEditingMode !== 'unsupported';
        $this->metaEditingNotice = $support->editorProfile($site, $page)['meta_notice'];
    }
}
