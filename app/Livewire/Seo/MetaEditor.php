<?php

namespace App\Livewire\Seo;

use App\Models\Page;
use App\Services\GitSyncService;
use App\Services\NextMetadataPatcher;
use App\Services\SeoAnalyzer;
use Livewire\Component;

class MetaEditor extends Component
{
    public string $pageId;

    public string $title = '';
    public string $metaDescription = '';
    public string $metaKeywords = '';
    public string $ogTitle = '';
    public string $ogDescription = '';
    public string $ogImage = '';
    public string $canonicalUrl = '';

    public array $analysis = [];

    public function mount(): void
    {
        $page = Page::findOrFail($this->pageId);

        $this->title = $page->title ?? '';
        $this->metaDescription = $page->meta_description ?? '';
        $this->metaKeywords = $page->meta_keywords ?? '';
        $this->ogTitle = $page->og_title ?? '';
        $this->ogDescription = $page->og_description ?? '';
        $this->ogImage = $page->og_image ?? '';
        $this->canonicalUrl = $page->canonical_url ?? '';

        $this->runAnalysis();
    }

    public function save(): void
    {
        $page = Page::findOrFail($this->pageId);

        $page->update([
            'title'            => $this->title ?: null,
            'meta_description' => $this->metaDescription ?: null,
            'meta_keywords'    => $this->metaKeywords ?: null,
            'og_title'         => $this->ogTitle ?: null,
            'og_description'   => $this->ogDescription ?: null,
            'og_image'         => $this->ogImage ?: null,
            'canonical_url'    => $this->canonicalUrl ?: null,
        ]);

        // Write meta tags back to source file
        $this->patchSourceFile($page);

        $this->runAnalysis();

        session()->flash('success', 'SEO meta tags updated.');
    }

    public function runAnalysis(): void
    {
        $page = Page::findOrFail($this->pageId);
        $analyzer = app(SeoAnalyzer::class);
        $this->analysis = $analyzer->analyze($page);
    }

    public function render()
    {
        $page = Page::findOrFail($this->pageId);

        return view('livewire.seo.meta-editor', [
            'page' => $page,
        ]);
    }

    private function patchSourceFile(Page $page): void
    {
        $site = $page->site;

        if (! app(GitSyncService::class)->isCloned($site)) {
            return;
        }

        $fullPath = "{$site->repo_path}/{$page->file_path}";

        if (! file_exists($fullPath)) {
            return;
        }

        try {
            $html = file_get_contents($fullPath);

            if ($this->isNextMetadataFile($page->file_path, $site->project_type) && app(NextMetadataPatcher::class)->canPatch($html)) {
                $html = app(NextMetadataPatcher::class)->patch($html, [
                    'title' => $this->title,
                    'description' => $this->metaDescription,
                    'keywords' => $this->metaKeywords,
                    'canonical' => $this->canonicalUrl,
                    'og_title' => $this->ogTitle,
                    'og_description' => $this->ogDescription,
                    'og_image' => $this->ogImage,
                ]);
            } else {
                $html = $this->upsertMetaTag($html, 'title', null, $this->title);
                $html = $this->upsertMetaTag($html, 'meta', 'description', $this->metaDescription);
                $html = $this->upsertMetaTag($html, 'meta', 'keywords', $this->metaKeywords);
                $html = $this->upsertMetaTag($html, 'og', 'og:title', $this->ogTitle);
                $html = $this->upsertMetaTag($html, 'og', 'og:description', $this->ogDescription);
                $html = $this->upsertMetaTag($html, 'og', 'og:image', $this->ogImage);
                $html = $this->upsertCanonical($html, $this->canonicalUrl);
            }

            file_put_contents($fullPath, $html);

            $git = app(GitSyncService::class);
            $git->commitAndPush($site, [$page->file_path], "Update SEO meta for {$page->url_path}");

        } catch (\Throwable $e) {
            session()->flash('error', 'Meta saved to DB but failed to update source: ' . $e->getMessage());
        }
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

    private function isNextMetadataFile(string $filePath, string $projectType): bool
    {
        if ($projectType !== 'nextjs') {
            return false;
        }

        return (bool) preg_match('/\.(tsx|jsx|ts|js)$/', $filePath);
    }
}
