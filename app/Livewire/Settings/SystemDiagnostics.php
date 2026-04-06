<?php

namespace App\Livewire\Settings;

use App\Models\Site;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;

class SystemDiagnostics extends Component
{
    public function render()
    {
        $snapshot = $this->buildSnapshot();

        return view('livewire.settings.system-diagnostics', $snapshot);
    }

    private function buildSnapshot(): array
    {
        $expectedQueues = ['default', 'git', 'parsing', 'deploy', 'monitoring'];
        $queueConnection = (string) config('queue.default', 'sync');
        $queueDriver = (string) config("queue.connections.{$queueConnection}.driver", $queueConnection);
        $appEnvironment = app()->environment();
        $configuredQueues = $this->configuredHorizonQueues($appEnvironment);
        $redisStatus = $this->redisStatus();
        $queueStats = $this->queueStats($queueConnection, $queueDriver, $expectedQueues);
        $failureMetrics = $this->failureMetrics();
        $recentFailures = $failureMetrics['recent'];
        $stuckSites = $this->stuckSites();

        $totalPendingJobs = collect($queueStats)->sum(fn (array $queue) => $queue['pending'] ?? 0);
        $recentFailureCount = $failureMetrics['count_24h'];
        $missingQueues = array_values(array_diff($expectedQueues, $configuredQueues));
        $workerHealth = $this->workerHealth($queueDriver, $redisStatus, $totalPendingJobs, $recentFailureCount, $stuckSites);
        $checks = $this->checks(
            queueDriver: $queueDriver,
            redisStatus: $redisStatus,
            missingQueues: $missingQueues,
            totalPendingJobs: $totalPendingJobs,
            recentFailureCount: $recentFailureCount,
            stuckSites: $stuckSites,
        );

        return [
            'systemInfo' => [
                'app_environment' => $appEnvironment,
                'queue_connection' => $queueConnection,
                'queue_driver' => $queueDriver,
                'configured_queues' => $configuredQueues,
                'missing_queues' => $missingQueues,
                'redis_status' => $redisStatus,
            ],
            'workerHealth' => $workerHealth,
            'queueStats' => $queueStats,
            'recentFailures' => $recentFailures,
            'stuckSites' => $stuckSites,
            'checks' => $checks,
            'recommendations' => $this->recommendations(
                queueDriver: $queueDriver,
                redisStatus: $redisStatus,
                missingQueues: $missingQueues,
                totalPendingJobs: $totalPendingJobs,
                recentFailureCount: $recentFailureCount,
                stuckSites: $stuckSites,
            ),
            'summary' => [
                'pending_jobs' => $totalPendingJobs,
                'failed_jobs' => $recentFailureCount,
                'stuck_sites' => count($stuckSites['setup']) + count($stuckSites['deploy']),
            ],
        ];
    }

    private function configuredHorizonQueues(string $environment): array
    {
        $supervisors = config("horizon.environments.{$environment}");

        if (empty($supervisors) || ! is_array($supervisors)) {
            $supervisors = config('horizon.defaults', []);
        }

        return collect($supervisors)
            ->pluck('queue')
            ->flatten()
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function redisStatus(): array
    {
        try {
            $connection = (string) config('horizon.use', 'default');
            $response = Redis::connection($connection)->ping();

            return [
                'ok' => true,
                'connection' => $connection,
                'message' => is_string($response) ? $response : 'PONG',
            ];
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'connection' => (string) config('horizon.use', 'default'),
                'message' => $e->getMessage(),
            ];
        }
    }

    private function queueStats(string $queueConnection, string $queueDriver, array $queues): array
    {
        return collect($queues)
            ->map(function (string $queue) use ($queueConnection, $queueDriver) {
                try {
                    $pending = Queue::connection($queueConnection)->size($queue);
                } catch (\Throwable $e) {
                    return [
                        'name' => $queue,
                        'pending' => null,
                        'oldest_wait' => null,
                        'error' => $e->getMessage(),
                    ];
                }

                return [
                    'name' => $queue,
                    'pending' => $pending,
                    'oldest_wait' => $this->oldestWaitForQueue($queueDriver, $queue),
                    'error' => null,
                ];
            })
            ->all();
    }

    private function oldestWaitForQueue(string $queueDriver, string $queue): ?string
    {
        if ($queueDriver !== 'database') {
            return null;
        }

        try {
            if (! Schema::hasTable('jobs')) {
                return null;
            }

            $availableAt = DB::table('jobs')
                ->where('queue', $queue)
                ->whereNull('reserved_at')
                ->min('available_at');

            if (! $availableAt) {
                return null;
            }

            $seconds = now()->diffInSeconds(Carbon::createFromTimestamp((int) $availableAt));

            if ($seconds < 60) {
                return "{$seconds}s";
            }

            if ($seconds < 3600) {
                return floor($seconds / 60) . 'm';
            }

            return floor($seconds / 3600) . 'h';
        } catch (\Throwable) {
            return null;
        }
    }

    private function failureMetrics(): array
    {
        try {
            if (! Schema::hasTable('failed_jobs')) {
                return [
                    'count_24h' => 0,
                    'recent' => [],
                ];
            }

            $recent = DB::table('failed_jobs')
                ->orderByDesc('failed_at')
                ->limit(5)
                ->get()
                ->map(function ($job) {
                    return [
                        'id' => $job->id,
                        'queue' => $job->queue,
                        'name' => $this->jobNameFromPayload($job->payload),
                        'summary' => $this->exceptionSummary($job->exception),
                        'failed_at' => Carbon::parse($job->failed_at),
                    ];
                })
                ->all();

            $count24h = DB::table('failed_jobs')
                ->where('failed_at', '>=', now()->subDay())
                ->count();

            return [
                'count_24h' => $count24h,
                'recent' => $recent,
            ];
        } catch (\Throwable) {
            return [
                'count_24h' => 0,
                'recent' => [],
            ];
        }
    }

    private function stuckSites(): array
    {
        $setupThreshold = now()->subMinutes(5);
        $deployThreshold = now()->subMinutes(10);

        try {
            $setup = Site::query()
                ->whereNull('last_synced_at')
                ->where('created_at', '<=', $setupThreshold)
                ->latest('created_at')
                ->limit(5)
                ->get()
                ->map(fn (Site $site) => [
                    'id' => $site->id,
                    'name' => $site->name,
                    'project_type' => $site->project_type,
                    'age' => $site->created_at?->diffForHumans() ?? 'unknown',
                    'reason' => 'Created but never finished the initial clone/parse step.',
                ])
                ->all();

            $deploy = Site::query()
                ->whereIn('deploy_status', ['building', 'deploying'])
                ->where('updated_at', '<=', $deployThreshold)
                ->latest('updated_at')
                ->limit(5)
                ->get()
                ->map(fn (Site $site) => [
                    'id' => $site->id,
                    'name' => $site->name,
                    'project_type' => $site->project_type,
                    'age' => $site->updated_at?->diffForHumans() ?? 'unknown',
                    'reason' => 'Status has not changed for more than 10 minutes.',
                ])
                ->all();

            return ['setup' => $setup, 'deploy' => $deploy];
        } catch (\Throwable) {
            return ['setup' => [], 'deploy' => []];
        }
    }

    private function workerHealth(
        string $queueDriver,
        array $redisStatus,
        int $totalPendingJobs,
        int $recentFailureCount,
        array $stuckSites,
    ): array {
        $stuckCount = count($stuckSites['setup']) + count($stuckSites['deploy']);

        if ($queueDriver !== 'redis') {
            return [
                'status' => 'fail',
                'label' => 'Misconfigured',
                'message' => 'The app is not using the Redis queue driver that Horizon expects.',
            ];
        }

        if (! $redisStatus['ok']) {
            return [
                'status' => 'fail',
                'label' => 'Redis Offline',
                'message' => 'The app could not reach the Redis connection Horizon relies on.',
            ];
        }

        if ($totalPendingJobs > 0 && $stuckCount > 0) {
            return [
                'status' => 'fail',
                'label' => 'Workers Likely Offline',
                'message' => 'Jobs are queued and site records look stalled, which usually means Horizon is not consuming work.',
            ];
        }

        if ($recentFailureCount > 0) {
            return [
                'status' => 'warn',
                'label' => 'Workers Active, Jobs Failing',
                'message' => 'The queue is moving, but recent jobs have crashed and need review.',
            ];
        }

        if ($totalPendingJobs > 0) {
            return [
                'status' => 'warn',
                'label' => 'Backlog Present',
                'message' => 'Jobs are queued. That can be normal briefly, but the backlog should clear quickly.',
            ];
        }

        return [
            'status' => 'pass',
            'label' => 'Healthy',
            'message' => 'Queue configuration looks aligned and there is no visible backlog right now.',
        ];
    }

    private function checks(
        string $queueDriver,
        array $redisStatus,
        array $missingQueues,
        int $totalPendingJobs,
        int $recentFailureCount,
        array $stuckSites,
    ): array {
        $stuckCount = count($stuckSites['setup']) + count($stuckSites['deploy']);

        return [
            [
                'title' => 'Queue driver matches Horizon',
                'status' => $queueDriver === 'redis' ? 'pass' : 'fail',
                'message' => $queueDriver === 'redis'
                    ? 'Jobs are configured to use Redis, which matches the Horizon worker setup.'
                    : "The app is using the {$queueDriver} queue driver while Horizon is configured for Redis.",
            ],
            [
                'title' => 'Redis connectivity',
                'status' => $redisStatus['ok'] ? 'pass' : 'fail',
                'message' => $redisStatus['ok']
                    ? "Connected to Redis via the {$redisStatus['connection']} connection."
                    : "Redis connection failed: {$redisStatus['message']}",
            ],
            [
                'title' => 'Horizon queue coverage',
                'status' => empty($missingQueues) ? 'pass' : 'fail',
                'message' => empty($missingQueues)
                    ? 'The configured supervisors cover the queues pixelkraft dispatches to.'
                    : 'Missing Horizon queues: ' . implode(', ', $missingQueues),
            ],
            [
                'title' => 'Pending backlog',
                'status' => $totalPendingJobs === 0 ? 'pass' : ($stuckCount > 0 ? 'fail' : 'warn'),
                'message' => $totalPendingJobs === 0
                    ? 'No queued jobs are waiting right now.'
                    : "{$totalPendingJobs} job(s) are currently pending across the configured queues.",
            ],
            [
                'title' => 'Recent failures',
                'status' => $recentFailureCount === 0 ? 'pass' : ($recentFailureCount >= 3 ? 'fail' : 'warn'),
                'message' => $recentFailureCount === 0
                    ? 'No recent failed jobs were found.'
                    : "{$recentFailureCount} recent failed job(s) were found in the failed_jobs table.",
            ],
            [
                'title' => 'Stalled site workflows',
                'status' => $stuckCount === 0 ? 'pass' : 'fail',
                'message' => $stuckCount === 0
                    ? 'No sites currently look stuck during setup or deploy.'
                    : "{$stuckCount} site workflow(s) look stalled and should be checked below.",
            ],
        ];
    }

    private function recommendations(
        string $queueDriver,
        array $redisStatus,
        array $missingQueues,
        int $totalPendingJobs,
        int $recentFailureCount,
        array $stuckSites,
    ): array {
        $recommendations = collect();

        if ($queueDriver !== 'redis') {
            $recommendations->push('Set QUEUE_CONNECTION=redis in the app environment, then clear and rebuild config cache.');
        }

        if (! $redisStatus['ok']) {
            $recommendations->push('Verify REDIS_HOST, REDIS_PORT, REDIS_PASSWORD, and the Redis service itself before retrying jobs.');
        }

        if (! empty($missingQueues)) {
            $recommendations->push('Restart Horizon after confirming supervisors listen to default, git, parsing, deploy, and monitoring queues.');
        }

        if ($totalPendingJobs > 0 && (count($stuckSites['setup']) + count($stuckSites['deploy'])) > 0) {
            $recommendations->push('If jobs stay queued for more than a minute, restart Horizon or your queue worker process on the server.');
        }

        if ($recentFailureCount > 0) {
            $recommendations->push('Open the failed job summaries below and compare them with the matching site deploy log to isolate the crash point.');
        }

        if ($recommendations->isEmpty()) {
            $recommendations->push('The queue system currently looks healthy. If a single site still stalls, inspect that site record and deploy log next.');
        }

        return $recommendations->all();
    }

    private function jobNameFromPayload(string $payload): string
    {
        $decoded = json_decode($payload, true);

        if (! is_array($decoded)) {
            return 'Unknown job';
        }

        return $decoded['displayName']
            ?? $decoded['data']['commandName']
            ?? 'Unknown job';
    }

    private function exceptionSummary(string $exception): string
    {
        $line = trim(strtok($exception, "\n")) ?: 'No exception details available.';

        return mb_strimwidth($line, 0, 180, '...');
    }
}
