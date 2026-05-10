<?php

namespace Tests\Unit;

use App\Enums\DeployStatus;
use App\Events\DeployFailed;
use App\Models\DeployLog;
use App\Models\DeploymentRelease;
use App\Models\DeploymentTarget;
use App\Models\Site;
use App\Models\User;
use App\Services\ReleaseManager;
use App\Services\SiteProvisioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Mockery;
use Tests\TestCase;

class ReleaseManagerTest extends TestCase
{
    use RefreshDatabase;

    private ReleaseManager $manager;

    protected function setUp(): void
    {
        parent::setUp();

        // SiteProvisioningService::initializeSite() creates Linux filesystem paths
        // (/var/www/sites/...) that are invalid on Windows.  Stub the method to
        // only perform the DB work (deployment targets + tracking) and skip the
        // mkdir calls.
        $real = app(SiteProvisioningService::class);
        $stub = Mockery::mock(SiteProvisioningService::class);
        $stub->shouldReceive('initializeSite')->andReturnUsing(
            fn (Site $site) => $real->ensureDefaultDeploymentTargets($site)
        );
        $stub->shouldReceive('ensureDefaultDeploymentTargets')->passthru();
        $stub->shouldReceive('ensureDefaultTrackingInstallation')->passthru();
        $this->app->instance(SiteProvisioningService::class, $stub);

        $this->manager = app(ReleaseManager::class);
    }

    private function makeUser(): User
    {
        return User::create([
            'name' => 'U', 'email' => 'rm-'.uniqid().'@x.com',
            'password' => Hash::make('secret'), 'role' => 'admin',
        ]);
    }

    private function makeSite(User $user): Site
    {
        return Site::create([
            'user_id' => $user->id, 'name' => 'S',
            'slug' => 'rm-'.uniqid(), 'branch' => 'main',
            'project_type' => 'static_html', 'deploy_status' => DeployStatus::Draft,
        ]);
    }

    // ── begin() ───────────────────────────────────────────────────────────

    public function test_begin_creates_deploy_log_and_release(): void
    {
        $site = $this->makeSite($this->makeUser());

        $result = $this->manager->begin($site, 'test');

        $this->assertInstanceOf(DeployLog::class, $result['log']);
        $this->assertInstanceOf(DeploymentRelease::class, $result['release']);
        $this->assertInstanceOf(DeploymentTarget::class, $result['target']);

        $this->assertDatabaseHas('deploy_logs', ['site_id' => $site->id, 'status' => 'queued']);
        $this->assertDatabaseHas('deployment_releases', ['site_id' => $site->id, 'status' => 'building']);
    }

    public function test_begin_transitions_site_status_to_queued(): void
    {
        $site = $this->makeSite($this->makeUser());
        $this->manager->begin($site, 'manual');

        $this->assertSame(DeployStatus::Queued->value, $site->fresh()->deploy_status->value);
    }

    // ── fail() ────────────────────────────────────────────────────────────

    public function test_fail_marks_site_as_failed_and_fires_event(): void
    {
        Event::fake([DeployFailed::class]);

        $site = $this->makeSite($this->makeUser());
        ['log' => $log, 'release' => $release] = $this->manager->begin($site, 'test');

        $this->manager->fail($site, $log, $release, 'Something went wrong');

        $this->assertSame(DeployStatus::Failed->value, $site->fresh()->deploy_status->value);
        $this->assertSame('failed', $log->fresh()->status);
        $this->assertSame('failed', $release->fresh()->status);
        Event::assertDispatched(DeployFailed::class, fn ($e) => $e->error === 'Something went wrong');
    }

    public function test_fail_accepts_string_ids_for_log_and_release(): void
    {
        Event::fake();

        $site = $this->makeSite($this->makeUser());
        ['log' => $log, 'release' => $release] = $this->manager->begin($site, 'test');

        $this->manager->fail($site->id, $log->id, $release->id, 'error via IDs');

        $this->assertSame(DeployStatus::Failed->value, $site->fresh()->deploy_status->value);
    }

    public function test_fail_with_null_log_and_release_still_marks_site_failed(): void
    {
        Event::fake();

        $site = $this->makeSite($this->makeUser());
        $this->manager->begin($site, 'test');

        $this->manager->fail($site, null, null, 'orphaned failure');

        $this->assertSame(DeployStatus::Failed->value, $site->fresh()->deploy_status->value);
    }

    // ── pruneSnapshots() ─────────────────────────────────────────────────

    public function test_prune_snapshots_nulls_tags_beyond_retention_window(): void
    {
        $site = $this->makeSite($this->makeUser());
        config()->set('platform.deploy.rollback_snapshots', 2);

        // Create 4 successful deploys with snapshot tags.
        foreach (range(1, 4) as $i) {
            DeployLog::create([
                'site_id' => $site->id, 'status' => 'success',
                'snapshot_tag' => "deploy-tag-{$i}", 'triggered_by' => 'test',
                'created_at' => now()->subMinutes(10 - $i),
            ]);
        }

        $this->manager->pruneSnapshots($site);

        // The 2 most recent must keep their tags.
        $kept = DeployLog::where('site_id', $site->id)->whereNotNull('snapshot_tag')->count();
        $this->assertSame(2, $kept);

        // The 2 oldest must have tag set to null.
        $pruned = DeployLog::where('site_id', $site->id)->whereNull('snapshot_tag')->count();
        $this->assertSame(2, $pruned);
    }

    // ── resolveLog / resolveRelease ───────────────────────────────────────

    public function test_resolve_log_returns_model_from_string_id(): void
    {
        $site = $this->makeSite($this->makeUser());
        ['log' => $log] = $this->manager->begin($site, 'test');

        $resolved = $this->manager->resolveLog($log->id);
        $this->assertTrue($resolved->is($log));
    }

    public function test_resolve_log_returns_null_for_null(): void
    {
        $this->assertNull($this->manager->resolveLog(null));
    }

    // ── elapsedMs() ───────────────────────────────────────────────────────

    public function test_elapsed_ms_returns_non_negative_integer(): void
    {
        $site = $this->makeSite($this->makeUser());
        ['log' => $log] = $this->manager->begin($site, 'test');

        $ms = $this->manager->elapsedMs($log);
        $this->assertGreaterThanOrEqual(0, $ms);
        $this->assertIsInt($ms);
    }
}
