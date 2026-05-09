<?php

namespace Tests\Unit;

use App\Console\Commands\CheckUptime;
use App\Enums\DeployStatus;
use App\Models\Site;
use App\Models\UptimeCheck;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Verify that the SSRF guard in CheckUptime blocks requests to private /
 * loopback / link-local addresses.
 *
 * The guard resolves $site->domain with gethostbyname() and rejects the
 * request when the IP falls in a private or reserved range.  Using a
 * literal private IP as the domain is enough to exercise this path
 * because gethostbyname() returns the input unchanged for a raw IP,
 * which makes the $resolvedIp !== $domain condition false and therefore
 * $isPublicIp = false.
 */
class CheckUptimeSsrfTest extends TestCase
{
    use RefreshDatabase;

    private function makeLiveSite(string $domain): Site
    {
        static $counter = 0;
        $counter++;

        return Site::create([
            'name' => "Test Site {$counter}",
            'slug' => "test-site-{$counter}",
            'repo_url' => 'https://github.com/example/repo.git',
            'branch' => 'main',
            'domain' => $domain,
            'is_active' => true,
            'deploy_status' => DeployStatus::Live,
        ]);
    }

    /** @test */
    public function test_loopback_domain_is_blocked_and_recorded_as_down(): void
    {
        Http::fake();

        $site = $this->makeLiveSite('127.0.0.1');

        $this->artisan('platform:check-uptime');

        // No outbound HTTP request should have been made.
        Http::assertNothingSent();

        // A zero-status UptimeCheck must be recorded so the dashboard
        // still shows that a check was attempted.
        $check = UptimeCheck::where('site_id', $site->id)->latest('checked_at')->first();
        $this->assertNotNull($check, 'Expected an UptimeCheck row to be recorded.');
        $this->assertSame(0, $check->status_code);
        $this->assertFalse((bool) $check->is_up);
        $this->assertFalse((bool) $check->is_degraded);
    }

    /** @test */
    public function test_rfc1918_domain_is_blocked_and_recorded_as_down(): void
    {
        Http::fake();

        $site = $this->makeLiveSite('10.0.0.1');

        $this->artisan('platform:check-uptime');

        Http::assertNothingSent();

        $check = UptimeCheck::where('site_id', $site->id)->latest('checked_at')->first();
        $this->assertNotNull($check);
        $this->assertSame(0, $check->status_code);
        $this->assertFalse((bool) $check->is_up);
    }

    /** @test */
    public function test_link_local_ip_domain_is_blocked(): void
    {
        Http::fake();

        // 169.254.169.254 is the AWS instance metadata endpoint.
        $site = $this->makeLiveSite('169.254.169.254');

        $this->artisan('platform:check-uptime');

        Http::assertNothingSent();

        $check = UptimeCheck::where('site_id', $site->id)->latest('checked_at')->first();
        $this->assertNotNull($check);
        $this->assertSame(0, $check->status_code);
        $this->assertFalse((bool) $check->is_up);
    }
}
