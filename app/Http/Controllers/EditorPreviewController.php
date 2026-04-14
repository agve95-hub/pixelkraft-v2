<?php

namespace App\Http\Controllers;

use App\Models\Page;
use App\Models\Site;
use App\Services\PagePreviewService;
use App\Services\PreviewOverlayService;
use App\Services\SiteRuntimeService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class EditorPreviewController extends Controller
{
    public function __construct(
        private PagePreviewService $previews,
        private PreviewOverlayService $overlays,
        private SiteRuntimeService $runtime,
    ) {}

    /**
     * Serve a page's HTML for the editor iframe preview.
     */
    public function show(Site $site, Page $page): Response
    {
        abort_unless($page->site_id === $site->id, 404);

        try {
            $page->loadMissing('editableRegions');
            $preview = $this->resolvePreviewSource($site, $page);

            if ($preview['mode'] === 'runtime') {
                return $this->renderRuntimePreview($site, $page);
            }

            if ($preview['mode'] !== 'file') {
                $fallbackPreview = $this->renderSourceFallbackPreview($site, $page);

                if ($fallbackPreview !== null) {
                    return $this->htmlResponse($this->decoratePreview($site, $page, $fallbackPreview));
                }

                return $this->htmlResponse($this->renderUnavailablePreview($site, $page));
            }

            $html = File::get($preview['file_path']);
            $html = $this->resolveFileAssetPaths(
                $html,
                $site,
                $preview['root_prefix'],
                $preview['directory_prefix'],
            );

            return $this->htmlResponse($this->decoratePreview($site, $page, $html));
        } catch (\Throwable $e) {
            Log::warning('Editor preview failed to render page.', [
                'site_id' => $site->id,
                'page_id' => $page->id,
                'error' => $e->getMessage(),
            ]);

            return $this->htmlResponse($this->renderFailurePreview($site, $page));
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
        // First-pass checks: extension whitelist + literal path-traversal sequences.
        if (! $this->isAllowedLocalAsset($path)) {
            abort(404);
        }

        // Resolve the repo root to a canonical absolute path. This must succeed for
        // a valid, already-cloned site.
        $repoRoot = realpath((string) $site->repo_path);
        if ($repoRoot === false) {
            abort(404);
        }

        // Build the candidate full path and canonicalise it. realpath() follows
        // symlinks, so a symlink inside the repo pointing outside it will be caught.
        $fullPath = $repoRoot.DIRECTORY_SEPARATOR.ltrim(
            str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path),
            DIRECTORY_SEPARATOR,
        );
        $realFullPath = realpath($fullPath);

        // Reject if the file does not exist or resolves outside the repo root.
        // The trailing separator on $repoRoot prevents a sibling-directory prefix match
        // (e.g. /var/www/repos/site vs /var/www/repos/site-evil).
        if (
            $realFullPath === false
            || ! str_starts_with($realFullPath, $repoRoot.DIRECTORY_SEPARATOR)
        ) {
            abort(404);
        }

        $mimeType = $this->getMimeType($path);
        $content = File::get($realFullPath);

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

        $response = $this->runtime->fetch($site, '/'.ltrim($path, '/'), $request->query());

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
            $directoryPrefix ? $this->assetBaseUrl($site).'/'.$directoryPrefix.'/' : $this->assetBaseUrl($site).'/',
        );

        return $this->htmlResponse($this->decoratePreview($site, $page, $html));
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
            'css' => 'text/css',
            'js', 'mjs' => 'application/javascript',
            'json' => 'application/json',
            'svg' => 'image/svg+xml',
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'avif' => 'image/avif',
            'ico' => 'image/x-icon',
            'woff' => 'font/woff',
            'woff2' => 'font/woff2',
            'ttf' => 'font/ttf',
            'xml' => 'application/xml',
            default => 'application/octet-stream',
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
                    $repoRelativePath = trim($rootPrefix, '/').'/'.$repoRelativePath;
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

    private function decoratePreview(Site $site, Page $page, string $html): string
    {
        return $this->overlays->decorate($site, $page, $html);
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

    private function renderSourceFallbackPreview(Site $site, Page $page): ?string
    {
        $path = "{$site->repo_path}/{$page->file_path}";
        if (! File::exists($path)) {
            return null;
        }

        $normalizedPath = strtolower(str_replace('\\', '/', $page->file_path));
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $isBladeTemplate = str_ends_with($normalizedPath, '.blade.php');

        if (! $isBladeTemplate && ! in_array($extension, ['jsx', 'tsx', 'vue', 'svelte', 'astro', 'md', 'mdx', 'php'], true)) {
            return null;
        }
        $source = File::get($path);
        $snippets = $this->extractSourceSnippets($source);

        if (empty($snippets)) {
            return null;
        }

        $sections = collect($snippets)
            ->map(function (string $snippet, int $index) {
                $safeSnippet = e($snippet);
                $label = $index + 1;

                return <<<HTML
<section data-pk-fallback-snippet="{$label}" style="padding:14px 16px;border:1px solid #27272a;border-radius:10px;background:#111827;">
    <p style="margin:0;color:#e4e4e7;line-height:1.45;">{$safeSnippet}</p>
</section>
HTML;
            })
            ->implode("\n");

        $filePath = e($page->file_path);
        $projectType = e($site->project_type);

        return <<<HTML
<html>
<head>
    <meta charset="utf-8">
    <title>Source fallback preview</title>
</head>
<body style="margin:0;background:#09090b;color:#d4d4d8;font-family:system-ui,sans-serif;padding:20px;">
    <main data-pk-fallback-preview="true" style="max-width:920px;margin:0 auto;display:grid;gap:12px;">
        <div style="border:1px solid #27272a;border-radius:12px;background:#18181b;padding:14px 16px;">
            <h1 style="margin:0;font-size:16px;color:#fafafa;">Source fallback preview</h1>
            <p style="margin:8px 0 0;color:#a1a1aa;font-size:13px;line-height:1.5;">
                Live built output is unavailable for <code style="color:#a78bfa;">{$filePath}</code>.
                This fallback extracts editable text snippets from the <code style="color:#a78bfa;">{$projectType}</code> source so Visual mode can still map and edit content.
            </p>
        </div>
        {$sections}
    </main>
</body>
</html>
HTML;
    }

    /**
     * @return list<string>
     */
    private function extractSourceSnippets(string $source): array
    {
        $source = preg_replace('/<\?(?:php|=)?[\s\S]*?\?>/i', ' ', $source) ?? $source;
        $source = preg_replace('/\{!![\s\S]*?!!\}/', ' ', $source) ?? $source;
        $source = preg_replace('/\{\{\s*[^}]+\s*\}\}/', ' ', $source) ?? $source;
        // Strip Blade-style @directives only — a broad @\w+ match removes valid JSX/text
        // such as social handles (@feuerlauf) inside elements.
        $bladeDirectives = implode('|', [
            'if', 'elseif', 'else', 'endif', 'unless', 'endunless', 'isset', 'endisset',
            'empty', 'endempty', 'auth', 'endauth', 'guest', 'endguest', 'switch', 'endswitch',
            'case', 'break', 'default', 'foreach', 'endforeach', 'for', 'endfor', 'while', 'endwhile',
            'continue', 'php', 'endphp', 'verbatim', 'endverbatim', 'component', 'endcomponent',
            'slot', 'endslot', 'props', 'aware', 'class', 'push', 'endpush', 'prepend', 'endprepend',
            'once', 'endonce', 'error', 'enderror', 'include', 'each', 'extends', 'section', 'show',
            'parent', 'yield', 'stack', 'inject', 'csrf', 'method', 'json', 'production', 'env',
            'hasSection', 'sectionMissing',
        ]);
        $source = preg_replace('/@(?:'.$bladeDirectives.')(?:\s*\(|\b)/', ' ', $source) ?? $source;
        $source = preg_replace('/{{--[\s\S]*?--}}/', ' ', $source) ?? $source;
        $source = preg_replace('/<script\b[^>]*>.*?<\/script>/is', ' ', $source) ?? $source;
        $source = preg_replace('/<style\b[^>]*>.*?<\/style>/is', ' ', $source) ?? $source;

        $matches = [];
        preg_match_all('/>\s*([^<>{}\n][^<>{}\n]{3,200})\s*</u', $source, $tagTextMatches);
        preg_match_all('/["\']([^"\']{4,200})["\']/u', $source, $quotedMatches);

        $candidates = array_merge($tagTextMatches[1] ?? [], $quotedMatches[1] ?? []);

        foreach ($candidates as $candidate) {
            $snippet = trim(html_entity_decode((string) $candidate, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            $snippet = preg_replace('/\s+/u', ' ', $snippet) ?? $snippet;

            if ($snippet === '' || mb_strlen($snippet) < 4) {
                continue;
            }

            if (preg_match('/^(import|export|const|let|var|function|return|class|if|else|for|while)\b/i', $snippet)) {
                continue;
            }

            if (preg_match('~^[#./@:_-]+$~u', $snippet)) {
                continue;
            }

            $matches[] = $snippet;
        }

        $unique = [];
        foreach ($matches as $snippet) {
            $key = mb_strtolower($snippet);
            if (! array_key_exists($key, $unique)) {
                $unique[$key] = $snippet;
            }
        }

        return array_slice(array_values($unique), 0, 80);
    }

    private function renderFailurePreview(Site $site, Page $page): string
    {
        $filePath = e($page->file_path);
        $siteName = e($site->name);

        return <<<HTML
<html>
<body style="background:#09090b;color:#d4d4d8;font-family:system-ui;height:100vh;margin:0;display:flex;align-items:center;justify-content:center;padding:24px;">
    <div style="max-width:760px;background:#18181b;border:1px solid #3f3f46;border-radius:16px;padding:24px;line-height:1.6;">
        <h1 style="margin:0 0 12px;font-size:20px;color:#fafafa;">Preview failed</h1>
        <p style="margin:0 0 12px;">pixelkraft hit an internal preview error while rendering <code style="color:#a78bfa;">{$filePath}</code> for <code style="color:#a78bfa;">{$siteName}</code>.</p>
        <p style="margin:0;color:#fda4af;">The preview service encountered an internal error. Check application logs for details and try again.</p>
    </div>
</body>
</html>
HTML;
    }
}
