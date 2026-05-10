<?php

namespace App\Services;

use App\Models\Site;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Manages media files stored in Cloudflare R2 (S3-compatible).
 *
 * Requires in .env:
 *   R2_ACCESS_KEY_ID, R2_SECRET_ACCESS_KEY, R2_BUCKET, R2_ENDPOINT, R2_URL
 *
 * Files are stored under the site's r2_bucket_prefix:
 *   sites/{slug}/media/{filename}
 *
 * Public URLs use R2_URL (the public bucket URL or a custom domain).
 */
class R2MediaService
{
    /** Maximum upload size in bytes (10 MB). */
    private const MAX_BYTES = 10 * 1024 * 1024;

    /** Allowed MIME types for uploads. */
    private const ALLOWED_MIMES = [
        'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml', 'image/avif',
        'video/mp4', 'video/webm',
        'application/pdf',
        'font/woff', 'font/woff2',
    ];

    public function isConfigured(): bool
    {
        return (bool) config('filesystems.disks.r2.key')
            && (bool) config('filesystems.disks.r2.endpoint');
    }

    /**
     * Upload a file to R2 and return its public URL.
     */
    public function upload(Site $site, UploadedFile $file): string
    {
        if (! $this->isConfigured()) {
            throw new \RuntimeException('R2 is not configured. Set R2_ACCESS_KEY_ID, R2_SECRET_ACCESS_KEY, R2_BUCKET, R2_ENDPOINT, and R2_URL in .env.');
        }

        if ($file->getSize() > self::MAX_BYTES) {
            throw new \InvalidArgumentException('File exceeds the 10 MB upload limit.');
        }

        $mime = $file->getMimeType();
        if (! in_array($mime, self::ALLOWED_MIMES, true)) {
            throw new \InvalidArgumentException("File type [{$mime}] is not allowed. Accepted: images, video, PDF, fonts.");
        }

        $prefix = rtrim((string) ($site->r2_bucket_prefix ?: "sites/{$site->slug}/media"), '/');
        $originalName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $ext = $file->getClientOriginalExtension();
        $safeName = Str::slug($originalName).($ext ? ".{$ext}" : '');
        $path = "{$prefix}/{$safeName}";

        Storage::disk('r2')->put($path, $file->getContent(), [
            'visibility' => 'public',
            'ContentType' => $mime,
            'CacheControl' => 'public, max-age=31536000',
        ]);

        Log::info("R2 upload: [{$path}] for site [{$site->slug}]");

        return $this->publicUrl($path);
    }

    /**
     * List all media files for a site.
     *
     * @return list<array{path: string, url: string, size: int, last_modified: string}>
     */
    public function list(Site $site): array
    {
        if (! $this->isConfigured()) {
            return [];
        }

        $prefix = rtrim((string) ($site->r2_bucket_prefix ?: "sites/{$site->slug}/media"), '/');

        try {
            $files = Storage::disk('r2')->files($prefix);
        } catch (\Throwable $e) {
            Log::warning("R2 list failed for [{$site->slug}]", ['error' => $e->getMessage()]);

            return [];
        }

        return collect($files)->map(function (string $file) {
            try {
                return [
                    'path' => $file,
                    'name' => basename($file),
                    'url' => $this->publicUrl($file),
                    'size' => Storage::disk('r2')->size($file),
                    'last_modified' => date('Y-m-d H:i', Storage::disk('r2')->lastModified($file)),
                ];
            } catch (\Throwable) {
                return null;
            }
        })->filter()->values()->all();
    }

    /**
     * Delete a file from R2.
     */
    public function delete(Site $site, string $path): void
    {
        if (! $this->isConfigured()) {
            return;
        }

        $prefix = rtrim((string) ($site->r2_bucket_prefix ?: "sites/{$site->slug}/media"), '/');

        // Reject paths that escape the site's prefix.
        if (! str_starts_with($path, $prefix.'/')) {
            throw new \InvalidArgumentException("Path [{$path}] is outside the site media prefix.");
        }

        Storage::disk('r2')->delete($path);
        Log::info("R2 delete: [{$path}] for site [{$site->slug}]");
    }

    private function publicUrl(string $path): string
    {
        $base = rtrim((string) config('filesystems.disks.r2.url', ''), '/');

        return $base.'/'.$path;
    }
}
