<?php

namespace App\Http\Controllers;

use App\Models\Page;
use App\Models\Site;
use App\Services\PagePreviewService;
use App\Services\SiteRuntimeService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class EditorPreviewController extends Controller
{
    public function __construct(
        private PagePreviewService $previews,
        private SiteRuntimeService $runtime,
    ) {}

    /**
     * Serve a page's HTML for the editor iframe preview.
     */
    public function show(Site $site, Page $page): Response
    {
        abort_unless($page->site_id === $site->id, 404);

        try {
            $preview = $this->resolvePreviewSource($site, $page);

            if ($preview['mode'] === 'runtime') {
                return $this->renderRuntimePreview($site, $page);
            }

            if ($preview['mode'] !== 'file') {
                return $this->htmlResponse($this->renderUnavailablePreview($site, $page));
            }

            $html = File::get($preview['file_path']);
            $html = $this->resolveFileAssetPaths(
                $html,
                $site,
                $preview['root_prefix'],
                $preview['directory_prefix'],
            );

            return $this->htmlResponse($html);
        } catch (\Throwable $e) {
            return $this->htmlResponse($this->renderFailurePreview($site, $page, $e));
        }
    }

    /**
     * Serve a static asset (CSS, JS, image) from the repo for the preview.
     */
    public function asset(Request $request, Site $site, string $path): Response
    {
        try {
            if ($this->runtime->usesRuntimeServer($site) && $this->runtime->isReachable($site)) {
                return $this->proxyRuntimeAsset($request, $site, $path);
            }

            return $this->serveLocalAsset($site, $path);
        } catch (\Throwable) {
            abort(404);
        }
    }

    private function serveLocalAsset(Site $site, string $path): Response
    {
        $fullPath = "{$site->repo_path}/{$path}";

        if (! File::exists($fullPath) || ! $this->isAllowedLocalAsset($path)) {
            abort(404);
        }

        $mimeType = $this->getMimeType($path);
        $content = File::get($fullPath);

        return response($content, 200, [
            'Content-Type' => $mimeType,
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    private function proxyRuntimeAsset(Request $request, Site $site, string $path): Response
    {
        if (! $this->isAllowedRuntimePath($path)) {
            abort(404);
        }

        $response = $this->runtime->fetch($site, '/' . ltrim($path, '/'), $request->query());

        if (! $response) {
            abort(404);
        }

        $headers = [
            'Content-Type' => $response->header('Content-Type') ?: 'application/octet-stream',
        ];

        if ($cacheControl = $response->header('Cache-Control')) {
            $headers['Cache-Control'] = $cacheControl;
        }

        return response($response->body(), $response->status(), $headers);
    }

    /**
     * Rewrite relative URLs in HTML so assets load from our preview server.
     */
    private function resolveFileAssetPaths(
        string $html,
        Site $site,
        string $rootPrefix,
        string $directoryPrefix,
    ): string {
        $baseUrl = $this->assetBaseUrl($site);
        $basePath = $directoryPrefix ? "{$baseUrl}/{$directoryPrefix}/" : "{$baseUrl}/";

        return $this->injectBaseTag(
            $this->rewriteRootRelativeAssetUrls($html, $baseUrl, $rootPrefix),
            $basePath,
        );
    }

    private function renderRuntimePreview(Site $site, Page $page): Response
    {
        $path = parse_url($page->url_path ?? '/', PHP_URL_PATH) ?: '/';
        $response = $this->runtime->fetch($site, $path);

        if (! $response) {
            return $this->htmlResponse($this->renderUnavailablePreview($site, $page));
        }

        if ($response->status() >= 500) {
            throw new \RuntimeException("Runtime preview responded with HTTP {$response->status()}.");
        }

        $directoryPrefix = trim(dirname(trim($path, '/')), '.');
        if ($directoryPrefix === '.') {
            $directoryPrefix = '';
        }

        $html = $this->injectBaseTag(
            $this->rewriteRootRelativeAssetUrls($response->body(), $this->assetBaseUrl($site)),
            $directoryPrefix ? $this->assetBaseUrl($site) . '/' . $directoryPrefix . '/' : $this->assetBaseUrl($site) . '/',
        );

        return $this->htmlResponse($html);
    }

    private function isAllowedLocalAsset(string $path): bool
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

    private function isAllowedRuntimePath(string $path): bool
    {
        return ! str_contains($path, '..') && ! str_starts_with($path, '/');
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

    private function resolvePreviewSource(Site $site, Page $page): array
    {
        $sourceFilePath = "{$site->repo_path}/{$page->file_path}";
        if ($this->isHtmlFile($sourceFilePath)) {
            return array_merge(
                ['mode' => 'file'],
                $this->previews->contextForRepoRelativePath($site, $page->file_path),
            );
        }

        if ($builtPath = $this->previews->findBuiltHtmlPath($site, $page->url_path)) {
            return array_merge(
                ['mode' => 'file'],
                $this->previews->contextForRepoRelativePath($site, $builtPath),
            );
        }

        if ($this->runtime->usesRuntimeServer($site) && $this->runtime->isReachable($site, $page->url_path ?? '/')) {
            return ['mode' => 'runtime'];
        }

        return ['mode' => 'unavailable'];
    }

    private function isHtmlFile(string $path): bool
    {
        if (! File::exists($path)) {
            return false;
        }

        return in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), ['html', 'htm'], true);
    }

    private function rewriteRootRelativeAssetUrls(string $html, string $baseUrl, string $rootPrefix = ''): string
    {
        return preg_replace_callback(
            '/\b(src|href)=([\'"])(\/[^\'"]+)\2/i',
            function (array $matches) use ($baseUrl, $rootPrefix) {
                $path = $matches[3];

                if (! $this->shouldRewriteAssetPath($path)) {
                    return $matches[0];
                }

                $repoRelativePath = ltrim($path, '/');
                if ($rootPrefix !== '') {
                    $repoRelativePath = trim($rootPrefix, '/') . '/' . $repoRelativePath;
                }

                return "{$matches[1]}={$matches[2]}{$baseUrl}/{$repoRelativePath}{$matches[2]}";
            },
            $html
        );
    }

    private function shouldRewriteAssetPath(string $path): bool
    {
        $assetPath = parse_url($path, PHP_URL_PATH) ?: $path;
        $extension = strtolower(pathinfo($assetPath, PATHINFO_EXTENSION));

        return str_starts_with($assetPath, '/_next/')
            || str_starts_with($assetPath, '/_nuxt/')
            || in_array($extension, ['css', 'js', 'mjs', 'png', 'jpg', 'jpeg', 'gif', 'svg', 'webp', 'ico', 'avif', 'woff', 'woff2'], true);
    }

    private function injectBaseTag(string $html, string $basePath): string
    {
        if (preg_match('/<base\s/i', $html)) {
            return $html;
        }

        $baseTag = "<base href=\"{$basePath}\">";

        if (preg_match('/<head[^>]*>/i', $html, $match, PREG_OFFSET_CAPTURE)) {
            $position = $match[0][1] + strlen($match[0][0]);

            return substr_replace($html, "\n{$baseTag}\n", $position, 0);
        }

        return "{$baseTag}\n{$html}";
    }

    private function assetBaseUrl(Site $site): string
    {
        $assetRoute = route('editor.asset', ['site' => $site->id, 'path' => '__pk_asset__']);

        return Str::beforeLast($assetRoute, '/__pk_asset__');
    }

    private function htmlResponse(string $html): Response
    {
        return response($html, 200, [
            'Content-Type' => 'text/html; charset=utf-8',
            'X-Frame-Options' => 'SAMEORIGIN',
        ]);
    }

    private function renderUnavailablePreview(Site $site, Page $page): string
    {
        $expectedOutput = $this->runtime->usesRuntimeServer($site)
            ? 'a successful runtime deploy'
            : ($site->project_type === 'nextjs'
                ? ($site->build_output_dir && $site->build_output_dir !== '.next' ? $site->build_output_dir : 'out')
                : ($site->build_output_dir ?: 'the configured build output directory'));

        $filePath = e($page->file_path);
        $projectType = e($site->project_type);
        $expectedOutput = e($expectedOutput);

        return <<<HTML
<html>
<body style="background:#09090b;color:#d4d4d8;font-family:system-ui;height:100vh;margin:0;display:flex;align-items:center;justify-content:center;padding:24px;">
    <div style="max-width:720px;background:#18181b;border:1px solid #27272a;border-radius:16px;padding:24px;line-height:1.6;">
        <h1 style="margin:0 0 12px;font-size:20px;color:#fafafa;">Preview unavailable</h1>
        <p style="margin:0 0 12px;">The editor cannot preview the source file <code style="color:#a78bfa;">{$filePath}</code> directly because this page belongs to a <code style="color:#a78bfa;">{$projectType}</code> project.</p>
        <p style="margin:0;">Build or deploy the site so the rendered page is available via <code style="color:#a78bfa;">{$expectedOutput}</code>, then reopen the page editor.</p>
    </div>
</body>
</html>
HTML;
    }

    private function renderFailurePreview(Site $site, Page $page, \Throwable $e): string
    {
        $message = e($e->getMessage());
        $filePath = e($page->file_path);
        $siteName = e($site->name);

        return <<<HTML
<html>
<body style="background:#09090b;color:#d4d4d8;font-family:system-ui;height:100vh;margin:0;display:flex;align-items:center;justify-content:center;padding:24px;">
    <div style="max-width:760px;background:#18181b;border:1px solid #3f3f46;border-radius:16px;padding:24px;line-height:1.6;">
        <h1 style="margin:0 0 12px;font-size:20px;color:#fafafa;">Preview failed</h1>
        <p style="margin:0 0 12px;">pixelkraft hit an internal preview error while rendering <code style="color:#a78bfa;">{$filePath}</code> for <code style="color:#a78bfa;">{$siteName}</code>.</p>
        <pre style="margin:0;background:#09090b;border-radius:12px;padding:16px;overflow:auto;color:#fda4af;white-space:pre-wrap;">{$message}</pre>
    </div>
</body>
</html>
HTML;
    }
}
