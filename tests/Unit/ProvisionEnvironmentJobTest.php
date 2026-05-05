<?php

namespace Tests\Unit;

use App\Jobs\ProvisionEnvironmentJob;
use App\Jobs\ProvisionSslJob;
use App\Models\DeployLog;
use App\Models\DeploymentRelease;
use App\Models\Site;
use App\Models\User;
use App\Services\DeployService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProvisionEnvironmentJobTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(): User
    {
        return User::create([
            'name' => 'U',
            'email' => 'pej-'.uniqid().'@x.com',
            'password' => Hash::make('secret'),
            'role' => 'admin',
        ]);
    }

    private function makeSite(User $user): Site
    {
        return Site::create([
            'user_id' => $user->id,
            'name' => 'S',
            'slug' => 'pej-'.uniqid(),
            'repo_url' => 'https://github.com/example/pej',
            'branch' => 'main',
            'project_type' => 'static_html',
        ]);
    }

    private function makeLog(Site $site): DeployLog
    {
        return DeployLog::create([
            'site_id' => $site->id,
            'status' => 'queued',
            'created_at' => now(),
        ]);
    }

    private function makeRelease(Site $site): DeploymentRelease
    {
        return DeploymentRelease::create([
            'site_id' => $site->id,
            'status' => 'pending',
            'source_commit_sha' => 'abc123',
            'source_branch' => 'main',
            'is_current' => false,
        ]);
    }

    // ── ProvisionEnvironmentJob ──────────────────

    public function test_calls_provision_environment_on_deploy_service(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);
        $log = $this->makeLog($site);
        $release = $this->makeRelease($site);

        $deployer = $this->mock(DeployService::class);
        $deployer->shouldReceive('provisionEnvironment')
            ->once()
            ->with(
                \Mockery::on(fn ($s) => $s->id === $site->id),
                \Mockery::on(fn ($l) => $l->id === $log->id),
                \Mockery::on(fn ($r) => $r->id === $release->id),
            );

        $job = new ProvisionEnvironmentJob($site->id, $log->id, $release->id);
        $job->handle($deployer);
    }

    public function test_marks_failed_on_exception(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);
        $log = $this->makeLog($site);
        $release = $this->makeRelease($site);

        $deployer = $this->mock(DeployService::class);
        $deployer->shouldReceive('markDeploymentFailed')->once();

        $job = new ProvisionEnvironmentJob($site->id, $log->id, $release->id);
        $job->failed(new \RuntimeException('provision failed'));
    }

    public function test_has_correct_queue_tags(): void
    {
        $job = new ProvisionEnvironmentJob('s-1', 'l-1', 'r-1');

        $this->assertContains('deploy', $job->tags());
        $this->assertContains('site:s-1', $job->tags());
        $this->assertContains('stage:provision', $job->tags());
    }
}
