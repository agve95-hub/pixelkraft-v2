<?php

namespace Tests\Feature;

use App\Jobs\ProvisionSslJob;
use App\Models\Site;
use App\Models\User;
use App\Services\AnalyticsAggregator;
use App\Services\SslService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SyncAnalyticsAndSslCommandsTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(): User
    {
        return User::create([
            'name' => 'U',
            'email' => 'sassl-'.uniqid().'@x.com',
            'password' => Hash::make('secret'),
            'role' => 'admin',
        ]);
    }

    private function makeSite(User $user): Site
    {
        return Site::create([
            'user_id' => $user->id,
            'name' => 'S',
            'slug' => 'sassl-'.uniqid(),
            'repo_url' => 'https://github.com/example/sassl',
            'branch' => 'main',
            'project_type' => 'static_html',
        ]);
    }

    // ── pixelkraft:sync-analytics ─────────────────

    public function test_sync_analytics_command_calls_aggregator_and_succeeds(): void
    {
        $aggregator = $this->mock(AnalyticsAggregator::class);
        $aggregator->shouldReceive('syncAll')->once()->andReturn(5);

        $this->artisan('pixelkraft:sync-analytics')
            ->assertSuccessful()
            ->expectsOutputToContain('5 write operations');
    }

    public function test_sync_analytics_outputs_completion_message(): void
    {
        $aggregator = $this->mock(AnalyticsAggregator::class);
        $aggregator->shouldReceive('syncAll')->andReturn(0);

        $this->artisan('pixelkraft:sync-analytics')
            ->assertSuccessful()
            ->expectsOutputToContain('Analytics sync completed');
    }

    // ── pixelkraft:check-ssl ──────────────────────

    public function test_check_ssl_command_calls_ssl_service_and_succeeds(): void
    {
        $ssl = $this->mock(SslService::class);
        $ssl->shouldReceive('checkAllCertificates')->once()->andReturn(0);

        $this->artisan('pixelkraft:check-ssl')->assertSuccessful();
    }

    public function test_check_ssl_outputs_alert_count(): void
    {
        $ssl = $this->mock(SslService::class);
        $ssl->shouldReceive('checkAllCertificates')->andReturn(3);

        $this->artisan('pixelkraft:check-ssl')
            ->assertSuccessful()
            ->expectsOutputToContain('3 alerts');
    }

    // ── ProvisionSslJob ───────────────────────────

    public function test_provision_ssl_job_calls_ssl_provision(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);

        $ssl = $this->mock(SslService::class);
        $ssl->shouldReceive('provision')->once()->with($site);

        $job = new ProvisionSslJob($site);
        $job->handle($ssl);
    }

    public function test_provision_ssl_job_has_correct_tags(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);

        $job = new ProvisionSslJob($site);
        $tags = $job->tags();

        $this->assertContains('ssl', $tags);
        $this->assertContains("site:{$site->id}", $tags);
    }
}
