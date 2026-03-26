<?php

namespace App\Livewire\Files;

use App\Models\Site;
use App\Services\GitSyncService;
use Illuminate\Support\Facades\File;
use Livewire\Component;
use Livewire\WithFileUploads;

class FileManager extends Component
{
    use WithFileUploads;

    public string $siteId;
    public string $currentPath = '';
    public ?string $viewingFile = null;
    public string $fileContent = '';
    public $uploadFile = null;

    public function navigateTo(string $path): void
    {
        // Prevent path traversal
        if (str_contains($path, '..')) {
            return;
        }

        $this->currentPath = $path;
        $this->viewingFile = null;
    }

    public function goUp(): void
    {
        $this->currentPath = dirname($this->currentPath);

        if ($this->currentPath === '.') {
            $this->currentPath = '';
        }
    }

    public function viewFile(string $relativePath): void
    {
        $site = Site::findOrFail($this->siteId);
        $fullPath = "{$site->repo_path}/{$relativePath}";

        if (! File::exists($fullPath) || ! File::isFile($fullPath)) {
            return;
        }

        // Only view text-based files
        $ext = strtolower(pathinfo($relativePath, PATHINFO_EXTENSION));
        $textExts = ['html', 'htm', 'css', 'js', 'mjs', 'json', 'md', 'txt', 'xml', 'svg', 'yml', 'yaml', 'toml', 'php', 'py', 'jsx', 'tsx', 'vue', 'svelte', 'astro', 'njk', 'liquid', 'env', 'gitignore', 'htaccess'];

        if (in_array($ext, $textExts, true) || empty($ext)) {
            $this->fileContent = File::get($fullPath);
            $this->viewingFile = $relativePath;
        }
    }

    public function saveFile(): void
    {
        if (! $this->viewingFile) {
            return;
        }

        $site = Site::findOrFail($this->siteId);
        $fullPath = "{$site->repo_path}/{$this->viewingFile}";

        File::put($fullPath, $this->fileContent);

        try {
            $git = app(GitSyncService::class);
            $git->commitAndPush($site, [$this->viewingFile], "Edit {$this->viewingFile}");
            session()->flash('success', 'File saved and pushed.');
        } catch (\Throwable $e) {
            session()->flash('error', 'Saved locally but push failed: ' . $e->getMessage());
        }
    }

    public function upload(): void
    {
        $this->validate(['uploadFile' => 'required|file|max:10240']);

        $site = Site::findOrFail($this->siteId);
        $targetDir = $this->currentPath
            ? "{$site->repo_path}/{$this->currentPath}"
            : $site->repo_path;

        if (! File::isDirectory($targetDir)) {
            session()->flash('error', 'Target directory not found.');
            return;
        }

        $filename = $this->uploadFile->getClientOriginalName();
        $this->uploadFile->storeAs('', $filename, ['disk' => 'local']);

        $sourcePath = storage_path("app/private/{$filename}");
        $destPath = "{$targetDir}/{$filename}";

        File::move($sourcePath, $destPath);

        try {
            $relativePath = $this->currentPath ? "{$this->currentPath}/{$filename}" : $filename;
            $git = app(GitSyncService::class);
            $git->commitAndPush($site, [$relativePath], "Upload {$filename}");
            session()->flash('success', "Uploaded {$filename}.");
        } catch (\Throwable $e) {
            session()->flash('error', 'Uploaded locally but push failed: ' . $e->getMessage());
        }

        $this->uploadFile = null;
    }

    public function deleteFile(string $relativePath): void
    {
        $site = Site::findOrFail($this->siteId);
        $fullPath = "{$site->repo_path}/{$relativePath}";

        if (! File::exists($fullPath)) {
            return;
        }

        File::delete($fullPath);

        try {
            $git = app(GitSyncService::class);
            $git->commitAllAndPush($site, "Delete {$relativePath}");
            session()->flash('success', "Deleted {$relativePath}.");
        } catch (\Throwable $e) {
            session()->flash('error', 'Deleted locally but push failed: ' . $e->getMessage());
        }

        if ($this->viewingFile === $relativePath) {
            $this->viewingFile = null;
            $this->fileContent = '';
        }
    }

    public function closeFile(): void
    {
        $this->viewingFile = null;
        $this->fileContent = '';
    }

    public function formatSize(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes . ' B';
        }

        if ($bytes < 1048576) {
            return round($bytes / 1024, 1) . ' KB';
        }

        return round($bytes / 1048576, 1) . ' MB';
    }

    public function render()
    {
        $site = Site::findOrFail($this->siteId);
        $entries = $this->getDirectoryEntries($site);

        return view('livewire.files.file-manager', [
            'site'    => $site,
            'entries' => $entries,
        ]);
    }

    private function getDirectoryEntries(Site $site): array
    {
        $basePath = $site->repo_path;
        $scanPath = $this->currentPath ? "{$basePath}/{$this->currentPath}" : $basePath;

        if (! File::isDirectory($scanPath)) {
            return [];
        }

        $entries = [];

        foreach (File::directories($scanPath) as $dir) {
            $name = basename($dir);

            if (str_starts_with($name, '.') || in_array($name, ['node_modules', 'vendor', '__pycache__'])) {
                continue;
            }

            $relativePath = $this->currentPath ? "{$this->currentPath}/{$name}" : $name;

            $entries[] = [
                'name'     => $name,
                'path'     => $relativePath,
                'type'     => 'directory',
                'size'     => null,
                'modified' => File::lastModified($dir),
            ];
        }

        foreach (File::files($scanPath) as $file) {
            $name = $file->getFilename();

            if (str_starts_with($name, '.') && $name !== '.htaccess') {
                continue;
            }

            $relativePath = $this->currentPath ? "{$this->currentPath}/{$name}" : $name;

            $entries[] = [
                'name'     => $name,
                'path'     => $relativePath,
                'type'     => 'file',
                'size'     => $file->getSize(),
                'ext'      => strtolower($file->getExtension()),
                'modified' => $file->getMTime(),
            ];
        }

        // Sort: directories first, then files alphabetically
        usort($entries, function ($a, $b) {
            if ($a['type'] !== $b['type']) {
                return $a['type'] === 'directory' ? -1 : 1;
            }

            return strcasecmp($a['name'], $b['name']);
        });

        return $entries;
    }
}
