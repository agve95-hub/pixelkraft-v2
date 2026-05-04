<?php

namespace App\Services\Import;

use App\Enums\DeployStatus;
use App\Models\Site;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ZipArchive;

class ZipImportService
{
    private const MAX_UNCOMPRESSED_BYTES = 500 * 1024 * 1024; // 500 MB

    private const ALLOWED_EXTENSIONS = [
        'html', 'htm', 'css', 'js', 'mjs', 'json', 'xml', 'txt', 'svg',
        'png', 'jpg', 'jpeg', 'gif', 'webp', 'avif', 'ico',
        'woff', 'woff2', 'ttf', 'otf', 'eot',
        'pdf', 'mp4', 'webm', 'mp3', 'ogg',
        'map', 'ts', 'tsx', 'jsx', 'vue', 'astro', 'md', 'mdx',
    ];

    /**
     * Extract a stored ZIP into the site's repo_path and trigger parsing.
     *
     * @param  string  $zipDiskPath  Storage disk path returned by file()->store()
     */
    public function import(Site $site, string $zipDiskPath): ImportResult
    {
        $zip = new ZipArchive;
        $zipRealPath = Storage::disk('local')->path($zipDiskPath);

        if (! file_exists($zipRealPath)) {
            throw new ImportException("ZIP file not found at: {$zipDiskPath}");
        }

        $code = $zip->open($zipRealPath);
        if ($code !== true) {
            throw new ImportException("Cannot open ZIP archive (error code {$code}).");
        }

        // Pre-scan: total size + path traversal guard
        $this->validateZipContents($zip);

        // Ensure a clean destination directory
        $repoPath = $site->repo_path;
        $this->prepareRepoDirectory($repoPath, $site);

        try {
            $extracted = $this->extractSafely($zip, $repoPath);
        } finally {
            $zip->close();
        }

        // Strip a single common top-level directory if all files share one (e.g. dist/index.html)
        $extracted = $this->flattenIfWrapped($repoPath, $extracted);

        // Detect project type from what we extracted
        $projectType = $this->detectProjectType($repoPath, $extracted);

        $site->update([
            'project_type' => $projectType,
            'source_type' => 'upload',
            'deploy_status' => DeployStatus::Idle,
        ]);

        // Clean up the uploaded ZIP from temporary storage
        Storage::disk('local')->delete($zipDiskPath);

        Log::info("ZIP import complete for [{$site->slug}]", [
            'file_count' => count($extracted),
            'project_type' => $projectType,
            'repo_path' => $repoPath,
        ]);

        return new ImportResult(
            fileCount: count($extracted),
            projectType: $projectType,
            files: $extracted,
        );
    }

    // ── Validation ────────────────────────────────

    private function validateZipContents(ZipArchive $zip): void
    {
        $totalUncompressed = 0;

        for ($i = 0; $i < $zip->count(); $i++) {
            $stat = $zip->statIndex($i);

            if ($stat === false) {
                continue;
            }

            $name = $stat['name'];

            // Reject entries with null bytes
            if (str_contains($name, "\0")) {
                throw new ImportException('ZIP contains a file with a null byte in its name.');
            }

            // Reject absolute paths and path traversal sequences
            if (
                str_starts_with($name, '/')
                || str_contains($name, '../')
                || str_contains($name, '/..')
            ) {
                throw new ImportException("ZIP contains a path-traversal entry: {$name}");
            }

            $totalUncompressed += $stat['size'];

            if ($totalUncompressed > self::MAX_UNCOMPRESSED_BYTES) {
                throw new ImportException(
                    'ZIP uncompressed size exceeds the 500 MB limit. '
                    .'Split your build output into smaller uploads.'
                );
            }
        }
    }

    // ── Extraction ────────────────────────────────

    /**
     * @return list<string> Relative paths of extracted files
     */
    private function extractSafely(ZipArchive $zip, string $destDir): array
    {
        $extracted = [];
        $destDir = rtrim(realpath($destDir) ?: $destDir, DIRECTORY_SEPARATOR);

        for ($i = 0; $i < $zip->count(); $i++) {
            $stat = $zip->statIndex($i);
            if ($stat === false) {
                continue;
            }

            $name = $stat['name'];

            // Skip directories and hidden system files
            if (str_ends_with($name, '/') || basename($name) === '.DS_Store' || basename($name) === 'Thumbs.db') {
                continue;
            }

            // Extension whitelist check
            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            if ($ext !== '' && ! in_array($ext, self::ALLOWED_EXTENSIONS, true)) {
                Log::debug("ZIP import: skipping disallowed extension [{$ext}] for [{$name}]");

                continue;
            }

            $targetPath = $destDir.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $name);

            // Resolve to canonical path and verify it stays inside dest
            $parentDir = dirname($targetPath);
            File::ensureDirectoryExists($parentDir);
            $realParent = realpath($parentDir);

            if ($realParent === false || ! str_starts_with($realParent, $destDir.DIRECTORY_SEPARATOR)) {
                Log::warning("ZIP import: skipping path-escape entry [{$name}]");

                continue;
            }

            $contents = $zip->getFromIndex($i);
            if ($contents === false) {
                Log::warning("ZIP import: could not read entry [{$name}]");

                continue;
            }

            File::put($targetPath, $contents);
            $extracted[] = $name;
        }

        return $extracted;
    }

    // ── Post-processing ───────────────────────────

    /**
     * If all extracted files share a single top-level directory (e.g. the ZIP was created
     * as "dist/" → "dist/index.html"), move them up one level so repo_path is the web root.
     *
     * @param  list<string>  $files
     * @return list<string> Updated relative paths after potential flatten
     */
    private function flattenIfWrapped(string $repoPath, array $files): array
    {
        if (empty($files)) {
            return $files;
        }

        $topDirs = collect($files)
            ->map(fn (string $f) => explode('/', $f)[0])
            ->unique()
            ->values();

        if ($topDirs->count() !== 1) {
            return $files; // multiple top-level entries — nothing to flatten
        }

        $wrapper = $topDirs->first();
        $wrapperPath = $repoPath.DIRECTORY_SEPARATOR.$wrapper;

        if (! is_dir($wrapperPath)) {
            return $files;
        }

        // Move all contents of wrapper/ up into repoPath
        foreach (File::allFiles($wrapperPath) as $file) {
            $relative = $file->getRelativePathname();
            $dest = $repoPath.DIRECTORY_SEPARATOR.$relative;
            File::ensureDirectoryExists(dirname($dest));
            File::move($file->getRealPath(), $dest);
        }

        File::deleteDirectory($wrapperPath);

        // Return paths without the wrapper prefix
        return collect($files)
            ->map(fn (string $f) => ltrim(Str::replaceFirst($wrapper.'/', '', $f), '/'))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Prepare the repo directory: create it if it doesn't exist, or clear it if it does
     * (only if it's a platform-owned path to avoid wiping arbitrary directories).
     */
    private function prepareRepoDirectory(string $repoPath, Site $site): void
    {
        $reposBase = rtrim((string) config('pixelkraft.repos_path'), '/\\');

        // Only clear the directory if it's under the platform repos path
        if (File::isDirectory($repoPath)) {
            $realRepo = realpath($repoPath);
            $realBase = realpath($reposBase);

            if ($realRepo && $realBase && str_starts_with($realRepo, $realBase.DIRECTORY_SEPARATOR)) {
                File::cleanDirectory($repoPath);
            }
        } else {
            File::makeDirectory($repoPath, 0755, true);
        }
    }

    // ── Detection ─────────────────────────────────

    /**
     * Infer the project type from the extracted file tree.
     */
    private function detectProjectType(string $repoPath, array $files): string
    {
        $fileSet = array_flip(array_map(
            fn (string $f) => strtolower(basename($f)),
            $files
        ));

        if (isset($fileSet['package.json'])) {
            if (file_exists("{$repoPath}/next.config.js") || file_exists("{$repoPath}/next.config.ts")) {
                return 'nextjs';
            }
            if (file_exists("{$repoPath}/nuxt.config.ts") || file_exists("{$repoPath}/nuxt.config.js")) {
                return 'nuxt';
            }
            if (file_exists("{$repoPath}/astro.config.mjs") || file_exists("{$repoPath}/astro.config.ts")) {
                return 'astro';
            }
            if (file_exists("{$repoPath}/svelte.config.js")) {
                return 'svelte';
            }
            if (file_exists("{$repoPath}/vite.config.ts") || file_exists("{$repoPath}/vite.config.js")) {
                // Distinguish react vs vue by package.json contents
                $pkg = @json_decode((string) @file_get_contents("{$repoPath}/package.json"), true);
                $deps = array_keys(array_merge(
                    $pkg['dependencies'] ?? [],
                    $pkg['devDependencies'] ?? [],
                ));
                if (in_array('vue', $deps, true)) {
                    return 'vue';
                }
                if (in_array('react', $deps, true) || in_array('react-dom', $deps, true)) {
                    return 'react';
                }
            }
        }

        if (isset($fileSet['composer.json']) || isset($fileSet['index.php'])) {
            return 'php_site';
        }

        if (isset($fileSet['hugo.toml']) || isset($fileSet['config.toml'])) {
            return 'hugo';
        }

        $htmlFiles = collect($files)->filter(fn (string $f) => str_ends_with(strtolower($f), '.html'));
        if ($htmlFiles->isNotEmpty()) {
            return 'static_html';
        }

        return 'static_html'; // safe fallback
    }
}
