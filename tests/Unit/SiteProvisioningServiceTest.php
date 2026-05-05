<?php

namespace Tests\Unit;

use App\Models\DeploymentTarget;
use App\Models\Site;
use App\Models\TrackingInstallation;
use App\Models\User;
use App\Services\SiteProvisioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SiteProvisioningServiceTest extends TestCase
{
    use RefreshDatabase;

    private SiteProvisioningService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(SiteProvisioningService::class);
    }

    private function makeUser(): User
    {
        return User::create([
            'name' => 'U',
            'email' => 'sps-'.uniqid().'@x.com',
            'password' => Hash::make('secret'),
            'role' => 'admin',
        ]);
    }

    private function makeSite(User $user, array $attrs = []): Site
    {
        return Site::create(array_merge([
            'user_id' => $user->id,
            'name' => 'S',
            'slug' => 'sps-'.uniqid(),
            'repo_url' => 'https://github.com/example/sps',
            'branch' => 'main',
            'project_type' => 'static_html',
        ], $attrs));
    }

    // ── ensureDefaultDeploymentTargets ────────────

    public function test_creates_production_deployment_target(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);

        $this->service->ensureDefaultDeploymentTargets($site);

        $this->assertDatabaseHas('deployment_targets', [
            'site_id' => $site->id,
            'environment' => 'production',
        ]);
    }

    public function test_creates_staging_deployment_target(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);

        $this->service->ensureDefaultDeploymentTargets($site);

        $this->assertDatabaseHas('deployment_targets', [
            'site_id' => $site->id,
            'environment' => 'staging',
        ]);
    }

    public function test_production_target_is_active(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);

        $this->service->ensureDefaultDeploymentTargets($site);

        $target = DeploymentTarget::where('site_id', $site->id)
            ->where('environment', 'production')
            ->first();

        $this->assertTrue($target->is_active);
    }

    public function test_staging_target_is_inactive_by_default(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);

        $this->service->ensureDefaultDeploymentTargets($site);

        $target = DeploymentTarget::where('site_id', $site->id)
            ->where('environment', 'staging')
            ->first();

        $this->assertFalse($target->is_active);
    }

    public function test_ensure_deployment_targets_is_idempotent(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);

        $this->service->ensureDefaultDeploymentTargets($site);
        $this->service->ensureDefaultDeploymentTargets($site);

        $this->assertSame(1, DeploymentTarget::where('site_id', $site->id)->where('environment', 'production')->count());
        $this->assertSame(1, DeploymentTarget::where('site_id', $site->id)->where('environment', 'staging')->count());
    }

    public function test_production_target_health_url_uses_domain(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user, ['domain' => 'example.com']);

        $this->service->ensureDefaultDeploymentTargets($site);

        $target = DeploymentTarget::where('site_id', $site->id)
            ->where('environment', 'production')
            ->first();

        $this->assertSame('https://example.com/', $target->health_check_url);
    }

    // ── ensureDefaultTrackingInstallation ─────────

    public function test_creates_pixelkraft_tracking_installation(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);

        $this->service->ensureDefaultTrackingInstallation($site);

        $this->assertDatabaseHas('tracking_installations', [
            'site_id' => $site->id,
            'provider' => 'pixelkraft',
        ]);
    }

    public function test_tracking_installation_is_active_by_default(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);

        $installation = $this->service->ensureDefaultTrackingInstallation($site);

        $this->assertTrue($installation->is_active);
    }

    public function test_ensure_tracking_installation_is_idempotent(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);

        $this->service->ensureDefaultTrackingInstallation($site);
        $this->service->ensureDefaultTrackingInstallation($site);

        $this->assertSame(1, TrackingInstallation::where('site_id', $site->id)->count());
    }

    public function test_tracking_installation_returns_model(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);

        $result = $this->service->ensureDefaultTrackingInstallation($site);

        $this->assertInstanceOf(TrackingInstallation::class, $result);
        $this->assertSame($site->id, $result->site_id);
    }
}
