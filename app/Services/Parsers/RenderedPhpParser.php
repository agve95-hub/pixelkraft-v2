<?php

namespace App\Services\Parsers;

use App\Models\Site;
use Illuminate\Support\Facades\File;

class RenderedPhpParser implements ParserInterface
{
    public function __construct(
        private StaticHtmlParser $htmlParser,
    ) {}

    public function name(): string
    {
        return 'rendered_php';
    }

    public function discoverPages(string $repoPath, Site $site): array
    {
        $directories = [
            $repoPath.'/public',
            $repoPath.'/resources/views',
            $repoPath,
        ];

        $pages = [];

        foreach ($directories as $directory) {
            if (! File::isDirectory($directory)) {
                continue;
            }

            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            );

            foreach ($iterator as $file) {
                if (! $file->isFile()) {
                    continue;
                }

                $path = str_replace('\\', '/', $file->getPathname());
                $relativePath = str_replace(str_replace('\\', '/', $repoPath).'/', '', $path);
                $lower = strtolower($relativePath);

                if ($this->shouldSkip($lower)) {
                    continue;
                }

                if (str_ends_with($lower, '.blade.php') || str_ends_with($lower, '.php')) {
                    $pages[] = $relativePath;
                }
            }
        }

        $pages = array_values(array_unique($pages));
        sort($pages);

        return $pages;
    }

    public function parsePage(string $repoPath, string $filePath, Site $site): ?ParsedPage
    {
        $fullPath = "{$repoPath}/{$filePath}";

        if (! File::exists($fullPath)) {
            return null;
        }

        $source = File::get($fullPath);
        if (trim($source) === '') {
            return null;
        }

        $html = $this->normalizePhpTemplateToHtml($source);

        return $this->htmlParser->parseHtmlDocument(
            html: $html,
            filePath: $filePath,
            site: $site,
            urlPath: $this->filePathToUrlPath($filePath),
        );
    }

    private function normalizePhpTemplateToHtml(string $source): string
    {
        $normalized = preg_replace('/<\?(?:php|=)?[\s\S]*?\?>/i', ' ', $source) ?? $source;
        $normalized = preg_replace('/\{\{\s*[^}]+\s*\}\}/', ' content ', $normalized) ?? $normalized;
        $normalized = preg_replace('/\{!![\s\S]*?!!\}/', ' content ', $normalized) ?? $normalized;
        $normalized = preg_replace('/@\w+(?:\([^)]*\))?/', ' ', $normalized) ?? $normalized;
        $normalized = preg_replace('/{{--[\s\S]*?--}}/', ' ', $normalized) ?? $normalized;

        return $normalized;
    }

    private function filePathToUrlPath(string $filePath): string
    {
        $path = str_replace('\\', '/', $filePath);
        $path = preg_replace('#^resources/views/#', '', $path) ?? $path;
        $path = preg_replace('#^public/#', '', $path) ?? $path;
        $path = preg_replace('#\.blade\.php$#', '', $path) ?? $path;
        $path = preg_replace('#\.php$#', '', $path) ?? $path;
        $path = preg_replace('#/?index$#', '', $path) ?? $path;
        $path = '/'.ltrim($path, '/');

        return $path === '' ? '/' : $path;
    }

    private function shouldSkip(string $path): bool
    {
        return (bool) preg_match('#(^|/)(vendor|node_modules|storage|bootstrap/cache)/#', $path);
    }
}
