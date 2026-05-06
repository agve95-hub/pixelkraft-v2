<?php

namespace Tests\Unit;

use App\Models\DeployLog;
use App\Models\Site;
use App\Models\User;
use App\Services\Deployment\RuntimeDeploymentAdapter;
use App\Services\SiteRuntimeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RuntimeDeploymentAdapterTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(): User
    {
        return User::create([
            'name' => 'U',
            'email' => 'rda-'.uniqid().'@x.com',
            'password' => Hash::make('secret'),
            'role' => 'admin',
        ]);
    }

    private function makeSite(User $user): Site
    {
        return Site::create([
            'user_id' => $user->id,
            'name' => 'S',
            'slug' => 'rda-'.uniqid(),
            'repo_url' => 'https://github.com/example/rda',
            'branch' => 'main',
            'project_type' => 'nextjs',
        ]);
    }

    private function makeAdapter(?SiteRuntimeService $runtime = null): RuntimeDeploymentAdapter
    {
        return new RuntimeDeploymentAdapter($runtime ?? $this->mock(SiteRuntimeService::class));
    }

    public function test_mode_returns_runtime(): void
    {
        $this->assertSame(SiteRuntimeService::MODE_RUNTIME, $this->makeAdapter()->mode());
    }

    public function test_activation_step_label(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);

        $this->assertSame('Starting runtime server...', $this->makeAdapter()->activationStepLabel($site));
    }

    public function test_artifact_directory_returns_null(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);

        $this->assertNull($this->makeAdapter()->artifactDirectory($site));
    }

    public function test_does_not_support_aggressive_optimization(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);

        $this->assertFalse($this->makeAdapter()->supportsAggressiveOptimization($site));
    }

    public function test_activate_delegates_to_site_runtime_service(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);

        $log = DeployLog::create([
            'site_id' => $site->id,
            'status' => 'deploying',
            'created_at' => now(),
        ]);

        $runtime = $this->mock(SiteRuntimeService::class);
        $runtime->shouldReceive('deploy')->once()->with($site, \Mockery::any());
        $runtime->shouldReceive('baseUrl')->andReturn('http://127.0.0.1:4100');

        $adapter = $this->makeAdapter($runtime);
        $adapter->activate($site, $log);
    }

    public function test_activate_appends_to_deploy_log(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);

        $log = DeployLog::create([
            'site_id' => $site->id,
            'status' => 'deploying',
            'created_at' => now(),
        ]);

        $runtime = $this->mock(SiteRuntimeService::class);
        $runtime->shouldReceive('deploy')->once();
        $runtime->shouldReceive('baseUrl')->andReturn('http://127.0.0.1:4100');

        $this->makeAdapter($runtime)->activate($site, $log);

        $log->refresh();
        $this->assertStringContainsString('127.0.0.1:4100', $log->output_log ?? '');
    }
}
