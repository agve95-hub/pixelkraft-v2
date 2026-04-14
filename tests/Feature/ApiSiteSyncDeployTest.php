<?php

namespace Tests\Feature;

use App\Jobs\CloneRepoJob;
use App\Jobs\DeploySiteJob;
use App\Jobs\ParseSiteJob;
use App\Models\DeployLog;
use App\Models\Site;
use App\Models\User;
use App\Services\GitSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Tests\TestCase;

class ApiSiteSyncDeployTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_sync_dispatches_clone_when_repo_not_cloned(): void
    {
        Queue::fake();

        $user = User::create([
            'name' => 'Dev',
            'email' => 'dev-sync@example.com',
            'password' => Hash::make('password'),
            'role' => 'editor',
        ]);

        $site = Site::create([
            'user_id' => $user->id,
            'name' => 'Sync Me',
            'slug' => 'sync-me-api',
            'repo_url' => 'https://github.com/example/sm.git',
            'branch' => 'main',
            'project_type' => 'static_html',
        ]);

        $mock = Mockery::mock(GitSyncService::class);
        $mock->shouldReceive('isCloned')->once()->with(Mockery::on(fn ($s) => $s->is($site)))->andReturn(false);
        $this->app->instance(GitSyncService::class, $mock);

        Sanctum::actingAs($user, ['*']);

        $this->postJson("/api/v1/sites/{$site->id}/sync")
            ->assertOk()
            ->assertJsonPath('status', 'dispatched');

        Queue::assertPushed(CloneRepoJob::class, fn (CloneRepoJob $job) => $job->site->is($site));
        Queue::assertNotPushed(ParseSiteJob::class);
    }

    public function test_sync_dispatches_parse_when_repo_already_cloned(): void
    {
        Queue::fake();

        $user = User::create([
            'name' => 'Dev2',
            'email' => 'dev2-sync@example.com',
            'password' => Hash::make('password'),
            'role' => 'editor',
        ]);

        $site = Site::create([
            'user_id' => $user->id,
            'name' => 'Parse Me',
            'slug' => 'parse-me-api',
            'repo_url' => 'https://github.com/example/pm.git',
            'branch' => 'main',
            'project_type' => 'static_html',
        ]);

        $mock = Mockery::mock(GitSyncService::class);
        $mock->shouldReceive('isCloned')->once()->andReturn(true);
        $this->app->instance(GitSyncService::class, $mock);

        Sanctum::actingAs($user, ['*']);

        $this->postJson("/api/v1/sites/{$site->id}/sync")->assertOk();

        Queue::assertPushed(ParseSiteJob::class, fn (ParseSiteJob $job) => $job->site->is($site));
        Queue::assertNotPushed(CloneRepoJob::class);
    }

    public function test_deploy_dispatches_deploy_site_job(): void
    {
        Queue::fake();

        $user = User::create([
            'name' => 'Ops',
            'email' => 'ops-deploy@example.com',
            'password' => Hash::make('password'),
            'role' => 'editor',
        ]);

        $site = Site::create([
            'user_id' => $user->id,
            'name' => 'Deploy Me',
            'slug' => 'deploy-me-api',
            'repo_url' => 'https://github.com/example/dm.git',
            'branch' => 'main',
            'project_type' => 'static_html',
        ]);

        Sanctum::actingAs($user, ['*']);

        $this->postJson("/api/v1/sites/{$site->id}/deploy")
            ->assertOk()
            ->assertJsonPath('status', 'dispatched');

        Queue::assertPushed(DeploySiteJob::class, function (DeploySiteJob $job) use ($site) {
            return $job->site->is($site) && $job->triggeredBy === 'api';
        });
    }

    public function test_rollback_returns_400_when_deploy_log_has_no_snapshot(): void
    {
        $user = User::create([
            'name' => 'Ops2',
            'email' => 'ops2-rollback@example.com',
            'password' => Hash::make('password'),
            'role' => 'editor',
        ]);

        $site = Site::create([
            'user_id' => $user->id,
            'name' => 'Rollback Site',
            'slug' => 'rollback-site-api',
            'repo_url' => 'https://github.com/example/rs.git',
            'branch' => 'main',
            'project_type' => 'static_html',
        ]);

        $log = DeployLog::create([
            'site_id' => $site->id,
            'status' => 'success',
            'commit_sha' => 'abc1234',
            'commit_message' => 'Deploy',
            'snapshot_tag' => null,
            'triggered_by' => 'manual',
            'created_at' => now(),
        ]);

        Sanctum::actingAs($user, ['*']);

        $this->postJson("/api/v1/sites/{$site->id}/rollback/{$log->id}")
            ->assertStatus(400)
            ->assertJsonFragment(['error' => 'No snapshot available for this deploy']);
    }
}
