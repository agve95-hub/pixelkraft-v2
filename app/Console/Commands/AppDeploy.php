<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Laravel\Horizon\Contracts\MasterSupervisorRepository;

/**
 * Safe production deployment sequence for the platform platform itself.
 *
 * Running `php artisan migrate` directly in production while the app and
 * Horizon are live risks two classes of failure:
 *
 *  1. A request hits code that references a column/table that doesn't exist
 *     yet (or was just dropped), causing 500 errors mid-deploy.
 *  2. A queued job deserializes a model with stale column assumptions and
 *     either crashes the worker or, worse, silently corrupts data.
 *
 * This command prevents both by following the sequence:
 *  (a) Enable maintenance mode so HTTP traffic returns 503 immediately.
 *  (b) Pause Horizon so no new jobs are dispatched to workers.
 *  (c) Run migrations.
 *  (d) Clear compiled views, config, route, and event caches.
 *  (e) Restart queue workers via the cache signal (workers pick this up
 *      and gracefully restart after finishing their current job).
 *  (f) Bring the app out of maintenance mode.
 *
 * Usage:
 *   php artisan app:deploy
 *   php artisan app:deploy --skip-migrate   # cache-clear + restart only
 */
class AppDeploy extends Command
{
    protected $signature = 'app:deploy
                            {--skip-migrate : Skip running migrations (useful for cache-only refreshes)}
                            {--secret= : Maintenance mode bypass secret (forwarded to `down`)}';

    protected $description = 'Zero-downtime platform deploy: maintenance mode → migrate → cache clear → bring up';

    public function handle(): int
    {
        $this->enableMaintenanceMode();

        try {
            $this->pauseHorizon();

            if (! $this->option('skip-migrate')) {
                $this->runMigrations();
            }

            $this->clearCaches();
            $this->restartWorkers();
        } finally {
            // Always bring the app back up, even if a step throws.
            $this->disableMaintenanceMode();
        }

        $this->info('Deploy complete.');

        return self::SUCCESS;
    }

    private function enableMaintenanceMode(): void
    {
        $this->info('Enabling maintenance mode...');

        $args = ['--refresh' => true];

        if ($secret = $this->option('secret')) {
            $args['--secret'] = $secret;
        }

        $this->call('down', $args);
    }

    private function disableMaintenanceMode(): void
    {
        $this->info('Disabling maintenance mode...');
        $this->call('up');
    }

    private function pauseHorizon(): void
    {
        // Horizon is optional — skip gracefully if the package is not installed
        // or its supervisor repository is unavailable (e.g. Redis is down).
        if (! interface_exists(MasterSupervisorRepository::class)) {
            $this->warn('Horizon not found; skipping queue pause.');

            return;
        }

        try {
            $this->info('Pausing Horizon...');
            $this->call('horizon:pause');
        } catch (\Throwable $e) {
            $this->warn("Could not pause Horizon: {$e->getMessage()} — continuing.");
        }
    }

    private function runMigrations(): void
    {
        $this->info('Running migrations...');
        $exitCode = $this->call('migrate', ['--force' => true]);

        if ($exitCode !== self::SUCCESS) {
            throw new \RuntimeException('Migrations failed — aborting deploy.');
        }
    }

    private function clearCaches(): void
    {
        $this->info('Clearing caches...');
        $this->call('view:clear');
        $this->call('config:clear');
        $this->call('route:clear');
        $this->call('event:clear');
    }

    private function restartWorkers(): void
    {
        $this->info('Signalling workers to restart...');
        $this->call('queue:restart');

        if (interface_exists(MasterSupervisorRepository::class)) {
            try {
                $this->call('horizon:continue');
            } catch (\Throwable $e) {
                $this->warn("Could not resume Horizon: {$e->getMessage()} — start it manually.");
            }
        }
    }
}
