<?php

namespace Tests\Unit;

use App\Models\Site;
use App\Models\User;
use App\Services\SslService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

class SslServiceTest extends TestCase
{
    use RefreshDatabase;

    private SslService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new SslService;
    }

    private function makeUser(): User
    {
        return User::create([
            'name' => 'U',
            'email' => 'ssl-'.uniqid().'@x.com',
            'password' => Hash::make('secret'),
            'role' => 'admin',
        ]);
    }

    private function makeSite(User $user, array $attrs = []): Site
    {
        return Site::create(array_merge([
            'user_id' => $user->id,
            'name' => 'S',
            'slug' => 'ssl-'.uniqid(),
            'repo_url' => 'https://github.com/example/ssl',
            'branch' => 'main',
            'project_type' => 'static_html',
            'is_active' => true,
            'domain' => 'example.com',
            'ssl_status' => 'active',
        ], $attrs));
    }

    private function certbotOutput(string $dateIso): string
    {
        return "Saving debug log to /var/log/letsencrypt/letsencrypt.log\n"
            ."- - - - - - - - - - - - - - - - - -\n"
            ."Found the following certs:\n"
            ."  Certificate Name: example.com\n"
            ."    Serial Number: abc123\n"
            ."    Expiry Date: {$dateIso} (VALID: 26 days)\n"
            ."- - - - - - - - - - - - - - - - - -\n";
    }

    // ── provision ────────────────────────────────

    public function test_provision_throws_when_site_has_no_domain(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user, ['domain' => null]);
        DB::table('sites')
            ->where('id', $site->id)
            ->update(['domain' => null]);
        $site->refresh();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('no domain configured');

        $this->service->provision($site);
    }

    // ── renewAll ─────────────────────────────────

    public function test_renew_all_returns_true_when_certbot_succeeds(): void
    {
        Process::fake(['*certbot renew*' => Process::result('', '', 0)]);

        $this->assertTrue($this->service->renewAll());
    }

    public function test_renew_all_returns_false_when_certbot_fails(): void
    {
        Process::fake(['*certbot renew*' => Process::result('', 'error', 1)]);

        $this->assertFalse($this->service->renewAll());
    }

    // ── checkAllCertificates ──────────────────────

    public function test_check_all_skips_inactive_sites(): void
    {
        $user = $this->makeUser();
        $this->makeSite($user, ['is_active' => false]);

        Process::fake();

        $alerts = $this->service->checkAllCertificates();

        $this->assertSame(0, $alerts);
        Process::assertNothingRan();
    }

    public function test_check_all_skips_sites_without_domain(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);
        DB::table('sites')
            ->where('id', $site->id)->update(['domain' => null]);

        Process::fake();

        $alerts = $this->service->checkAllCertificates();

        $this->assertSame(0, $alerts);
    }

    public function test_check_all_skips_sites_without_active_ssl(): void
    {
        $user = $this->makeUser();
        $this->makeSite($user, ['ssl_status' => 'none']);

        Process::fake();

        $alerts = $this->service->checkAllCertificates();

        $this->assertSame(0, $alerts);
        Process::assertNothingRan();
    }

    public function test_check_all_creates_alert_for_cert_expiring_within_14_days(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);

        $expiry = now()->addDays(7)->format('Y-m-d H:i:s+00:00');

        Process::fake([
            '*certbot certificates*' => Process::result($this->certbotOutput($expiry), '', 0),
        ]);

        $alerts = $this->service->checkAllCertificates();

        $this->assertSame(1, $alerts);
        $this->assertDatabaseHas('notifications', [
            'type' => 'ssl_expiring',
            'site_id' => $site->id,
        ]);
    }

    public function test_check_all_does_not_alert_for_cert_expiring_in_30_days(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);

        $expiry = now()->addDays(30)->format('Y-m-d H:i:s+00:00');

        Process::fake([
            '*certbot certificates*' => Process::result($this->certbotOutput($expiry), '', 0),
        ]);

        $alerts = $this->service->checkAllCertificates();

        $this->assertSame(0, $alerts);
        $this->assertDatabaseMissing('notifications', ['site_id' => $site->id]);
    }

    public function test_check_all_marks_expired_cert_as_expired(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);

        $expiry = now()->subDay()->format('Y-m-d H:i:s+00:00');

        Process::fake([
            '*certbot certificates*' => Process::result($this->certbotOutput($expiry), '', 0),
        ]);

        $this->service->checkAllCertificates();

        $site->refresh();
        $this->assertSame('expired', $site->ssl_status);
    }

    public function test_check_all_stores_expiry_date_on_site(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);

        $expiry = now()->addDays(30)->format('Y-m-d H:i:s+00:00');

        Process::fake([
            '*certbot certificates*' => Process::result($this->certbotOutput($expiry), '', 0),
        ]);

        $this->service->checkAllCertificates();

        $site->refresh();
        $this->assertNotNull($site->ssl_expires_at);
    }

    public function test_check_all_skips_sites_where_certbot_returns_no_expiry(): void
    {
        $user = $this->makeUser();
        $this->makeSite($user);

        Process::fake([
            '*certbot certificates*' => Process::result('No certificates found.', '', 0),
        ]);

        $alerts = $this->service->checkAllCertificates();

        $this->assertSame(0, $alerts);
    }
}
