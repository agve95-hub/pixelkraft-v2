<?php

namespace App\Services;

use App\Models\DeployLog;
use App\Models\Site;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;

class SiteRuntimeService
{
    /**
     * @var array<string, int|null>
     */
    private array $activePortCache = [];

    public function usesRuntimeServer(Site $site): bool
    {
        return $site->project_type === 'nextjs' && ! $this->usesStaticExport($site);
    }

    public function usesStaticExport(Site $site): bool
    {
        if ($site->project_type !== 'nextjs') {
            return false;
        }

        $configuredOutputDir = trim((string) $site->build_output_dir, '/');
        if ($configuredOutputDir !== '' && $configuredOutputDir !== '.next') {
            return true;
        }

        if ($this->nextConfigContains($site, 'output', 'export')) {
            return true;
        }

        $buildCommand = strtolower((string) ($site->build_command ?? ''));
        if (str_contains($buildCommand, 'next export')) {
            return true;
        }

        return false;
    }

    public function portFor(Site $site): int
    {
        $portStart = (int) config('pixelkraft.runtime.port_start', 4100);
        $portSpan = max(100, (int) config('pixelkraft.runtime.port_span', 2000));

        return $portStart + (abs(crc32((string) $site->id)) % $portSpan);
    }

    public function baseUrl(Site $site): string
    {
        return $this->urlForPort($this->portFor($site));
    }

    public function previewBaseUrl(Site $site): string
    {
        return $this->urlForPort($this->activePortFor($site) ?? $this->portFor($site));
    }

    public function fetch(Site $site, string $path = '/', array $query = [])
    {
        foreach ($this->candidatePorts($site) as $port) {
            try {
                $response = Http::timeout(15)
                    ->withOptions(['http_errors' => false])
                    ->get($this->urlForPort($port) . $this->normalizePath($path), $query);

                if ($response->status() > 0) {
                    return $response;
                }
            } catch (\Throwable) {
                continue;
            }
        }

        return null;
    }

    public function isReachable(Site $site, string $path = '/'): bool
    {
        $response = $this->fetch($site, $path);

        return $response && $response->status() > 0 && $response->status() < 500;
    }

    public function deploy(Site $site, DeployLog $log): void
    {
        $execution = $this->prepareExecution($site, $log);

        $this->stopShellProcess($site);
        $this->startShellProcess($site, $execution);
        $this->waitUntilHealthy($site, $log);
    }

    private function prepareExecution(Site $site, DeployLog $log): array
    {
        if ($this->hasStandaloneServer($site)) {
            $runtimeRoot = $this->runtimeRoot($site);
            $this->stageStandaloneRuntime($site, $runtimeRoot);
            $log->appendLog("  Prepared standalone runtime at {$runtimeRoot}");

            return [
                'working_dir' => $runtimeRoot,
                'command' => 'node server.js',
                'bin_dir' => null,
            ];
        }

        $log->appendLog('  Using Next.js runtime server from the cloned repository.');

        return [
            'working_dir' => $site->repo_path,
            'command' => 'next start -H ' . $this->host() . ' -p ' . $this->portFor($site),
            'bin_dir' => "{$site->repo_path}/node_modules/.bin",
        ];
    }

    private function stageStandaloneRuntime(Site $site, string $runtimeRoot): void
    {
        $repoPath = $site->repo_path;
        $standaloneRoot = "{$repoPath}/.next/standalone";

        File::deleteDirectory($runtimeRoot);
        File::ensureDirectoryExists(dirname($runtimeRoot), 0755, true);
        File::copyDirectory($standaloneRoot, $runtimeRoot);

        if (File::isDirectory("{$repoPath}/.next/static")) {
            File::ensureDirectoryExists("{$runtimeRoot}/.next", 0755, true);
            File::copyDirectory("{$repoPath}/.next/static", "{$runtimeRoot}/.next/static");
        }

        if (File::isDirectory("{$repoPath}/public")) {
            File::copyDirectory("{$repoPath}/public", "{$runtimeRoot}/public");
        }
    }

    private function startShellProcess(Site $site, array $execution): void
    {
        $logFile = $this->logFile($site);
        $pidFile = $this->pidFile($site);

        File::ensureDirectoryExists(dirname($logFile), 0755, true);
        File::ensureDirectoryExists(dirname($pidFile), 0755, true);

        $nodeVersion = $site->node_version ?? '20';
        $host = $this->host();
        $port = $this->portFor($site);
        $script = "cd " . escapeshellarg($execution['working_dir']) . " && "
            . "export PORT={$port} HOSTNAME=" . escapeshellarg($host) . " NODE_ENV=production NEXT_TELEMETRY_DISABLED=1 && "
            . $this->nvmBootstrap($nodeVersion);

        if (! empty($execution['bin_dir'])) {
            $script .= 'export PATH="' . addcslashes($execution['bin_dir'], '"\\') . ':$PATH" && ';
        }

        $script .= 'nohup ' . $execution['command']
            . ' >> ' . escapeshellarg($logFile)
            . ' 2>&1 & echo $! > ' . escapeshellarg($pidFile);

        $result = Process::timeout(20)
            ->path($execution['working_dir'])
            ->run(['bash', '-lc', $script]);

        if (! $result->successful()) {
            throw new \RuntimeException('Failed to start runtime process: ' . trim($result->errorOutput() ?: $result->output()));
        }
    }

    private function stopShellProcess(Site $site): void
    {
        $pidFile = $this->pidFile($site);

        if (! File::exists($pidFile)) {
            return;
        }

        $script = 'if [ -f ' . escapeshellarg($pidFile) . ' ]; then '
            . 'PID=$(cat ' . escapeshellarg($pidFile) . '); '
            . 'if kill -0 "$PID" 2>/dev/null; then kill "$PID" || true; sleep 1; fi; '
            . 'rm -f ' . escapeshellarg($pidFile) . '; '
            . 'fi';

        Process::timeout(10)
            ->path(dirname($pidFile))
            ->run(['bash', '-lc', $script]);

        unset($this->activePortCache[(string) $site->id]);
    }

    private function waitUntilHealthy(Site $site, DeployLog $log): void
    {
        $timeout = max(5, (int) config('pixelkraft.runtime.startup_timeout_seconds', 30));
        $deadline = microtime(true) + $timeout;
        $desiredPort = $this->portFor($site);

        while (microtime(true) < $deadline) {
            if ($this->isReachableOnPort($desiredPort, '/')) {
                unset($this->activePortCache[(string) $site->id]);
                $log->appendLog('  Runtime server is responding on ' . $this->baseUrl($site));
                return;
            }

            usleep(500000);
        }

        $logFile = $this->logFile($site);
        $tail = File::exists($logFile) ? implode("\n", array_slice(file($logFile, FILE_IGNORE_NEW_LINES), -20)) : 'No runtime log captured.';

        throw new \RuntimeException("Runtime server did not become healthy. Recent runtime log:\n{$tail}");
    }

    private function runtimeRoot(Site $site): string
    {
        return rtrim((string) config('pixelkraft.runtime.storage_path', storage_path('app/runtime-sites')), '/')
            . '/' . $site->slug;
    }

    private function pidFile(Site $site): string
    {
        return rtrim((string) config('pixelkraft.runtime.pid_path', storage_path('app/runtime-pids')), '/')
            . '/' . $site->slug . '.pid';
    }

    private function logFile(Site $site): string
    {
        return rtrim((string) config('pixelkraft.runtime.log_path', storage_path('logs/runtime-sites')), '/')
            . '/' . $site->slug . '.log';
    }

    private function hasStandaloneServer(Site $site): bool
    {
        return File::exists("{$site->repo_path}/.next/standalone/server.js");
    }

    private function nextConfigContains(Site $site, string $key, string $expectedValue): bool
    {
        foreach (['next.config.js', 'next.config.mjs', 'next.config.ts'] as $configFile) {
            $path = "{$site->repo_path}/{$configFile}";

            if (! File::exists($path)) {
                continue;
            }

            $config = File::get($path);
            if (preg_match('/' . preg_quote($key, '/') . '\s*:\s*[\'"]' . preg_quote($expectedValue, '/') . '[\'"]/', $config)) {
                return true;
            }
        }

        return false;
    }

    private function host(): string
    {
        return (string) config('pixelkraft.runtime.host', '127.0.0.1');
    }

    private function normalizePath(string $path): string
    {
        return '/' . ltrim($path, '/');
    }

    /**
     * @return list<int>
     */
    private function candidatePorts(Site $site): array
    {
        $ports = [];
        $activePort = $this->activePortFor($site);

        if ($activePort !== null) {
            $ports[] = $activePort;
        }

        $desiredPort = $this->portFor($site);

        if (! in_array($desiredPort, $ports, true)) {
            $ports[] = $desiredPort;
        }

        return $ports;
    }

    private function activePortFor(Site $site): ?int
    {
        $cacheKey = (string) $site->id;

        if (array_key_exists($cacheKey, $this->activePortCache)) {
            return $this->activePortCache[$cacheKey];
        }

        return $this->activePortCache[$cacheKey] = $this->discoverActivePort($site);
    }

    private function discoverActivePort(Site $site): ?int
    {
        $pidFile = $this->pidFile($site);

        if (! File::exists($pidFile)) {
            return null;
        }

        $pid = trim((string) File::get($pidFile));

        if ($pid === '' || ! ctype_digit($pid)) {
            return null;
        }

        $result = Process::timeout(5)->run([
            'bash',
            '-lc',
            'ps -p ' . escapeshellarg($pid) . ' -o args=',
        ]);

        if (! $result->successful()) {
            return null;
        }

        $command = trim($result->output());

        if ($command === '') {
            return null;
        }

        if (preg_match('/\b(?:PORT=|-p\s+)(\d{2,5})\b/', $command, $matches)) {
            return (int) $matches[1];
        }

        return null;
    }

    private function isReachableOnPort(int $port, string $path = '/'): bool
    {
        try {
            $response = Http::timeout(15)
                ->withOptions(['http_errors' => false])
                ->get($this->urlForPort($port) . $this->normalizePath($path));

            return $response->status() > 0 && $response->status() < 500;
        } catch (\Throwable) {
            return false;
        }
    }

    private function urlForPort(int $port): string
    {
        return 'http://' . $this->host() . ':' . $port;
    }

    private function nvmBootstrap(string $nodeVersion): string
    {
        return 'export NVM_DIR="$HOME/.nvm" && '
            . 'if [ -s "$NVM_DIR/nvm.sh" ]; then . "$NVM_DIR/nvm.sh"; nvm use '
            . escapeshellarg($nodeVersion)
            . ' >/dev/null 2>&1 || true; fi && ';
    }
}
