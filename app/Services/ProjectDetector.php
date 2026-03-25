<?php

namespace App\Services;

use App\Models\Site;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class ProjectDetector
{
    /**
     * Detect the project type and suggest build configuration.
     *
     * @return array{type: string, build_command: ?string, build_output_dir: ?string, confidence: float}
     */
    public function detect(Site $site): array
    {
        $repoPath = $site->repo_path;

        if (! File::isDirectory($repoPath)) {
            return $this->result('custom', null, null, 0);
        }

        // Check package.json first for JS-based projects
        $packageJson = $this->readPackageJson($repoPath);

        // Run detection rules in priority order
        $detectors = [
            [$this, 'detectAstro'],
            [$this, 'detectNextjs'],
            [$this, 'detectNuxt'],
            [$this, 'detectSvelte'],
            [$this, 'detectVue'],
            [$this, 'detectReact'],
            [$this, 'detectHugo'],
            [$this, 'detectEleventy'],
            [$this, 'detectStaticHtml'],
        ];

        foreach ($detectors as $detector) {
            $result = $detector($repoPath, $packageJson);
            if ($result !== null) {
                Log::info("Detected project type for [{$site->slug}]", $result);
                return $result;
            }
        }

        Log::info("Could not auto-detect project type for [{$site->slug}], defaulting to custom");

        return $this->result('custom', null, null, 0.1);
    }

    /**
     * Apply detected settings to a site (only if not already configured).
     */
    public function applyToSite(Site $site): array
    {
        $detection = $this->detect($site);

        $updates = ['project_type' => $detection['type']];

        if (empty($site->build_command) && $detection['build_command']) {
            $updates['build_command'] = $detection['build_command'];
        }

        if (empty($site->build_output_dir) && $detection['build_output_dir']) {
            $updates['build_output_dir'] = $detection['build_output_dir'];
        }

        $site->update($updates);

        return $detection;
    }

    // ── Detectors ───────────────────────────────

    private function detectAstro(string $path, ?array $pkg): ?array
    {
        if ($this->packageHasDependency($pkg, 'astro')) {
            return $this->result('astro', 'npx astro build', 'dist', 0.95);
        }

        if (File::exists("{$path}/astro.config.mjs") || File::exists("{$path}/astro.config.ts")) {
            return $this->result('astro', 'npx astro build', 'dist', 0.9);
        }

        return null;
    }

    private function detectNextjs(string $path, ?array $pkg): ?array
    {
        if ($this->packageHasDependency($pkg, 'next')) {
            $buildCmd = $this->getScript($pkg, 'build') ?? 'npx next build';

            return $this->result('nextjs', $buildCmd, '.next', 0.95);
        }

        if (File::exists("{$path}/next.config.js") || File::exists("{$path}/next.config.mjs") || File::exists("{$path}/next.config.ts")) {
            return $this->result('nextjs', 'npx next build', '.next', 0.85);
        }

        return null;
    }

    private function detectNuxt(string $path, ?array $pkg): ?array
    {
        if ($this->packageHasDependency($pkg, 'nuxt')) {
            $buildCmd = $this->getScript($pkg, 'build') ?? 'npx nuxt build';

            return $this->result('nuxt', $buildCmd, '.output/public', 0.95);
        }

        if (File::exists("{$path}/nuxt.config.ts") || File::exists("{$path}/nuxt.config.js")) {
            return $this->result('nuxt', 'npx nuxt build', '.output/public', 0.85);
        }

        return null;
    }

    private function detectSvelte(string $path, ?array $pkg): ?array
    {
        if ($this->packageHasDependency($pkg, '@sveltejs/kit')) {
            $buildCmd = $this->getScript($pkg, 'build') ?? 'npm run build';

            return $this->result('svelte', $buildCmd, 'build', 0.95);
        }

        if ($this->packageHasDependency($pkg, 'svelte')) {
            $buildCmd = $this->getScript($pkg, 'build') ?? 'npm run build';

            return $this->result('svelte', $buildCmd, 'public', 0.85);
        }

        return null;
    }

    private function detectVue(string $path, ?array $pkg): ?array
    {
        if ($this->packageHasDependency($pkg, 'vue')) {
            $buildCmd = $this->getScript($pkg, 'build') ?? 'npm run build';
            $outputDir = 'dist';

            // Vite-based Vue projects
            if ($this->packageHasDependency($pkg, 'vite')) {
                $outputDir = 'dist';
            }

            return $this->result('vue', $buildCmd, $outputDir, 0.85);
        }

        return null;
    }

    private function detectReact(string $path, ?array $pkg): ?array
    {
        if ($this->packageHasDependency($pkg, 'react')) {
            $buildCmd = $this->getScript($pkg, 'build') ?? 'npm run build';

            // CRA uses build/, Vite uses dist/
            $outputDir = $this->packageHasDependency($pkg, 'vite') ? 'dist' : 'build';

            return $this->result('react', $buildCmd, $outputDir, 0.85);
        }

        return null;
    }

    private function detectHugo(string $path, ?array $pkg): ?array
    {
        $configFiles = ['hugo.toml', 'hugo.yaml', 'hugo.json', 'config.toml', 'config.yaml', 'config.json'];

        foreach ($configFiles as $configFile) {
            if (File::exists("{$path}/{$configFile}")) {
                // Check if it's actually Hugo config (config.toml could be anything)
                if (str_starts_with($configFile, 'hugo.') || $this->fileContains("{$path}/{$configFile}", 'baseURL')) {
                    return $this->result('hugo', 'hugo --minify', 'public', 0.95);
                }
            }
        }

        // Check for Hugo directory structure
        if (File::isDirectory("{$path}/content") && File::isDirectory("{$path}/layouts")) {
            return $this->result('hugo', 'hugo --minify', 'public', 0.7);
        }

        return null;
    }

    private function detectEleventy(string $path, ?array $pkg): ?array
    {
        $configFiles = ['.eleventy.js', 'eleventy.config.js', 'eleventy.config.mjs', 'eleventy.config.cjs'];

        foreach ($configFiles as $configFile) {
            if (File::exists("{$path}/{$configFile}")) {
                $buildCmd = $this->getScript($pkg, 'build') ?? 'npx @11ty/eleventy';

                return $this->result('eleventy', $buildCmd, '_site', 0.95);
            }
        }

        if ($this->packageHasDependency($pkg, '@11ty/eleventy')) {
            $buildCmd = $this->getScript($pkg, 'build') ?? 'npx @11ty/eleventy';

            return $this->result('eleventy', $buildCmd, '_site', 0.9);
        }

        return null;
    }

    private function detectStaticHtml(string $path, ?array $pkg): ?array
    {
        // Look for HTML files in root or common directories
        $htmlLocations = [
            $path,
            "{$path}/public",
            "{$path}/src",
        ];

        foreach ($htmlLocations as $dir) {
            if (! File::isDirectory($dir)) {
                continue;
            }

            $htmlFiles = File::glob("{$dir}/*.html");
            if (! empty($htmlFiles)) {
                // Check if there's an index.html — strong indicator of static site
                $hasIndex = File::exists("{$dir}/index.html");
                $confidence = $hasIndex ? 0.9 : 0.6;

                // If HTML is in a subdirectory, that's the output dir
                $outputDir = ($dir !== $path) ? basename($dir) : null;

                return $this->result('static_html', null, $outputDir, $confidence);
            }
        }

        return null;
    }

    // ── Helpers ──────────────────────────────────

    private function readPackageJson(string $path): ?array
    {
        $packagePath = "{$path}/package.json";

        if (! File::exists($packagePath)) {
            return null;
        }

        $content = File::get($packagePath);
        $data = json_decode($content, true);

        return is_array($data) ? $data : null;
    }

    private function packageHasDependency(?array $pkg, string $name): bool
    {
        if (! $pkg) {
            return false;
        }

        return isset($pkg['dependencies'][$name])
            || isset($pkg['devDependencies'][$name]);
    }

    private function getScript(?array $pkg, string $name): ?string
    {
        return $pkg['scripts'][$name] ?? null;
    }

    private function fileContains(string $path, string $needle): bool
    {
        if (! File::exists($path)) {
            return false;
        }

        return str_contains(File::get($path), $needle);
    }

    private function result(string $type, ?string $buildCommand, ?string $outputDir, float $confidence): array
    {
        return [
            'type'             => $type,
            'build_command'    => $buildCommand,
            'build_output_dir' => $outputDir,
            'confidence'       => $confidence,
        ];
    }
}
