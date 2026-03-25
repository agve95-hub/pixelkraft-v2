<?php

namespace App\Http\Controllers;

use App\Models\Page;
use App\Models\Site;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\File;

class EditorPreviewController extends Controller
{
    /**
     * Serve a page's HTML for the editor iframe preview.
     */
    public function show(Site $site, Page $page): Response
    {
        $repoPath = $site->repo_path;
        $filePath = "{$repoPath}/{$page->file_path}";

        if (! File::exists($filePath)) {
            return response('<html><body style="background:#09090b;color:#71717a;font-family:system-ui;display:flex;align-items:center;justify-content:center;height:100vh;margin:0"><p>File not found: ' . e($page->file_path) . '</p></body></html>', 404);
        }

        $html = File::get($filePath);

        // Resolve relative asset paths to work from repo directory
        $html = $this->resolveAssetPaths($html, $site, $page);

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
    private function resolveAssetPaths(string $html, Site $site, Page $page): string
    {
        $baseUrl = route('editor.asset', ['site' => $site->id, 'path' => '']);

        // Determine the directory of the current file for relative resolution
        $fileDir = dirname($page->file_path);

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

        return $html;
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
}
