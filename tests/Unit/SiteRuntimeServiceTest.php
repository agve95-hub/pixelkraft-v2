<?php

namespace Tests\Unit;

use App\Models\Site;
use App\Models\User;
use App\Services\SiteRuntimeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SiteRuntimeServiceTest extends TestCase
{
    use RefreshDatabase;

    private SiteRuntimeService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new SiteRuntimeService;
    }

    private function makeUser(): User
    {
        return User::create([
            'name' => 'U',
            'email' => 'srs-'.uniqid().'@x.com',
            'password' => Hash::make('secret'),
            'role' => 'admin',
        ]);
    }

    private function makeSite(User $user, array $attrs = []): Site
    {
        return Site::create(array_merge([
            'user_id' => $user->id,
            'name' => 'S',
            'slug' => 'srs-'.uniqid(),
            'repo_url' => 'https://github.com/example/srs',
            'branch' => 'main',
            'project_type' => 'static_html',
        ], $attrs));
    }

    // ── supportsRuntimeModeForProjectType ─────────

    public function test_only_nextjs_supports_runtime_mode(): void
    {
        $this->assertTrue($this->service->supportsRuntimeModeForProjectType('nextjs'));
    }

    public function test_static_html_does_not_support_runtime_mode(): void
    {
        $this->assertFalse($this->service->supportsRuntimeModeForProjectType('static_html'));
    }

    public function test_react_does_not_support_runtime_mode(): void
    {
        $this->assertFalse($this->service->supportsRuntimeModeForProjectType('react'));
    }

    // ── supportedDeploymentModes ──────────────────

    public function test_nextjs_site_supports_both_modes(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user, ['project_type' => 'nextjs']);

        $modes = $this->service->supportedDeploymentModes($site);

        $this->assertContains(SiteRuntimeService::MODE_STATIC, $modes);
        $this->assertContains(SiteRuntimeService::MODE_RUNTIME, $modes);
    }

    public function test_static_html_site_supports_only_static_mode(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user, ['project_type' => 'static_html']);

        $modes = $this->service->supportedDeploymentModes($site);

        $this->assertSame([SiteRuntimeService::MODE_STATIC], $modes);
    }

    // ── configuredDeploymentMode ──────────────────

    public function test_configured_mode_returns_null_when_not_set(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);

        $this->assertNull($this->service->configuredDeploymentMode($site));
    }

    public function test_configured_mode_returns_static_when_set(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user, ['deployment_mode' => 'static']);

        $this->assertSame('static', $this->service->configuredDeploymentMode($site));
    }

    public function test_configured_mode_returns_runtime_when_set(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user, ['deployment_mode' => 'runtime']);

        $this->assertSame('runtime', $this->service->configuredDeploymentMode($site));
    }

    public function test_configured_mode_normalises_uppercase(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user, ['deployment_mode' => 'STATIC']);

        $this->assertSame('static', $this->service->configuredDeploymentMode($site));
    }

    public function test_configured_mode_returns_null_for_invalid_value(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user, ['deployment_mode' => 'unknown-mode']);

        $this->assertNull($this->service->configuredDeploymentMode($site));
    }

    // ── deploymentMode — configured vs inferred ───

    public function test_deployment_mode_uses_configured_value_for_nextjs(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user, ['project_type' => 'nextjs', 'deployment_mode' => 'static']);

        $this->assertSame('static', $this->service->deploymentMode($site));
    }

    public function test_deployment_mode_ignores_runtime_config_for_non_nextjs(): void
    {
        // 'runtime' is not in supportedDeploymentModes for react — falls back to inferred
        $user = $this->makeUser();
        $site = $this->makeSite($user, ['project_type' => 'react', 'deployment_mode' => 'runtime']);

        $this->assertSame('static', $this->service->deploymentMode($site));
    }

    // ── inferredDeploymentMode ────────────────────

    public function test_nextjs_infers_runtime_when_no_static_indicators(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user, [
            'project_type' => 'nextjs',
            'build_output_dir' => null,
            'build_command' => null,
        ]);

        // No next.config.* file exists in test environment
        $this->assertSame('runtime', $this->service->inferredDeploymentMode($site));
    }

    public function test_nextjs_infers_static_when_output_dir_is_not_next(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user, [
            'project_type' => 'nextjs',
            'build_output_dir' => 'out',
        ]);

        $this->assertSame('static', $this->service->inferredDeploymentMode($site));
    }

    public function test_nextjs_infers_static_when_build_command_contains_next_export(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user, [
            'project_type' => 'nextjs',
            'build_command' => 'npm run build && npx next export',
        ]);

        $this->assertSame('static', $this->service->inferredDeploymentMode($site));
    }

    public function test_non_nextjs_always_infers_static(): void
    {
        $user = $this->makeUser();

        foreach (['react', 'vue', 'static_html', 'hugo', 'astro'] as $type) {
            $site = $this->makeSite($user, ['project_type' => $type]);
            $this->assertSame('static', $this->service->inferredDeploymentMode($site), "Failed for type: {$type}");
        }
    }

    // ── deploymentModeSource ──────────────────────

    public function test_source_is_configured_when_valid_mode_set(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user, ['project_type' => 'nextjs', 'deployment_mode' => 'runtime']);

        $this->assertSame('configured', $this->service->deploymentModeSource($site));
    }

    public function test_source_is_inferred_when_no_configured_mode(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user, ['deployment_mode' => null]);

        $this->assertSame('inferred', $this->service->deploymentModeSource($site));
    }

    // ── portFor ──────────────────────────────────

    public function test_port_for_returns_integer_in_valid_range(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);

        $port = $this->service->portFor($site);

        $portStart = (int) config('pixelkraft.runtime.port_start', 4100);
        $portSpan = max(100, (int) config('pixelkraft.runtime.port_span', 2000));

        $this->assertGreaterThanOrEqual($portStart, $port);
        $this->assertLessThan($portStart + $portSpan, $port);
    }

    public function test_port_for_is_deterministic_for_same_site(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);

        $this->assertSame($this->service->portFor($site), $this->service->portFor($site));
    }

    // ── usesRuntimeServer / usesStaticExport ──────

    public function test_uses_runtime_server_true_for_nextjs_in_runtime_mode(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user, ['project_type' => 'nextjs', 'deployment_mode' => 'runtime']);

        $this->assertTrue($this->service->usesRuntimeServer($site));
    }

    public function test_uses_runtime_server_false_for_static_site(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user, ['project_type' => 'static_html']);

        $this->assertFalse($this->service->usesRuntimeServer($site));
    }

    public function test_uses_static_export_true_for_nextjs_in_static_mode(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user, [
            'project_type' => 'nextjs',
            'deployment_mode' => 'static',
            'build_output_dir' => 'out',
        ]);

        $this->assertTrue($this->service->usesStaticExport($site));
    }

    public function test_uses_static_export_false_for_non_nextjs(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user, ['project_type' => 'react', 'deployment_mode' => 'static']);

        $this->assertFalse($this->service->usesStaticExport($site));
    }
}
