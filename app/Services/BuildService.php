<?php

namespace App\Services;

use App\Models\DeployLog;
use App\Models\Site;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

/**
 * Handles all shell-level build operations: dependency installation, build command
 * execution, package manager detection, node version selection, and output scrubbing.
 *
 * Extracted from DeployService so that build concerns are isolated, independently
 * testable, and reusable by both the queued deploy chain and the synchronous path.
 */
class BuildService
{
    /**
     * Env var names that could be used to hijack the Node.js / shell runtime.
     * User-supplied `env_variables` keys matching any of these are silently dropped
     * before being passed to the build process.
     *
     * @var list<string>
     */
    public const DANGEROUS_ENV_VARS = [
        'NODE_OPTIONS', 'NODE_PATH', 'NODE_EXTRA_CA_CERTS',
        'NPM_CONFIG_USERCONFIG', 'NPM_CONFIG_GLOBALCONFIG', 'NPM_CONFIG_PREFIX',
        'PNPM_HOME', 'BUN_INSTALL',
        'LD_PRELOAD', 'LD_LIBRARY_PATH',
        'DYLD_INSERT_LIBRARIES', 'DYLD_LIBRARY_PATH',
        'SHELL', 'IFS', 'ENV', 'BASH_ENV',
        'PATH',
    ];

    public function installDependencies(Site $site, DeployLog $log): void
    {
        $repoPath = $site->repo_path;

        if (! File::exists("{$repoPath}/package.json")) {
            $log->appendLog('  No package.json found, skipping npm install.');

            return;
        }

        $installCommand = $this->dependencyInstallCommand($repoPath);
        $result = $this->runCommand(
            $installCommand,
            $repoPath,
            $site,
            timeout: 180,
            envOverrides: [
                'NODE_ENV' => 'development',
                'NPM_CONFIG_PRODUCTION' => 'false',
                'npm_config_production' => 'false',
            ],
        );

        $this->appendCommandResult($log, '  Dependencies', $result);

        if (! $result['success']) {
            throw new \RuntimeException("Dependency installation failed: {$result['output']}");
        }
    }

    public function runBuild(Site $site, DeployLog $log): void
    {
        $buildCommand = $this->resolveBuildCommand($site);

        if (empty($buildCommand)) {
            $log->appendLog('  No build command configured, skipping build.');

            return;
        }

        $this->validateBuildCommand($buildCommand);

        $result = $this->runCommand(
            $buildCommand,
            $site->repo_path,
            $site,
            timeout: config('platform.deploy.build_timeout_seconds', 300),
        );

        $this->appendCommandResult($log, '  Build', $result);

        if (! $result['success']) {
            throw new \RuntimeException("Build failed: {$result['output']}");
        }
    }

    /**
     * @return array{success: bool, command: string, output: string, summary: string}
     */
    public function runCommand(
        string $command,
        string $cwd,
        Site $site,
        int $timeout = 120,
        array $envOverrides = [],
    ): array {
        $nodeBinPath = str_replace('\\', '/', "{$cwd}/node_modules/.bin");
        $systemPath = getenv('PATH') ?: ($_SERVER['PATH'] ?? '');

        $siteEnv = array_filter(
            (array) ($site->env_variables ?? []),
            fn (string $key) => ! in_array(strtoupper($key), self::DANGEROUS_ENV_VARS, true),
            ARRAY_FILTER_USE_KEY
        );

        $env = array_merge(
            [
                'NODE_ENV' => 'production',
                'PATH' => $nodeBinPath.PATH_SEPARATOR.$systemPath,
            ],
            $siteEnv,
            $this->buildToolCacheEnv($site),
            $envOverrides,
        );

        $nodeVersion = trim((string) ($site->node_version ?? '20'));
        $this->validateNodeVersion($nodeVersion);

        $nvmPrefix = "export NVM_DIR=\"\$HOME/.nvm\" && [ -s \"\$NVM_DIR/nvm.sh\" ] && . \"\$NVM_DIR/nvm.sh\" && nvm use {$nodeVersion} 2>/dev/null;";
        $fullCommand = "{$nvmPrefix} {$command}";

        $result = Process::timeout($timeout)
            ->path($cwd)
            ->env($env)
            ->run(['bash', '-c', $fullCommand]);

        $rawOutput = trim($result->output()."\n".$result->errorOutput());
        $output = $this->scrubEnvValues($rawOutput, $siteEnv);

        return [
            'success' => $result->successful(),
            'command' => $command,
            'output' => $output,
            'summary' => $result->successful() ? 'OK' : 'FAILED (exit '.$result->exitCode().')',
        ];
    }

    public function resolveBuildCommand(Site $site): ?string
    {
        $buildCommand = trim((string) ($site->build_command ?? ''));

        if ($buildCommand === '') {
            $buildCommand = $this->inferDefaultBuildCommand($site);
        }

        if ($buildCommand === '') {
            return null;
        }

        $packagePath = "{$site->repo_path}/package.json";
        if (! File::exists($packagePath)) {
            return $buildCommand;
        }

        $packageJson = json_decode(File::get($packagePath), true);
        if (! is_array($packageJson)) {
            return $buildCommand;
        }

        $scripts = is_array($packageJson['scripts'] ?? null) ? $packageJson['scripts'] : [];
        $buildScript = trim((string) ($scripts['build'] ?? ''));
        $exportScript = trim((string) ($scripts['export'] ?? ''));
        $normalizedBuild = preg_replace('/\s+/', ' ', strtolower($buildCommand)) ?: strtolower($buildCommand);

        if (
            $buildScript !== '' &&
            (
                $buildCommand === $buildScript
                || preg_match('/^(?:npm run|corepack pnpm|corepack yarn|pnpm|yarn|bun run)\s+build$/', $normalizedBuild)
            )
        ) {
            return $this->packageManagerRun($site->repo_path, 'build');
        }

        if (
            $buildScript !== '' && $exportScript !== '' &&
            (
                $buildCommand === "{$buildScript} && {$exportScript}"
                || preg_match('/^(?:npm run|corepack pnpm|corepack yarn|pnpm|yarn|bun run)\s+build\s+&&\s+(?:npm run|corepack pnpm|corepack yarn|pnpm|yarn|bun run)\s+export$/', $normalizedBuild)
            )
        ) {
            return $this->packageManagerRun($site->repo_path, 'build')
                .' && '.
                $this->packageManagerRun($site->repo_path, 'export');
        }

        return $buildCommand;
    }

    public function packageManager(string $repoPath): string
    {
        return match (true) {
            File::exists("{$repoPath}/pnpm-lock.yaml") => 'pnpm',
            File::exists("{$repoPath}/yarn.lock") => 'yarn',
            File::exists("{$repoPath}/bun.lockb"), File::exists("{$repoPath}/bun.lock") => 'bun',
            File::exists("{$repoPath}/package-lock.json"), File::exists("{$repoPath}/npm-shrinkwrap.json") => 'npm',
            default => 'npm',
        };
    }

    /**
     * Reject build commands containing shell metacharacters that enable injection.
     */
    public function validateBuildCommand(string $command): void
    {
        if (preg_match('/[\r\n]/', $command)) {
            throw new \RuntimeException('Build command must not contain newline characters.');
        }

        if (preg_match('/[;|`$<>]/', $command)) {
            throw new \RuntimeException(
                "Build command contains a disallowed shell character (one of: ; | \` \$ < >). ".
                'Edit the build command in Site Settings to remove it.'
            );
        }
    }

    /**
     * Reject node_version values that could inject into the nvm shell prefix.
     */
    public function validateNodeVersion(string $version): void
    {
        if ($version === '') {
            return;
        }

        if (! preg_match('/^\d+(\.\d+){0,2}$|^lts\/[a-z]+$|^(current|node|stable)$/i', $version)) {
            throw new \RuntimeException(
                "Invalid Node.js version specifier [{$version}]. Allowed formats: '20', '18.12.1', 'lts/iron', 'current'."
            );
        }
    }

    /**
     * Replace user-supplied env variable values in build output with [REDACTED]
     * before the output is persisted in DeployLog.
     *
     * @param  array<string, string>  $envVars
     */
    public function scrubEnvValues(string $output, array $envVars): string
    {
        foreach ($envVars as $value) {
            $value = (string) $value;
            if (strlen($value) < 4) {
                continue;
            }
            $output = str_replace($value, '[REDACTED]', $output);
        }

        return $output;
    }

    private function inferDefaultBuildCommand(Site $site): string
    {
        $repoPath = $site->repo_path;

        if (! $repoPath || ! File::exists("{$repoPath}/package.json")) {
            return '';
        }

        $packageJson = json_decode(File::get("{$repoPath}/package.json"), true);
        if (! is_array($packageJson) || ! isset($packageJson['scripts']['build'])) {
            return '';
        }

        $buildableTypes = ['nextjs', 'nuxt', 'astro', 'react', 'vue', 'svelte', 'hugo', 'eleventy'];
        if (in_array($site->project_type, $buildableTypes, true)) {
            return 'npm run build';
        }

        return '';
    }

    private function packageManagerRun(string $repoPath, string $script): string
    {
        return match ($this->packageManager($repoPath)) {
            'pnpm' => "corepack pnpm {$script}",
            'yarn' => "corepack yarn {$script}",
            'bun' => "bun run {$script}",
            default => "npm run {$script}",
        };
    }

    private function dependencyInstallCommand(string $repoPath): string
    {
        return match ($this->packageManager($repoPath)) {
            'pnpm' => 'corepack pnpm install --frozen-lockfile --prod=false',
            'yarn' => 'corepack yarn install --frozen-lockfile --production=false',
            'bun' => 'bun install --frozen-lockfile',
            'npm' => File::exists("{$repoPath}/package-lock.json") || File::exists("{$repoPath}/npm-shrinkwrap.json")
                ? 'npm ci --include=dev'
                : 'npm install',
            default => 'npm install',
        };
    }

    /**
     * @return array<string, string>
     */
    private function buildToolCacheEnv(Site $site): array
    {
        $slug = $site->slug !== '' ? $site->slug : 'site';
        $cacheBase = storage_path('app/build-cache/'.$slug);

        File::ensureDirectoryExists($cacheBase.'/npm', 0775, true);
        File::ensureDirectoryExists($cacheBase.'/yarn', 0775, true);
        File::ensureDirectoryExists($cacheBase.'/corepack', 0775, true);
        File::ensureDirectoryExists($cacheBase.'/xdg', 0775, true);

        return [
            'NPM_CONFIG_CACHE' => $cacheBase.'/npm',
            'npm_config_cache' => $cacheBase.'/npm',
            'YARN_CACHE_FOLDER' => $cacheBase.'/yarn',
            'COREPACK_HOME' => $cacheBase.'/corepack',
            'XDG_CACHE_HOME' => $cacheBase.'/xdg',
            'NPM_CONFIG_UPDATE_NOTIFIER' => 'false',
            'npm_config_update_notifier' => 'false',
        ];
    }

    private function appendCommandResult(DeployLog $log, string $label, array $result): void
    {
        $log->appendLog("{$label}: {$result['summary']} ({$result['command']})");

        if (! empty($result['output'])) {
            $log->appendLog($this->indentMultilineOutput($result['output']));
        }
    }

    private function indentMultilineOutput(string $output): string
    {
        return collect(preg_split("/\r\n|\n|\r/", trim($output)) ?: [])
            ->filter(fn (?string $line) => $line !== null && $line !== '')
            ->map(fn (string $line) => '    '.$line)
            ->take(80)
            ->implode("\n");
    }
}
