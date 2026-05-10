<?php

namespace Tests\Feature;

use App\Events\DeployFailed;
use App\Events\SiteDeployed;
use App\Events\SiteSynced;
use App\Jobs\AnalyzeSeoJob;
use App\Jobs\ParseSiteJob;
use App\Jobs\VerifyRuntimeHealthJob;
use App\Listeners\DeployOnSync;
use App\Models\DeploymentTarget;
use App\Listeners\NotifyOnDeployFailed;
use App\Listeners\ParseSiteOnDeploy;
use App\Listeners\ParseSiteOnSync;
use App\Listeners\VerifyRuntimeOnDeploy;
use App\Models\DeployLog;
use App\Models\DeploymentRelease;
use App\Models\Notification;
use App\Models\Site;
use App\Models\User;
use App\Services\DeployDispatcher;
use App\Services\SiteRuntimeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

class EventListenerChainTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(): User
    {
        return User::create([
            'name' => 'U', 'email' => 'elc-'.uniqid().'@x.com',
            'password' => Hash::make('secret'), 'role' => 'admin',
        ]);
    }

    private function makeSite(array $attrs = []): Site
    {
        return Site::create(array_merge([
            'user_id' => $this->makeUser()->id, 'name' => 'S',
            'slug' => 'elc-'.uniqid(), 'branch' => 'main',
            'project_type' => 'static_html', 'deploy_on_webhook' => false,
        ], $attrs));
    }

    // ── SiteSynced ────────────────────────────────────────────────────────

    public function test_site_synced_with_changes_dispatches_parse_job(): void
    {
        Queue::fake();
        $site = $this->makeSite();

        $listener = new ParseSiteOnSync;
        $listener->handle(new SiteSynced($site, hasChanges: true));

        Queue::assertPushed(ParseSiteJob::class, fn ($job) => $job->site->id === $site->id);
    }

    public function test_site_synced_without_changes_does_not_dispatch_parse_job(): void
    {
        Queue::fake();
        $site = $this->makeSite();

        $listener = new ParseSiteOnSync;
        $listener->handle(new SiteSynced($site, hasChanges: false));

        Queue::assertNotPushed(ParseSiteJob::class);
    }

    public function test_site_synced_with_deploy_on_webhook_dispatches_deploy(): void
    {
        Queue::fake();
        $site = $this->makeSite(['deploy_on_webhook' => true]);

        $dispatcher = Mockery::mock(DeployDispatcher::class);
        $dispatcher->shouldReceive('dispatch')->once()->andReturn(true);
        $this->app->instance(DeployDispatcher::class, $dispatcher);

        $listener = new DeployOnSync;
        $listener->handle(new SiteSynced($site, hasChanges: true));
    }

    public function test_site_synced_without_deploy_on_webhook_does_not_deploy(): void
    {
        Queue::fake();
        $site = $this->makeSite(['deploy_on_webhook' => false]);

        $dispatcher = Mockery::mock(DeployDispatcher::class);
        $dispatcher->shouldNotReceive('dispatch');
        $this->app->instance(DeployDispatcher::class, $dispatcher);

        $listener = new DeployOnSync;
        $listener->handle(new SiteSynced($site, hasChanges: true));
    }

    public function test_site_synced_with_no_changes_does_not_deploy_even_if_flag_is_on(): void
    {
        Queue::fake();
        $site = $this->makeSite(['deploy_on_webhook' => true]);

        $dispatcher = Mockery::mock(DeployDispatcher::class);
        $dispatcher->shouldNotReceive('dispatch');
        $this->app->instance(DeployDispatcher::class, $dispatcher);

        $listener = new DeployOnSync;
        $listener->handle(new SiteSynced($site, hasChanges: false));
    }

    private function makeTarget(Site $site): DeploymentTarget
    {
        return DeploymentTarget::firstOrCreate(
            ['site_id' => $site->id, 'environment' => 'production'],
            ['release_strategy' => 'symlink', 'is_active' => true]
        );
    }

    private function makeRelease(Site $site, DeployLog $log): DeploymentRelease
    {
        return DeploymentRelease::create([
            'site_id' => $site->id,
            'deployment_target_id' => $this->makeTarget($site)->id,
            'deploy_log_id' => $log->id,
            'source_branch' => 'main',
            'status' => 'active',
            'is_current' => true,
        ]);
    }

    // ── SiteDeployed ──────────────────────────────────────────────────────

    public function test_site_deployed_dispatches_parse_job(): void
    {
        Queue::fake();
        $site = $this->makeSite();
        $log = DeployLog::create(['site_id' => $site->id, 'status' => 'success', 'triggered_by' => 'test', 'created_at' => now()]);
        $release = $this->makeRelease($site, $log);

        $listener = new ParseSiteOnDeploy;
        $listener->handle(new SiteDeployed($site, $log, $release));

        Queue::assertPushed(ParseSiteJob::class, fn ($job) => $job->site->id === $site->id);
    }

    public function test_site_deployed_for_static_site_does_not_dispatch_health_job(): void
    {
        Queue::fake();
        $site = $this->makeSite(['project_type' => 'static_html']);
        $log = DeployLog::create(['site_id' => $site->id, 'status' => 'success', 'triggered_by' => 'test', 'created_at' => now()]);
        $release = $this->makeRelease($site, $log);

        $runtime = Mockery::mock(SiteRuntimeService::class);
        $runtime->shouldReceive('usesRuntimeServer')->once()->andReturn(false);
        $this->app->instance(SiteRuntimeService::class, $runtime);

        $listener = new VerifyRuntimeOnDeploy;
        $listener->handle(new SiteDeployed($site, $log, $release));

        Queue::assertNotPushed(VerifyRuntimeHealthJob::class);
    }

    // ── DeployFailed ──────────────────────────────────────────────────────

    public function test_deploy_failed_creates_dashboard_notification(): void
    {
        $site = $this->makeSite();
        $log = DeployLog::create(['site_id' => $site->id, 'status' => 'failed', 'triggered_by' => 'test', 'created_at' => now(),
        ]);

        $listener = new NotifyOnDeployFailed;
        $listener->handle(new DeployFailed($site, $log, 'Build timed out'));

        $this->assertDatabaseHas('notifications', [
            'site_id' => $site->id,
            'type' => 'deploy_failed',
        ]);

        $notification = Notification::where('site_id', $site->id)->first();
        $this->assertStringContainsString('Build timed out', $notification->body);
    }

    public function test_deploy_failed_notification_truncates_long_error_messages(): void
    {
        $site = $this->makeSite();

        $listener = new NotifyOnDeployFailed;
        $listener->handle(new DeployFailed($site, null, str_repeat('X', 1000)));

        $notification = Notification::where('site_id', $site->id)->first();
        $this->assertNotNull($notification);
        $this->assertLessThanOrEqual(510, strlen($notification->body ?? ''));
    }

    // ── Listener registration ──────────────────────────────────────────────

    public function test_all_listeners_are_registered_for_their_events(): void
    {
        $dispatcher = app('events');

        $this->assertTrue($dispatcher->hasListeners(SiteSynced::class));
        $this->assertTrue($dispatcher->hasListeners(SiteDeployed::class));
        $this->assertTrue($dispatcher->hasListeners(DeployFailed::class));
    }
}
