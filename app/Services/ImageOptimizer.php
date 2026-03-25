<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

class ImageOptimizer
{
    /**
     * Optimize all images in a directory (recursive).
     * Returns number of images optimized.
     */
    public function optimizeDirectory(string $directory): int
    {
        if (! File::isDirectory($directory)) {
            return 0;
        }

        $count = 0;
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            $ext = strtolower($file->getExtension());

            if (! in_array($ext, ['jpg', 'jpeg', 'png', 'svg', 'gif'], true)) {
                continue;
            }

            $path = $file->getPathname();

            try {
                $optimized = match ($ext) {
                    'jpg', 'jpeg' => $this->optimizeJpeg($path),
                    'png'         => $this->optimizePng($path),
                    'svg'         => $this->optimizeSvg($path),
                    'gif'         => false, // Skip gif optimization
                    default       => false,
                };

                // Generate WebP variant for jpg/png
                if (in_array($ext, ['jpg', 'jpeg', 'png'], true)) {
                    $this->generateWebp($path);
                }

                if ($optimized) {
                    $count++;
                }
            } catch (\Throwable $e) {
                Log::warning("Image optimization failed for [{$path}]", ['error' => $e->getMessage()]);
            }
        }

        return $count;
    }

    /**
     * Optimize a single image file.
     */
    public function optimizeFile(string $path): bool
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return match ($ext) {
            'jpg', 'jpeg' => $this->optimizeJpeg($path),
            'png'         => $this->optimizePng($path),
            'svg'         => $this->optimizeSvg($path),
            default       => false,
        };
    }

    private function optimizeJpeg(string $path): bool
    {
        if (! $this->commandExists('jpegoptim')) {
            return false;
        }

        $result = Process::timeout(30)->run(
            'jpegoptim --strip-all --max=85 --quiet ' . escapeshellarg($path)
        );

        return $result->successful();
    }

    private function optimizePng(string $path): bool
    {
        if (! $this->commandExists('pngquant')) {
            return false;
        }

        $result = Process::timeout(30)->run(
            'pngquant --force --quality=65-85 --output ' . escapeshellarg($path) . ' -- ' . escapeshellarg($path)
        );

        return $result->successful();
    }

    private function optimizeSvg(string $path): bool
    {
        if (! $this->commandExists('svgo')) {
            return false;
        }

        $result = Process::timeout(15)->run(
            'svgo --quiet ' . escapeshellarg($path)
        );

        return $result->successful();
    }

    /**
     * Generate a .webp variant of an image using cwebp.
     */
    public function generateWebp(string $path): bool
    {
        if (! $this->commandExists('cwebp')) {
            return false;
        }

        $webpPath = preg_replace('/\.(jpe?g|png)$/i', '.webp', $path);

        // Skip if WebP already exists and is newer
        if (File::exists($webpPath) && File::lastModified($webpPath) >= File::lastModified($path)) {
            return true;
        }

        $result = Process::timeout(30)->run(
            'cwebp -quiet -q 80 ' . escapeshellarg($path) . ' -o ' . escapeshellarg($webpPath)
        );

        return $result->successful();
    }

    private function commandExists(string $command): bool
    {
        $result = Process::run("which {$command}");

        return $result->successful();
    }
}
