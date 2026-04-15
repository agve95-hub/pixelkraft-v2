<?php

namespace App\Livewire\Seo;

use App\Models\Page;
use App\Services\GitSyncService;
use App\Services\SiteSupportService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Livewire\Component;

class SchemaEditor extends Component
{
    public string $pageId;

    public string $schemaJson = '';

    public bool $schemaEditingSupported = false;

    public string $schemaEditingNotice = '';

    private function pageOrFail(): Page
    {
        return Page::query()
            ->whereKey($this->pageId)
            ->whereHas('site', fn ($query) => $query->visibleTo(auth()->user()))
            ->firstOrFail();
    }

    public function mount(): void
    {
        $page = $this->pageOrFail();

        $this->schemaJson = $page->schema_json
            ? json_encode($page->schema_json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
            : '';
        $this->refreshSupportState($page);
    }

    public function save(): void
    {
        $page = $this->pageOrFail();
        $this->refreshSupportState($page);

        if (! $this->schemaEditingSupported) {
            session()->flash('error', $this->schemaEditingNotice);

            return;
        }

        if (empty(trim($this->schemaJson))) {
            try {
                $this->injectSchema($page, '');
                $page->update(['schema_json' => null]);
                session()->flash('success', 'Schema markup removed.');

                return;
            } catch (\Throwable $e) {
                Log::error('Schema removal failed', ['page_id' => $this->pageId, 'error' => $e->getMessage()]);
                session()->flash('error', 'Schema removal failed. Check application logs for details.');

                return;
            }
        }

        $decoded = json_decode($this->schemaJson, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->addError('schemaJson', 'Invalid JSON: '.json_last_error_msg());

            return;
        }

        try {
            $this->injectSchema($page, $this->schemaJson);
            $page->update(['schema_json' => $decoded]);
            session()->flash('success', 'Schema markup saved and pushed.');
        } catch (\Throwable $e) {
            Log::error('Schema save failed', ['page_id' => $this->pageId, 'error' => $e->getMessage()]);
            session()->flash('error', 'Schema save failed. Check application logs for details.');
        }
    }

    public function usePreset(string $preset): void
    {
        $page = $this->pageOrFail()->load('site');
        $site = $page->site;
        $domain = $site->domain ?? 'example.com';

        $this->schemaJson = match ($preset) {
            'article' => json_encode([
                '@context' => 'https://schema.org',
                '@type' => 'Article',
                'headline' => $page->title ?? '',
                'description' => $page->meta_description ?? '',
                'url' => "https://{$domain}".($page->url_path ?? '/'),
                'datePublished' => now()->format('Y-m-d'),
                'author' => ['@type' => 'Person', 'name' => ''],
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),

            'product' => json_encode([
                '@context' => 'https://schema.org',
                '@type' => 'Product',
                'name' => $page->title ?? '',
                'description' => $page->meta_description ?? '',
                'url' => "https://{$domain}".($page->url_path ?? '/'),
                'offers' => [
                    '@type' => 'Offer',
                    'price' => '0.00',
                    'priceCurrency' => 'USD',
                    'availability' => 'https://schema.org/InStock',
                ],
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),

            'local_business' => json_encode([
                '@context' => 'https://schema.org',
                '@type' => 'LocalBusiness',
                'name' => $site->name,
                'url' => "https://{$domain}",
                'address' => [
                    '@type' => 'PostalAddress',
                    'streetAddress' => '',
                    'addressLocality' => '',
                    'addressCountry' => '',
                ],
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),

            'faq' => json_encode([
                '@context' => 'https://schema.org',
                '@type' => 'FAQPage',
                'mainEntity' => [
                    [
                        '@type' => 'Question',
                        'name' => 'Question 1?',
                        'acceptedAnswer' => [
                            '@type' => 'Answer',
                            'text' => 'Answer 1.',
                        ],
                    ],
                ],
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),

            default => $this->schemaJson,
        };
    }

    public function render(): View
    {
        return view('livewire.seo.schema-editor', [
            'schemaEditingSupported' => $this->schemaEditingSupported,
            'schemaEditingNotice' => $this->schemaEditingNotice,
        ]);
    }

    private function injectSchema(Page $page, string $json): void
    {
        $site = $page->site;
        $git = app(GitSyncService::class);

        if (! $git->isCloned($site)) {
            throw new \RuntimeException('Repository not cloned yet.');
        }

        $fullPath = "{$site->repo_path}/{$page->file_path}";

        if (! File::exists($fullPath)) {
            throw new \RuntimeException('Source file not found.');
        }

        // Prevent path traversal via symlinks in user-pushed repos.
        $realFull = realpath($fullPath);
        $realRepo = realpath((string) $site->repo_path);
        if ($realFull === false || $realRepo === false || ! str_starts_with($realFull, $realRepo.DIRECTORY_SEPARATOR)) {
            throw new \RuntimeException('Refusing to write outside of repository.');
        }

        $html = File::get($fullPath);
        $pattern = '/<script\s+type=["\']application\/ld\+json["\'][^>]*>.*?<\/script>/si';

        if ($json === '') {
            $html = preg_replace($pattern, '', $html, 1) ?? $html;
        } else {
            $scriptTag = '<script type="application/ld+json">'."\n".$json."\n".'</script>';

            if (preg_match($pattern, $html)) {
                // Use preg_replace_callback so $scriptTag is never parsed for
                // backreferences ($1, \1, etc.) even if the JSON contains those sequences.
                $html = preg_replace_callback($pattern, fn () => $scriptTag, $html, 1) ?? $html;
            } elseif (preg_match('/<\/head>/i', $html, $m, PREG_OFFSET_CAPTURE)) {
                $html = substr_replace($html, "    {$scriptTag}\n", $m[0][1], 0);
            }
        }

        File::put($fullPath, $html);
        $git->commitAndPush($site, [$page->file_path], "Update schema markup for {$page->url_path}");
    }

    private function refreshSupportState(Page $page): void
    {
        $support = app(SiteSupportService::class);
        $profile = $support->editorProfile($page->site, $page);

        $this->schemaEditingSupported = $profile['schema_editing_supported'];
        $this->schemaEditingNotice = $profile['schema_notice'];
    }
}
