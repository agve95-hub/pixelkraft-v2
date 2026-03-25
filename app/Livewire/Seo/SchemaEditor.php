<?php

namespace App\Livewire\Seo;

use App\Models\Page;
use App\Services\GitSyncService;
use Livewire\Component;

class SchemaEditor extends Component
{
    public string $pageId;
    public string $schemaJson = '';

    public function mount(): void
    {
        $page = Page::findOrFail($this->pageId);

        $this->schemaJson = $page->schema_json
            ? json_encode($page->schema_json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
            : '';
    }

    public function save(): void
    {
        $page = Page::findOrFail($this->pageId);

        if (empty(trim($this->schemaJson))) {
            $page->update(['schema_json' => null]);
            session()->flash('success', 'Schema markup removed.');
            return;
        }

        $decoded = json_decode($this->schemaJson, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->addError('schemaJson', 'Invalid JSON: ' . json_last_error_msg());
            return;
        }

        $page->update(['schema_json' => $decoded]);

        // Inject into source file
        $this->injectSchema($page, $this->schemaJson);

        session()->flash('success', 'Schema markup saved and pushed.');
    }

    public function usePreset(string $preset): void
    {
        $page = Page::with('site')->findOrFail($this->pageId);
        $site = $page->site;
        $domain = $site->domain ?? 'example.com';

        $this->schemaJson = match ($preset) {
            'article' => json_encode([
                '@context' => 'https://schema.org',
                '@type'    => 'Article',
                'headline' => $page->title ?? '',
                'description' => $page->meta_description ?? '',
                'url' => "https://{$domain}" . ($page->url_path ?? '/'),
                'datePublished' => now()->format('Y-m-d'),
                'author' => ['@type' => 'Person', 'name' => ''],
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),

            'product' => json_encode([
                '@context' => 'https://schema.org',
                '@type'    => 'Product',
                'name'     => $page->title ?? '',
                'description' => $page->meta_description ?? '',
                'url' => "https://{$domain}" . ($page->url_path ?? '/'),
                'offers' => [
                    '@type'         => 'Offer',
                    'price'         => '0.00',
                    'priceCurrency' => 'USD',
                    'availability'  => 'https://schema.org/InStock',
                ],
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),

            'local_business' => json_encode([
                '@context' => 'https://schema.org',
                '@type'    => 'LocalBusiness',
                'name'     => $site->name,
                'url'      => "https://{$domain}",
                'address'  => [
                    '@type'           => 'PostalAddress',
                    'streetAddress'   => '',
                    'addressLocality' => '',
                    'addressCountry'  => '',
                ],
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),

            'faq' => json_encode([
                '@context' => 'https://schema.org',
                '@type'    => 'FAQPage',
                'mainEntity' => [
                    [
                        '@type' => 'Question',
                        'name'  => 'Question 1?',
                        'acceptedAnswer' => [
                            '@type' => 'Answer',
                            'text'  => 'Answer 1.',
                        ],
                    ],
                ],
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),

            default => $this->schemaJson,
        };
    }

    public function render()
    {
        return view('livewire.seo.schema-editor');
    }

    private function injectSchema(Page $page, string $json): void
    {
        $site = $page->site;
        $git = app(GitSyncService::class);

        if (! $git->isCloned($site)) {
            return;
        }

        $fullPath = "{$site->repo_path}/{$page->file_path}";

        if (! file_exists($fullPath)) {
            return;
        }

        try {
            $html = file_get_contents($fullPath);
            $scriptTag = '<script type="application/ld+json">' . "\n" . $json . "\n" . '</script>';

            // Replace existing JSON-LD or insert before </head>
            if (preg_match('/<script\s+type=["\']application\/ld\+json["\'][^>]*>.*?<\/script>/si', $html)) {
                $html = preg_replace(
                    '/<script\s+type=["\']application\/ld\+json["\'][^>]*>.*?<\/script>/si',
                    $scriptTag,
                    $html,
                    1
                );
            } elseif (preg_match('/<\/head>/i', $html, $m, PREG_OFFSET_CAPTURE)) {
                $html = substr_replace($html, "    {$scriptTag}\n", $m[0][1], 0);
            }

            file_put_contents($fullPath, $html);
            $git->commitAndPush($site, [$page->file_path], "Update schema markup for {$page->url_path}");

        } catch (\Throwable $e) {
            session()->flash('error', 'Schema saved to DB but push failed: ' . $e->getMessage());
        }
    }
}
