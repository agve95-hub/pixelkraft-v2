<?php

namespace Tests\Feature;

use App\Jobs\SyncFromWebhookJob;
use App\Models\Site;
use App\Models\User;
use App\Models\WebhookDelivery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ReplayWebhooksCommandTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(): User
    {
        return User::create([
            'name' => 'U',
            'email' => 'rwh-'.uniqid().'@x.com',
            'password' => Hash::make('secret'),
            'role' => 'admin',
        ]);
    }

    private function makeSite(User $user, string $repoUrl = 'https://github.com/acme/site'): Site
    {
        return Site::create([
            'user_id' => $user->id,
            'name' => 'S',
            'slug' => 'rwh-'.uniqid(),
            'repo_url' => $repoUrl,
            'branch' => 'main',
            'project_type' => 'static_html',
            'is_active' => true,
        ]);
    }

    private function makeDelivery(array $attrs = []): WebhookDelivery
    {
        return WebhookDelivery::create(array_merge([
            'provider' => 'github',
            'delivery_id' => uniqid('dlv-'),
            'event' => 'push',
            'repository' => 'https://github.com/acme/site',
            'status' => 'received',
            'payload' => ['ref' => 'refs/heads/main', 'commits' => []],
            'received_at' => now()->subHour(),
            'processed_at' => null,
        ], $attrs));
    }

    // ── --since required ─────────────────────────

    public function test_fails_without_since_option(): void
    {
        $this->artisan('pixelkraft:replay-webhooks')->assertFailed();
    }

    public function test_fails_with_unparseable_since_value(): void
    {
        // Carbon::parse throws for clearly invalid values; the command catches and returns FAILURE.
        $this->artisan('pixelkraft:replay-webhooks', ['--since' => 'not-a-date'])
            ->assertFailed();
    }

    // ── dry-run ──────────────────────────────────

    public function test_dry_run_lists_without_dispatching_jobs(): void
    {
        Bus::fake();

        $user = $this->makeUser();
        $this->makeSite($user);
        $this->makeDelivery();

        $this->artisan('pixelkraft:replay-webhooks', [
            '--since' => '2 hours ago',
            '--dry-run' => true,
        ])->assertSuccessful()
            ->expectsOutputToContain('[dry-run]');

        Bus::assertNotDispatched(SyncFromWebhookJob::class);
    }

    // ── job dispatch ─────────────────────────────

    public function test_dispatches_job_for_stalled_delivery(): void
    {
        Bus::fake();

        $user = $this->makeUser();
        $this->makeSite($user, 'https://github.com/acme/site');
        $this->makeDelivery(['repository' => 'https://github.com/acme/site']);

        $this->artisan('pixelkraft:replay-webhooks', ['--since' => '2 hours ago'])
            ->assertSuccessful();

        Bus::assertDispatched(SyncFromWebhookJob::class);
    }

    public function test_does_not_dispatch_for_already_processed_delivery(): void
    {
        Bus::fake();

        $user = $this->makeUser();
        $this->makeSite($user);
        $this->makeDelivery(['processed_at' => now()->subMinutes(30)]);

        $this->artisan('pixelkraft:replay-webhooks', ['--since' => '2 hours ago'])
            ->assertSuccessful();

        Bus::assertNotDispatched(SyncFromWebhookJob::class);
    }

    public function test_does_not_dispatch_for_non_push_event(): void
    {
        Bus::fake();

        $user = $this->makeUser();
        $this->makeSite($user);
        $this->makeDelivery(['event' => 'pull_request']);

        $this->artisan('pixelkraft:replay-webhooks', ['--since' => '2 hours ago'])
            ->assertSuccessful();

        Bus::assertNotDispatched(SyncFromWebhookJob::class);
    }

    public function test_does_not_dispatch_when_branch_does_not_match(): void
    {
        Bus::fake();

        $user = $this->makeUser();
        $this->makeSite($user); // branch=main
        $this->makeDelivery(['payload' => ['ref' => 'refs/heads/feature-xyz']]);

        $this->artisan('pixelkraft:replay-webhooks', ['--since' => '2 hours ago'])
            ->assertSuccessful();

        Bus::assertNotDispatched(SyncFromWebhookJob::class);
    }

    public function test_skips_delivery_with_no_ref_in_payload(): void
    {
        Bus::fake();

        $user = $this->makeUser();
        $this->makeSite($user);
        $this->makeDelivery(['payload' => ['commits' => []]]);

        $this->artisan('pixelkraft:replay-webhooks', ['--since' => '2 hours ago'])
            ->assertSuccessful();

        Bus::assertNotDispatched(SyncFromWebhookJob::class);
    }

    public function test_no_deliveries_found_exits_successfully(): void
    {
        $this->artisan('pixelkraft:replay-webhooks', ['--since' => '2 hours ago'])
            ->assertSuccessful();
    }

    public function test_deliveries_before_since_window_are_ignored(): void
    {
        Bus::fake();

        $user = $this->makeUser();
        $this->makeSite($user);
        $this->makeDelivery(['received_at' => now()->subDays(2)]);

        $this->artisan('pixelkraft:replay-webhooks', ['--since' => '1 hour ago'])
            ->assertSuccessful();

        Bus::assertNotDispatched(SyncFromWebhookJob::class);
    }
}
