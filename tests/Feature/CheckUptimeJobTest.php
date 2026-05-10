<?php

namespace Tests\Feature;

use App\Enums\DeployStatus;
use App\Jobs\CheckUptimeJob;
use App\Models\Notification;
use App\Models\Site;
use App\Models\UptimeCheck;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CheckUptimeJobTest extends TestCase
{
    use RefreshDatabase;

    private function makeLiveSite(string $domain): Site
    {
        $user = User::create([
            'name' => 'U', 'email' => 'cuj-'.uniqid().'@x.com',
            'password' => Hash::make('secret'), 'role' => 'admin',
        ]);

        return Site::create([
            'user_id' => $user->id, 'name' => 'S', 'slug' => 'cuj-'.uniqid(),
            'branch' => 'main', 'project_type' => 'static_html',
            'domain' => $domain, 'is_active' => true, 'deploy_status' => DeployStatus::Live,
        ]);
    }

    public function test_loopback_ip_is_blocked_without_http_request(): void
    {
        Http::fake();
        $site = $this->makeLiveSite('127.0.0.1');

        (new CheckUptimeJob($site->id))->handle();

        Http::assertNothingSent();
        $check = UptimeCheck::where('site_id', $site->id)->first();
        $this->assertNotNull($check);
        $this->assertSame(0, $check->status_code);
        $this->assertFalse((bool) $check->is_up);
    }

    public function test_private_ip_is_blocked_without_http_request(): void
    {
        Http::fake();
        $site = $this->makeLiveSite('10.0.0.1');

        (new CheckUptimeJob($site->id))->handle();

        Http::assertNothingSent();
        $check = UptimeCheck::where('site_id', $site->id)->first();
        $this->assertFalse((bool) $check->is_up);
        $this->assertSame(0, $check->status_code);
    }

    public function test_successful_response_records_up_check(): void
    {
        Http::fake(['https://example.com' => Http::response('', 200)]);
        $site = $this->makeLiveSite('example.com');

        (new CheckUptimeJob($site->id))->handle();

        $check = UptimeCheck::where('site_id', $site->id)->first();
        $this->assertNotNull($check);
        $this->assertTrue((bool) $check->is_up);
        $this->assertSame(200, $check->status_code);
    }

    public function test_failed_response_records_down_check(): void
    {
        Http::fake(['https://example.com' => Http::response('', 503)]);
        $site = $this->makeLiveSite('example.com');

        (new CheckUptimeJob($site->id))->handle();

        $check = UptimeCheck::where('site_id', $site->id)->first();
        $this->assertFalse((bool) $check->is_up);
    }

    public function test_three_consecutive_failures_create_notification(): void
    {
        Http::fake(['https://example.com' => Http::response('', 503)]);
        $site = $this->makeLiveSite('example.com');

        // Run the job three times to reach the alert threshold.
        for ($i = 0; $i < 3; $i++) {
            (new CheckUptimeJob($site->id))->handle();
        }

        $this->assertDatabaseHas('notifications', [
            'site_id' => $site->id,
            'type' => 'uptime_down',
        ]);
    }

    public function test_job_is_on_monitoring_queue_with_uniqueness(): void
    {
        $job = new CheckUptimeJob('site-id');
        $this->assertSame('monitoring', $job->queue);
        $this->assertSame('site-id', $job->uniqueId());
    }

    public function test_missing_site_is_handled_gracefully(): void
    {
        Http::fake();
        (new CheckUptimeJob('00000000-0000-0000-0000-000000000000'))->handle();
        Http::assertNothingSent();
        $this->assertTrue(true); // no exception thrown
    }
}
