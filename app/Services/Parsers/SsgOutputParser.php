<?php

namespace App\Services\Parsers;

use App\Models\Site;
use Illuminate\Support\Facades\File;
use Symfony\Component\DomCrawler\Crawler;

class SsgOutputParser implements ParserInterface
{
    private StaticHtmlParser $htmlParser;

    public function __construct(StaticHtmlParser $htmlParser)
    {
        $this->htmlParser = $htmlParser;
    }

    public function name(): string
    {
        return 'ssg_output';
    }

    public function discoverPages(string $repoPath, Site $site): array
    {
        $outputDir = $site->build_output_dir ?? $this->guessOutputDir($repoPath, $site);

        if (! $outputDir) {
            return $this->htmlParser->discoverPages($repoPath, $site);
        }

        $outputPath = "{$repoPath}/{$outputDir}";

        if (! File::isDirectory($outputPath)) {
            return [];
        }

        $pages = [];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($outputPath, \FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'html' && $file->getExtension() !== 'htm') {
                continue;
            }

            // Store path relative to repo root (includes output dir prefix)
            $relativePath = str_replace($repoPath . '/', '', $file->getPathname());
            $pages[] = $relativePath;
        }

        sort($pages);

        return $pages;
    }

    public function parsePage(string $repoPath, string $filePath, Site $site): ?ParsedPage
    {
        // Parse the built HTML output (same as static)
        $parsed = $this->htmlParser->parsePage($repoPath, $filePath, $site);

        if (! $parsed) {
            return null;
        }

        // Try to map back to source files (markdown, template)
        $sourceMapping = $this->findSourceFile($repoPath, $filePath, $site);

        if ($sourceMapping) {
            // Update regions with source file info
            foreach ($parsed->regions as &$region) {
                $region['source_location'] = array_merge(
                    $region['source_location'] ?? [],
                    [
                        'source_file' => $sourceMapping['source_file'],
                        'source_type' => $sourceMapping['source_type'],
                    ]
                );
            }
        }

        return $parsed;
    }

    /**
     * Try to find the source file (markdown, template) that generated this built HTML.
     */
    private function findSourceFile(string $repoPath, string $builtFilePath, Site $site): ?array
    {
        $outputDir = $site->build_output_dir ?? '';
        $relativePath = $builtFilePath;

        // Strip output dir prefix to get the content path
        if ($outputDir) {
            $relativePath = preg_replace('#^' . preg_quote($outputDir, '#') . '/?#', '', $relativePath);
        }

        // Remove index.html suffix
        $contentPath = preg_replace('#/?index\.html?$#', '', $relativePath);

        $sourcePatterns = $this->getSourcePatterns($site->project_type);

        foreach ($sourcePatterns as $pattern) {
            $candidates = [
                "{$repoPath}/{$pattern['dir']}/{$contentPath}.{$pattern['ext']}",
                "{$repoPath}/{$pattern['dir']}/{$contentPath}/index.{$pattern['ext']}",
                "{$repoPath}/{$pattern['dir']}/{$contentPath}.html",
            ];

            foreach ($candidates as $candidate) {
                if (File::exists($candidate)) {
                    return [
                        'source_file' => str_replace($repoPath . '/', '', $candidate),
                        'source_type' => $pattern['type'],
                    ];
                }
            }
        }

        return null;
    }

    /**
     * Get source file search patterns per SSG type.
     */
    private function getSourcePatterns(string $projectType): array
    {
        return match ($projectType) {
            'hugo' => [
                ['dir' => 'content', 'ext' => 'md', 'type' => 'markdown'],
                ['dir' => 'content', 'ext' => 'html', 'type' => 'template'],
                ['dir' => 'layouts', 'ext' => 'html', 'type' => 'template'],
            ],
            'eleventy' => [
                ['dir' => 'src', 'ext' => 'md', 'type' => 'markdown'],
                ['dir' => 'src', 'ext' => 'njk', 'type' => 'template'],
                ['dir' => 'src', 'ext' => 'liquid', 'type' => 'template'],
                ['dir' => '.', 'ext' => 'md', 'type' => 'markdown'],
                ['dir' => '.', 'ext' => 'njk', 'type' => 'template'],
            ],
            'astro' => [
                ['dir' => 'src/pages', 'ext' => 'astro', 'type' => 'component'],
                ['dir' => 'src/pages', 'ext' => 'md', 'type' => 'markdown'],
                ['dir' => 'src/pages', 'ext' => 'mdx', 'type' => 'markdown'],
                ['dir' => 'src/content', 'ext' => 'md', 'type' => 'markdown'],
            ],
            default => [
                ['dir' => 'content', 'ext' => 'md', 'type' => 'markdown'],
                ['dir' => 'src', 'ext' => 'md', 'type' => 'markdown'],
            ],
        };
    }

    private function guessOutputDir(string $repoPath, Site $site): ?string
    {
        $candidates = match ($site->project_type) {
            'hugo'     => ['public'],
            'eleventy' => ['_site'],
            'astro'    => ['dist'],
            default    => ['dist', 'public', 'build', '_site'],
        };

        foreach ($candidates as $dir) {
            if (File::isDirectory("{$repoPath}/{$dir}")) {
                return $dir;
            }
        }

        return null;
    }
}
