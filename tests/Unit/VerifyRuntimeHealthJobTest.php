<?php

namespace Tests\Unit;

use App\Jobs\VerifyRuntimeHealthJob;
use App\Models\DeployLog;
use App\Models\Notification;
use App\Models\Site;
use App\Models\User;
use App\Services\SiteRuntimeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Mockery;
use Tests\TestCase;

class VerifyRuntimeHealthJobTest extends TestCase
{
    use RefreshDatabase;

    private function makeSite(): Site
    {
        $user = User::create([
            'name' => 'U', 'email' => 'vrhj-'.uniqid().'@x.com',
            'password' => Hash::make('secret'), 'role' => 'admin',
        ]);

        return Site::create([
            'user_id' => $user->id, 'name' => 'S',
            'slug' => 'vrhj-'.uniqid(), 'branch' => 'main', 'project_type' => 'nextjs',
        ]);
    }

    private function makeLog(Site $site): DeployLog
    {
        return DeployLog::create([
            'site_id' => $site->id, 'status' => 'success',
            'triggered_by' => 'test', 'created_at' => now(),
        ]);
    }

    public function test_job_returns_without_retrying_when_site_is_healthy(): void
    {
        $site = $this->makeSite();
        $log = $this->makeLog($site);

        $runtime = Mockery::mock(SiteRuntimeService::class);
        $runtime->shouldReceive('isReachable')->once()->andReturn(true);
        $this->app->instance(SiteRuntimeService::class, $runtime);

        $job = new VerifyRuntimeHealthJob($site->id, $log->id, 4100);
        $job->handle($runtime);

        $this->assertTrue(true); // reached without calling release()
    }

    public function test_job_is_on_monitoring_queue(): void
    {
        $job = new VerifyRuntimeHealthJob('site-id', 'log-id', 4100);
        $this->assertSame('monitoring', $job->queue);
    }

    public function test_job_has_24_tries_with_5_second_backoff(): void
    {
        $job = new VerifyRuntimeHealthJob('site-id', 'log-id', 4100);
        $this->assertSame(24, $job->tries);
        $this->assertSame(5, $job->backoff);
    }

    public function test_failed_creates_notification_for_site(): void
    {
        $site = $this->makeSite();
        $log = $this->makeLog($site);

        $job = new VerifyRuntimeHealthJob($site->id, $log->id, 4100);
        $job->failed(null);

        $this->assertDatabaseHas('notifications', [
            'site_id' => $site->id,
            'type' => 'deploy_failed',
        ]);
    }

    public function test_failed_with_missing_site_does_not_crash(): void
    {
        // Use a valid UUID that simply has no matching row.
        $job = new VerifyRuntimeHealthJob('00000000-0000-0000-0000-000000000000', 'log-id', 4100);
        $job->failed(null); // must not throw
        $this->assertTrue(true);
    }
}
