<?php

namespace Tests\Unit;

use App\Jobs\DeploySiteJob;
use App\Jobs\ParseSiteJob;
use App\Jobs\SyncFromWebhookJob;
use App\Models\Site;
use App\Models\User;
use App\Models\WebhookDelivery;
use App\Services\GitConflictException;
use App\Services\GitSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SyncFromWebhookJobTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(): User
    {
        return User::create([
            'name' => 'U',
            'email' => 'sfwj-'.uniqid().'@x.com',
            'password' => Hash::make('secret'),
            'role' => 'admin',
        ]);
    }

    private function makeSite(User $user, array $attrs = []): Site
    {
        return Site::create(array_merge([
            'user_id' => $user->id,
            'name' => 'S',
            'slug' => 'sfwj-'.uniqid(),
            'repo_url' => 'https://github.com/example/sfwj',
            'branch' => 'main',
            'project_type' => 'static_html',
            'deploy_on_webhook' => false,
        ], $attrs));
    }

    private function makeDelivery(Site $site): WebhookDelivery
    {
        return WebhookDelivery::create([
            'provider' => 'github',
            'delivery_id' => 'dlv-'.uniqid(),
            'event' => 'push',
            'repository' => 'https://github.com/example/sfwj',
            'site_id' => $site->id,
            'status' => 'received',
            'payload' => [],
            'received_at' => now(),
        ]);
    }

    private function samplePayload(): array
    {
        return [
            'ref' => 'refs/heads/main',
            'head_commit' => ['message' => 'Fix typo'],
            'pusher' => ['name' => 'alice'],
        ];
    }

    // ── no changes ───────────────────────────────

    public function test_does_not_dispatch_jobs_when_pull_returns_no_changes(): void
    {
        Bus::fake();

        $user = $this->makeUser();
        $site = $this->makeSite($user);

        $git = $this->mock(GitSyncService::class);
        $git->shouldReceive('pull')->andReturn(false);

        $job = new SyncFromWebhookJob($site, $this->samplePayload());
        $job->handle($git);

        Bus::assertNotDispatched(ParseSiteJob::class);
        Bus::assertNotDispatched(DeploySiteJob::class);
    }

    // ── has changes ──────────────────────────────

    public function test_dispatches_parse_job_when_pull_returns_changes(): void
    {
        Bus::fake();

        $user = $this->makeUser();
        $site = $this->makeSite($user);

        $git = $this->mock(GitSyncService::class);
        $git->shouldReceive('pull')->andReturn(true);

        $job = new SyncFromWebhookJob($site, $this->samplePayload());
        $job->handle($git);

        Bus::assertDispatched(ParseSiteJob::class);
    }

    public function test_dispatches_deploy_job_when_deploy_on_webhook_is_true(): void
    {
        Bus::fake();

        $user = $this->makeUser();
        $site = $this->makeSite($user, ['deploy_on_webhook' => true]);

        $git = $this->mock(GitSyncService::class);
        $git->shouldReceive('pull')->andReturn(true);

        $job = new SyncFromWebhookJob($site, $this->samplePayload());
        $job->handle($git);

        Bus::assertDispatched(ParseSiteJob::class);
        Bus::assertDispatched(DeploySiteJob::class);
    }

    public function test_does_not_dispatch_deploy_when_deploy_on_webhook_is_false(): void
    {
        Bus::fake();

        $user = $this->makeUser();
        $site = $this->makeSite($user, ['deploy_on_webhook' => false]);

        $git = $this->mock(GitSyncService::class);
        $git->shouldReceive('pull')->andReturn(true);

        $job = new SyncFromWebhookJob($site, $this->samplePayload());
        $job->handle($git);

        Bus::assertNotDispatched(DeploySiteJob::class);
    }

    // ── conflict handling ────────────────────────

    public function test_creates_conflict_notification_and_does_not_rethrow(): void
    {
        Bus::fake();

        $user = $this->makeUser();
        $site = $this->makeSite($user);

        $git = $this->mock(GitSyncService::class);
        $git->shouldReceive('pull')->andThrow(new GitConflictException('conflict detected'));

        $job = new SyncFromWebhookJob($site, $this->samplePayload());
        $job->handle($git);

        $this->assertDatabaseHas('notifications', [
            'site_id' => $site->id,
            'type' => 'deploy_failed',
        ]);

        Bus::assertNotDispatched(DeploySiteJob::class);
    }

    // ── generic failure ──────────────────────────

    public function test_creates_notification_and_rethrows_on_generic_failure(): void
    {
        Bus::fake();

        $user = $this->makeUser();
        $site = $this->makeSite($user);

        $git = $this->mock(GitSyncService::class);
        $git->shouldReceive('pull')->andThrow(new \RuntimeException('network error'));

        $this->expectException(\RuntimeException::class);

        $job = new SyncFromWebhookJob($site, $this->samplePayload());
        $job->handle($git);

        $this->assertDatabaseHas('notifications', ['site_id' => $site->id]);
    }

    public function test_scrubs_git_token_from_error_notification(): void
    {
        Bus::fake();

        $user = $this->makeUser();
        $site = $this->makeSite($user);

        $git = $this->mock(GitSyncService::class);
        $git->shouldReceive('pull')
            ->andThrow(new \RuntimeException('error: x-access-token:ghp_MyS3cr3tToken@github.com'));

        try {
            (new SyncFromWebhookJob($site, $this->samplePayload()))->handle($git);
        } catch (\RuntimeException) {
        }

        $notification = \App\Models\Notification::where('site_id', $site->id)->first();
        $this->assertNotNull($notification);
        $this->assertStringNotContainsString('ghp_MyS3cr3tToken', (string) $notification->body);
        $this->assertStringContainsString('[REDACTED]', (string) $notification->body);
    }

    // ── delivery marking ─────────────────────────

    public function test_marks_delivery_processed_after_successful_pull_with_changes(): void
    {
        Bus::fake();

        $user = $this->makeUser();
        $site = $this->makeSite($user);
        $delivery = $this->makeDelivery($site);

        $git = $this->mock(GitSyncService::class);
        $git->shouldReceive('pull')->andReturn(true);

        $job = new SyncFromWebhookJob($site, $this->samplePayload(), $delivery->delivery_id);
        $job->handle($git);

        $this->assertDatabaseHas('webhook_deliveries', [
            'delivery_id' => $delivery->delivery_id,
            'status' => 'processed',
        ]);
        $this->assertNotNull($delivery->fresh()->processed_at);
    }

    public function test_marks_delivery_ignored_when_no_changes(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);
        $delivery = $this->makeDelivery($site);

        $git = $this->mock(GitSyncService::class);
        $git->shouldReceive('pull')->andReturn(false);

        $job = new SyncFromWebhookJob($site, $this->samplePayload(), $delivery->delivery_id);
        $job->handle($git);

        $this->assertDatabaseHas('webhook_deliveries', [
            'delivery_id' => $delivery->delivery_id,
            'status' => 'ignored',
        ]);
    }

    public function test_marks_delivery_conflict_on_git_conflict(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);
        $delivery = $this->makeDelivery($site);

        $git = $this->mock(GitSyncService::class);
        $git->shouldReceive('pull')->andThrow(new GitConflictException('conflict'));

        $job = new SyncFromWebhookJob($site, $this->samplePayload(), $delivery->delivery_id);
        $job->handle($git);

        $this->assertDatabaseHas('webhook_deliveries', [
            'delivery_id' => $delivery->delivery_id,
            'status' => 'conflict',
        ]);
    }

    // ── tags ─────────────────────────────────────

    public function test_job_has_correct_queue_tags(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);

        $job = new SyncFromWebhookJob($site, []);

        $this->assertContains('webhook-sync', $job->tags());
        $this->assertContains("site:{$site->id}", $job->tags());
    }
}
