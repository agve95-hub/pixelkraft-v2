<?php

namespace Tests\Unit;

use App\Jobs\ActivateReleaseJob;
use App\Jobs\BuildSiteJob;
use App\Jobs\CloneRepoJob;
use App\Jobs\InjectTrackingJob;
use App\Jobs\ParseSiteJob;
use App\Models\DeployLog;
use App\Models\DeploymentRelease;
use App\Models\Notification;
use App\Models\Site;
use App\Models\User;
use App\Services\DeployService;
use App\Services\GitSyncService;
use App\Services\ProjectDetector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DeployPipelineJobsTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(): User
    {
        return User::create([
            'name' => 'U',
            'email' => 'dpj-'.uniqid().'@x.com',
            'password' => Hash::make('secret'),
            'role' => 'admin',
        ]);
    }

    private function makeSite(User $user): Site
    {
        return Site::create([
            'user_id' => $user->id,
            'name' => 'S',
            'slug' => 'dpj-'.uniqid(),
            'repo_url' => 'https://github.com/example/dpj',
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

    // ── ActivateReleaseJob ───────────────────────

    public function test_activate_release_job_calls_activate_on_deploy_service(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);
        $log = $this->makeLog($site);
        $release = $this->makeRelease($site);

        $deployer = $this->mock(DeployService::class);
        $deployer->shouldReceive('activateRelease')
            ->once()
            ->with(
                \Mockery::on(fn ($s) => $s->id === $site->id),
                \Mockery::on(fn ($l) => $l->id === $log->id),
                \Mockery::on(fn ($r) => $r->id === $release->id),
            );

        $job = new ActivateReleaseJob($site->id, $log->id, $release->id);
        $job->handle($deployer);
    }

    public function test_activate_release_job_marks_failed_on_exception(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);
        $log = $this->makeLog($site);
        $release = $this->makeRelease($site);

        $deployer = $this->mock(DeployService::class);
        $deployer->shouldReceive('markDeploymentFailed')->once();

        $job = new ActivateReleaseJob($site->id, $log->id, $release->id);
        $job->failed(new \RuntimeException('activate failed'));
    }

    public function test_activate_release_job_has_correct_tags(): void
    {
        $job = new ActivateReleaseJob('site-1', 'log-1', 'release-1');

        $tags = $job->tags();

        $this->assertContains('deploy', $tags);
        $this->assertContains('site:site-1', $tags);
        $this->assertContains('stage:activate', $tags);
    }

    // ── BuildSiteJob ─────────────────────────────

    public function test_build_site_job_calls_build_on_deploy_service(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);
        $log = $this->makeLog($site);
        $release = $this->makeRelease($site);

        $deployer = $this->mock(DeployService::class);
        $deployer->shouldReceive('buildSite')
            ->once()
            ->with(
                \Mockery::on(fn ($s) => $s->id === $site->id),
                \Mockery::on(fn ($l) => $l->id === $log->id),
                \Mockery::on(fn ($r) => $r->id === $release->id),
            );

        $job = new BuildSiteJob($site->id, $log->id, $release->id);
        $job->handle($deployer);
    }

    public function test_build_site_job_marks_failed_on_exception(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);
        $log = $this->makeLog($site);
        $release = $this->makeRelease($site);

        $deployer = $this->mock(DeployService::class);
        $deployer->shouldReceive('markDeploymentFailed')->once();

        $job = new BuildSiteJob($site->id, $log->id, $release->id);
        $job->failed(new \RuntimeException('build failed'));
    }

    public function test_build_site_job_has_correct_tags(): void
    {
        $job = new BuildSiteJob('site-1', 'log-1', 'release-1');

        $this->assertContains('stage:build', $job->tags());
    }

    // ── InjectTrackingJob ────────────────────────

    public function test_inject_tracking_job_calls_inject_on_deploy_service(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);
        $log = $this->makeLog($site);
        $release = $this->makeRelease($site);

        $deployer = $this->mock(DeployService::class);
        $deployer->shouldReceive('injectTrackingAssets')->once();

        $job = new InjectTrackingJob($site->id, $log->id, $release->id);
        $job->handle($deployer);
    }

    public function test_inject_tracking_job_marks_failed_on_exception(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);
        $log = $this->makeLog($site);
        $release = $this->makeRelease($site);

        $deployer = $this->mock(DeployService::class);
        $deployer->shouldReceive('markDeploymentFailed')->once();

        $job = new InjectTrackingJob($site->id, $log->id, $release->id);
        $job->failed(new \RuntimeException('tracking failed'));
    }

    // ── CloneRepoJob ─────────────────────────────

    public function test_clone_repo_job_clones_and_dispatches_parse_job(): void
    {
        Bus::fake();

        $user = $this->makeUser();
        $site = $this->makeSite($user);

        $git = $this->mock(GitSyncService::class);
        $git->shouldReceive('cloneRepo')->once()->with($site);

        $detector = $this->mock(ProjectDetector::class);
        $detector->shouldReceive('applyToSite')
            ->once()
            ->andReturn(['type' => 'static_html', 'confidence' => 0.9, 'build_command' => null, 'build_output_dir' => null, 'deployment_mode' => 'static']);

        $job = new CloneRepoJob($site);
        $job->handle($git, $detector);

        Bus::assertDispatched(ParseSiteJob::class);
    }

    public function test_clone_repo_job_creates_notification_and_rethrows_on_failure(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);

        $git = $this->mock(GitSyncService::class);
        $git->shouldReceive('cloneRepo')->andThrow(new \RuntimeException('clone error'));

        $detector = $this->mock(ProjectDetector::class);

        $this->expectException(\RuntimeException::class);

        $job = new CloneRepoJob($site);
        $job->handle($git, $detector);

        $this->assertDatabaseHas('notifications', ['site_id' => $site->id, 'type' => 'deploy_failed']);
    }

    public function test_clone_repo_job_scrubs_token_from_error_message(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);

        $git = $this->mock(GitSyncService::class);
        $git->shouldReceive('cloneRepo')
            ->andThrow(new \RuntimeException('fatal: error with x-access-token:ghp_secretABC123@github.com'));

        $detector = $this->mock(ProjectDetector::class);

        try {
            $job = new CloneRepoJob($site);
            $job->handle($git, $detector);
        } catch (\RuntimeException) {
            // expected
        }

        $notification = Notification::where('site_id', $site->id)->first();
        $this->assertNotNull($notification);
        $this->assertStringNotContainsString('ghp_secretABC123', (string) $notification->body);
        $this->assertStringContainsString('[REDACTED]', (string) $notification->body);
    }
}
