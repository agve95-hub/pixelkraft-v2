<?php

namespace App\Http\Controllers;

use App\Models\Page;
use App\Models\Site;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class EditorPreviewController extends Controller
{
    /**
     * Serve a page's HTML for the editor iframe preview.
     */
    public function show(Site $site, Page $page): Response
    {
        abort_unless($page->site_id === $site->id, 404);

        $previewFilePath = $this->resolvePreviewFilePath($site, $page);

        if (! $previewFilePath) {
            return response($this->renderUnavailablePreview($site, $page), 200, [
                'Content-Type' => 'text/html; charset=utf-8',
                'X-Frame-Options' => 'SAMEORIGIN',
            ]);
        }

        $html = File::get($previewFilePath);

        // Resolve relative asset paths to work from repo directory
        $html = $this->resolveAssetPaths($html, $site, $page, $previewFilePath);

        return response($html, 200, [
            'Content-Type' => 'text/html; charset=utf-8',
            'X-Frame-Options' => 'SAMEORIGIN',
        ]);
    }

    /**
     * Serve a static asset (CSS, JS, image) from the repo for the preview.
     */
    public function asset(Site $site, string $path): Response
    {
        $fullPath = "{$site->repo_path}/{$path}";

        if (! File::exists($fullPath) || ! $this->isAllowedAsset($path)) {
            abort(404);
        }

        $mimeType = $this->getMimeType($path);
        $content = File::get($fullPath);

        return response($content, 200, [
            'Content-Type' => $mimeType,
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    /**
     * Rewrite relative URLs in HTML so assets load from our preview server.
     */
    private function resolveAssetPaths(string $html, Site $site, Page $page, string $previewFilePath): string
    {
        $assetRoute = route('editor.asset', ['site' => $site->id, 'path' => '__pk_asset__']);
        $baseUrl = Str::beforeLast($assetRoute, '/__pk_asset__');

        // Determine the directory of the current file for relative resolution
        $repoPath = str_replace('\\', '/', $site->repo_path);
        $normalizedPreviewPath = str_replace('\\', '/', $previewFilePath);
        $repoRelativePath = ltrim(Str::after($normalizedPreviewPath, $repoPath), '/');
        $fileDir = dirname($repoRelativePath);

        if ($fileDir === '.') {
            $fileDir = '';
        }

        // Inject a <base> tag to resolve relative paths
        if (! preg_match('/<base\s/i', $html)) {
            $basePath = $fileDir ? "{$baseUrl}/{$fileDir}/" : "{$baseUrl}/";
            $baseTag = "<base href=\"{$basePath}\">";

            // Insert after <head> or at the start
            if (preg_match('/<head[^>]*>/i', $html, $match, PREG_OFFSET_CAPTURE)) {
                $pos = $match[0][1] + strlen($match[0][0]);
                $html = substr_replace($html, "\n{$baseTag}\n", $pos, 0);
            } else {
                $html = "{$baseTag}\n{$html}";
            }
        }

        return $this->rewriteRootRelativeAssetUrls($html, $baseUrl);
    }

    private function isAllowedAsset(string $path): bool
    {
        $allowedExtensions = [
            'css', 'js', 'mjs',
            'png', 'jpg', 'jpeg', 'gif', 'svg', 'webp', 'ico', 'avif',
            'woff', 'woff2', 'ttf', 'eot', 'otf',
            'json', 'xml', 'txt',
            'mp4', 'webm', 'ogg', 'mp3',
            'pdf',
        ];

        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        // Prevent path traversal
        if (str_contains($path, '..') || str_starts_with($path, '/')) {
            return false;
        }

        return in_array($ext, $allowedExtensions, true);
    }

    private function getMimeType(string $path): string
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return match ($ext) {
            'css'          => 'text/css',
            'js', 'mjs'    => 'application/javascript',
            'json'         => 'application/json',
            'svg'          => 'image/svg+xml',
            'png'          => 'image/png',
            'jpg', 'jpeg'  => 'image/jpeg',
            'gif'          => 'image/gif',
            'webp'         => 'image/webp',
            'avif'         => 'image/avif',
            'ico'          => 'image/x-icon',
            'woff'         => 'font/woff',
            'woff2'        => 'font/woff2',
            'ttf'          => 'font/ttf',
            'xml'          => 'application/xml',
            default        => 'application/octet-stream',
        };
    }

    private function resolvePreviewFilePath(Site $site, Page $page): ?string
    {
        $sourceFilePath = "{$site->repo_path}/{$page->file_path}";
        if ($this->isHtmlFile($sourceFilePath)) {
            return $sourceFilePath;
        }

        return $this->resolveBuiltHtmlPath($site, $page);
    }

    private function resolveBuiltHtmlPath(Site $site, Page $page): ?string
    {
        $urlPath = $page->url_path ?? '/';
        if (str_contains($urlPath, ':')) {
            return null;
        }

        $outputDirs = array_values(array_unique(array_filter([
            $site->build_output_dir,
            $site->project_type === 'nextjs' ? 'out' : null,
        ])));

        $relativePath = trim($urlPath, '/');
        $candidates = $relativePath === ''
            ? ['index.html']
            : ["{$relativePath}.html", "{$relativePath}/index.html"];

        foreach ($outputDirs as $outputDir) {
            foreach ($candidates as $candidate) {
                $fullPath = "{$site->repo_path}/{$outputDir}/{$candidate}";
                if ($this->isHtmlFile($fullPath)) {
                    return $fullPath;
                }
            }
        }

        return null;
    }

    private function isHtmlFile(string $path): bool
    {
        if (! File::exists($path)) {
            return false;
        }

        return in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), ['html', 'htm'], true);
    }

    private function rewriteRootRelativeAssetUrls(string $html, string $baseUrl): string
    {
        return preg_replace_callback(
            '/\b(src|href)=([\'"])(\/[^\'"]+)\2/i',
            function (array $matches) use ($baseUrl) {
                $path = $matches[3];

                if (! $this->shouldRewriteAssetPath($path)) {
                    return $matches[0];
                }

                return "{$matches[1]}={$matches[2]}{$baseUrl}/" . ltrim($path, '/') . $matches[2];
            },
            $html
        );
    }

    private function shouldRewriteAssetPath(string $path): bool
    {
        $assetPath = parse_url($path, PHP_URL_PATH) ?: $path;
        $extension = strtolower(pathinfo($assetPath, PATHINFO_EXTENSION));

        return str_starts_with($assetPath, '/_next/')
            || in_array($extension, ['css', 'js', 'mjs', 'png', 'jpg', 'jpeg', 'gif', 'svg', 'webp', 'ico', 'avif', 'woff', 'woff2'], true);
    }

    private function renderUnavailablePreview(Site $site, Page $page): string
    {
        $expectedOutput = $site->project_type === 'nextjs'
            ? ($site->build_output_dir && $site->build_output_dir !== '.next' ? $site->build_output_dir : 'out')
            : ($site->build_output_dir ?: 'the configured build output directory');

        $filePath = e($page->file_path);
        $projectType = e($site->project_type);
        $expectedOutput = e($expectedOutput);

        return <<<HTML
<html>
<body style="background:#09090b;color:#d4d4d8;font-family:system-ui;height:100vh;margin:0;display:flex;align-items:center;justify-content:center;padding:24px;">
    <div style="max-width:720px;background:#18181b;border:1px solid #27272a;border-radius:16px;padding:24px;line-height:1.6;">
        <h1 style="margin:0 0 12px;font-size:20px;color:#fafafa;">Preview unavailable</h1>
        <p style="margin:0 0 12px;">The editor cannot preview the source file <code style="color:#a78bfa;">{$filePath}</code> directly because this page belongs to a <code style="color:#a78bfa;">{$projectType}</code> project.</p>
        <p style="margin:0;">Build or export the site so the rendered HTML exists in <code style="color:#a78bfa;">{$expectedOutput}</code>, then reopen the page editor.</p>
    </div>
</body>
</html>
HTML;
    }
}
