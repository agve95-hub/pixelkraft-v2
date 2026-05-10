<?php

namespace App\Livewire\Files;

use App\Services\R2MediaService;
use App\Support\SiteAccess;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Cloud media library backed by Cloudflare R2.
 *
 * Provides upload, list, delete, and copy-URL for site media files that
 * should not be stored in the git repository (images, videos, PDFs).
 * Requires R2 to be configured in .env (R2_ACCESS_KEY_ID, R2_SECRET_ACCESS_KEY,
 * R2_BUCKET, R2_ENDPOINT, R2_URL).
 */
class MediaLibrary extends Component
{
    use WithFileUploads;

    public string $siteId;

    public $mediaFile = null;

    public ?string $uploadError = null;

    public ?string $lastUploadedUrl = null;

    public function upload(): void
    {
        $this->uploadError = null;
        $this->lastUploadedUrl = null;

        $this->validate([
            'mediaFile' => 'required|file|max:10240|mimes:jpg,jpeg,png,gif,webp,svg,avif,mp4,webm,pdf,woff,woff2',
        ]);

        $site = SiteAccess::findOrFail($this->siteId);
        $r2 = app(R2MediaService::class);

        if (! $r2->isConfigured()) {
            $this->uploadError = 'R2 is not configured. Add R2_ACCESS_KEY_ID, R2_SECRET_ACCESS_KEY, R2_BUCKET, R2_ENDPOINT, and R2_URL to .env.';

            return;
        }

        try {
            $url = $r2->upload($site, $this->mediaFile);
            $this->lastUploadedUrl = $url;
            $this->mediaFile = null;
            session()->flash('success', 'File uploaded. URL copied below.');
        } catch (\Throwable $e) {
            $this->uploadError = $e->getMessage();
        }
    }

    public function deleteMedia(string $path): void
    {
        $site = SiteAccess::findOrFail($this->siteId);

        try {
            app(R2MediaService::class)->delete($site, $path);
            session()->flash('success', 'File deleted.');
        } catch (\Throwable $e) {
            session()->flash('error', 'Delete failed: '.$e->getMessage());
        }
    }

    public function render(): View
    {
        $site = SiteAccess::findOrFail($this->siteId);
        $r2 = app(R2MediaService::class);

        return view('livewire.files.media-library', [
            'site' => $site,
            'isConfigured' => $r2->isConfigured(),
            'mediaFiles' => $r2->isConfigured() ? $r2->list($site) : [],
        ]);
    }
}
