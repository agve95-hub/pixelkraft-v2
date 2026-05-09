<?php

namespace Tests\Feature;

use App\Jobs\DeploySiteJob;
use App\Jobs\ParseSiteJob;
use App\Jobs\SyncFromWebhookJob;
use App\Models\Site;
use App\Models\WebhookDelivery;
use App\Services\GitSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

/**
 * End-to-end pipeline test: GitHub push webhook → SyncFromWebhookJob → ParseSiteJob → DeploySiteJob.
 *
 * Scope: verifies that a correctly-signed push event propagates through every
 * queued stage and updates the expected database records.  Git and file-system
 * operations are mocked so no real repositories or SSH connections are needed.
 */
class WebhookPipelineE2ETest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Full happy-path flow:
     *   POST /api/webhooks/github
     *     → WebhookDelivery recorded
     *     → SyncFromWebhookJob dispatched (asserted via Queue::fake)
     *   SyncFromWebhookJob::handle() (run synchronously)
     *     → git pull returns true (has changes)
     *     → ParseSiteJob dispatched
     *     → DeploySiteJob dispatched (site has deploy_on_webhook=true)
     *     → WebhookDelivery status updated to "processed"
     */
    public function test_push_webhook_triggers_sync_parse_and_deploy_for_webhook_deploy_site(): void
    {
        config()->set('platform.github_webhook_require_signature', true);
        config()->set('platform.github_webhook_secret', 'test-global-secret');

        $site = Site::create([
            'name' => 'E2E Pipeline Site',
            'slug' => 'e2e-pipeline',
            'repo_url' => 'https://github.com/acme/e2e.git',
            'branch' => 'main',
            'deploy_on_webhook' => true,
            'is_active' => true,
        ]);

        // ── Step 1: POST webhook → SyncFromWebhookJob dispatched ──────────

        Queue::fake();

        $payload = $this->makePushPayload('acme/e2e', 'refs/heads/main');
        $body = json_encode($payload, JSON_THROW_ON_ERROR);

        $response = $this
            ->withHeaders([
                'X-GitHub-Event' => 'push',
                'X-GitHub-Delivery' => 'e2e-delivery-001',
                'X-Hub-Signature-256' => 'sha256='.hash_hmac('sha256', $body, 'test-global-secret'),
                'Accept' => 'application/json',
            ])
            ->postJson('/api/webhooks/github', $payload);

        $response->assertOk()->assertJson(['status' => 'ok', 'dispatched' => 1]);

        // Delivery must be recorded in the database.
        $this->assertDatabaseHas('webhook_deliveries', [
            'delivery_id' => 'e2e-delivery-001',
            'provider' => 'github',
            'event' => 'push',
            'repository' => 'acme/e2e',
            'status' => 'received',
        ]);

        Queue::assertPushed(
            SyncFromWebhookJob::class,
            fn (SyncFromWebhookJob $job) => $job->site->id === $site->id
                && $job->deliveryId === 'e2e-delivery-001'
        );

        // ── Step 2: Handle SyncFromWebhookJob (run inline, new Queue::fake) ──

        Queue::fake();

        $git = Mockery::mock(GitSyncService::class);
        // pull() returns true → repo had new commits
        $git->shouldReceive('pull')
            ->once()
            ->with(Mockery::on(fn ($s) => $s->id === $site->id))
            ->andReturn(true);

        $this->app->instance(GitSyncService::class, $git);

        $job = new SyncFromWebhookJob($site, $payload, 'e2e-delivery-001');
        $job->handle($git);

        Queue::assertPushed(
            ParseSiteJob::class,
            fn (ParseSiteJob $j) => $j->site->id === $site->id
        );

        Queue::assertPushed(
            DeploySiteJob::class,
            fn (DeploySiteJob $j) => $j->site->id === $site->id && $j->triggeredBy === 'webhook'
        );

        // Delivery status must be updated to "processed".
        $this->assertDatabaseHas('webhook_deliveries', [
            'delivery_id' => 'e2e-delivery-001',
            'status' => 'processed',
        ]);
    }

    /**
     * When git pull returns false (repo already up-to-date), neither ParseSiteJob
     * nor DeploySiteJob should be dispatched and the delivery is marked "ignored".
     */
    public function test_push_webhook_skips_jobs_when_no_new_commits(): void
    {
        config()->set('platform.github_webhook_require_signature', false);

        $site = Site::create([
            'name' => 'Up To Date',
            'slug' => 'up-to-date',
            'repo_url' => 'https://github.com/acme/utd.git',
            'branch' => 'main',
            'deploy_on_webhook' => true,
            'is_active' => true,
        ]);

        Queue::fake();

        $git = Mockery::mock(GitSyncService::class);
        $git->shouldReceive('pull')->once()->andReturn(false); // no changes

        $this->app->instance(GitSyncService::class, $git);

        $job = new SyncFromWebhookJob($site, $this->makePushPayload(), 'no-change-delivery');

        // Seed a delivery record so markDeliveryProcessed can find it.
        WebhookDelivery::create([
            'provider' => 'github',
            'delivery_id' => 'no-change-delivery',
            'event' => 'push',
            'repository' => 'acme/utd',
            'status' => 'received',
            'received_at' => now(),
        ]);

        $job->handle($git);

        Queue::assertNotPushed(ParseSiteJob::class);
        Queue::assertNotPushed(DeploySiteJob::class);

        $this->assertDatabaseHas('webhook_deliveries', [
            'delivery_id' => 'no-change-delivery',
            'status' => 'ignored',
        ]);
    }

    /**
     * When deploy_on_webhook is false, ParseSiteJob fires but DeploySiteJob does not.
     */
    public function test_push_webhook_does_not_deploy_when_auto_deploy_disabled(): void
    {
        config()->set('platform.github_webhook_require_signature', false);

        $site = Site::create([
            'name' => 'No Auto Deploy',
            'slug' => 'no-auto-deploy',
            'repo_url' => 'https://github.com/acme/nad.git',
            'branch' => 'main',
            'deploy_on_webhook' => false,
            'is_active' => true,
        ]);

        Queue::fake();

        $git = Mockery::mock(GitSyncService::class);
        $git->shouldReceive('pull')->once()->andReturn(true);

        $this->app->instance(GitSyncService::class, $git);

        $job = new SyncFromWebhookJob($site, $this->makePushPayload('acme/nad'), null);
        $job->handle($git);

        Queue::assertPushed(ParseSiteJob::class);
        Queue::assertNotPushed(DeploySiteJob::class);
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    private function makePushPayload(string $fullName = 'acme/e2e', string $ref = 'refs/heads/main'): array
    {
        return [
            'ref' => $ref,
            'repository' => [
                'full_name' => $fullName,
                'clone_url' => "https://github.com/{$fullName}.git",
            ],
            'pusher' => ['name' => 'octocat'],
            'head_commit' => ['message' => 'ci: trigger build', 'id' => 'abc1234567890'],
        ];
    }
}
