<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppDeployCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_deploy_runs_and_exits_successfully(): void
    {
        // The command runs the real migration (no-op on an already-migrated DB),
        // clears caches, and restarts workers — all safe in the test environment.
        $this->artisan('app:deploy')
            ->expectsOutputToContain('Enabling maintenance mode')
            ->expectsOutputToContain('Running migrations')
            ->expectsOutputToContain('Clearing caches')
            ->expectsOutputToContain('Signalling workers to restart')
            ->expectsOutputToContain('Disabling maintenance mode')
            ->expectsOutputToContain('Deploy complete.')
            ->assertExitCode(0);
    }

    public function test_deploy_skip_migrate_omits_migration_output(): void
    {
        $this->artisan('app:deploy --skip-migrate')
            ->expectsOutputToContain('Enabling maintenance mode')
            ->doesntExpectOutput('Running migrations')
            ->expectsOutputToContain('Clearing caches')
            ->expectsOutputToContain('Deploy complete.')
            ->assertExitCode(0);
    }

    public function test_maintenance_mode_is_disabled_after_successful_deploy(): void
    {
        $this->artisan('app:deploy')->assertExitCode(0);

        // After a successful deploy, the app should NOT be in maintenance mode.
        $this->assertFalse(app()->isDownForMaintenance());
    }
}
