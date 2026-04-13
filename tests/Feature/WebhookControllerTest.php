<?php

namespace Tests\Feature;

use App\Jobs\DeploySiteJob;
use App\Jobs\ParseSiteJob;
use App\Jobs\SyncFromWebhookJob;
use App\Models\Site;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

class WebhookControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_rejects_webhook_without_delivery_id(): void
    {
        config()->set('pixelkraft.github_webhook_require_signature', true);
        config()->set('pixelkraft.github_webhook_secret', 'test-secret');

        $payload = $this->pushPayload();

        $response = $this
            ->withHeaders($this->githubHeaders($payload, includeDeliveryId: false))
            ->postJson('/api/webhooks/github', $payload);

        $response
            ->assertStatus(400)
            ->assertJson(['error' => 'Missing delivery id']);
    }

    public function test_it_returns_503_when_signature_is_required_but_secret_missing(): void
    {
        config()->set('pixelkraft.github_webhook_require_signature', true);
        config()->set('pixelkraft.github_webhook_secret', null);

        $payload = $this->pushPayload();

        $response = $this
            ->withHeaders($this->githubHeaders($payload, includeSignature: false))
            ->postJson('/api/webhooks/github', $payload);

        $response
            ->assertStatus(503)
            ->assertJson(['error' => 'Webhook receiver is not configured']);
    }

    public function test_it_rejects_invalid_signature(): void
    {
        config()->set('pixelkraft.github_webhook_require_signature', true);
        config()->set('pixelkraft.github_webhook_secret', 'test-secret');

        $payload = $this->pushPayload();
        $headers = $this->githubHeaders($payload);
        $headers['X-Hub-Signature-256'] = 'sha256=invalid';

        $response = $this
            ->withHeaders($headers)
            ->postJson('/api/webhooks/github', $payload);

        $response
            ->assertStatus(403)
            ->assertJson(['error' => 'Invalid signature']);
    }

    public function test_it_dispatches_sync_only_for_exact_repo_and_matching_branch(): void
    {
        config()->set('pixelkraft.github_webhook_require_signature', true);
        config()->set('pixelkraft.github_webhook_secret', 'test-secret');

        $targetSite = Site::create([
            'name' => 'Target',
            'slug' => 'target',
            'repo_url' => 'https://github.com/acme/demo.git',
            'branch' => 'main',
        ]);

        Site::create([
            'name' => 'Similar Name',
            'slug' => 'similar-name',
            'repo_url' => 'https://github.com/acme/demo-extra.git',
            'branch' => 'main',
        ]);

        Site::create([
            'name' => 'Wrong Branch',
            'slug' => 'wrong-branch',
            'repo_url' => 'git@github.com:acme/demo.git',
            'branch' => 'develop',
        ]);

        Queue::fake();

        $payload = $this->pushPayload(fullName: 'Acme/Demo', ref: 'refs/heads/main');
        $response = $this
            ->withHeaders($this->githubHeaders($payload, deliveryId: 'delivery-exact'))
            ->postJson('/api/webhooks/github', $payload);

        $response
            ->assertOk()
            ->assertJson([
                'status' => 'ok',
                'dispatched' => 1,
            ]);

        Queue::assertPushed(SyncFromWebhookJob::class, function (SyncFromWebhookJob $job) use ($targetSite) {
            return $job->site->id === $targetSite->id;
        });
        Queue::assertPushed(SyncFromWebhookJob::class, 1);
    }

    public function test_it_ignores_duplicate_delivery_ids(): void
    {
        config()->set('pixelkraft.github_webhook_require_signature', true);
        config()->set('pixelkraft.github_webhook_secret', 'test-secret');

        Site::create([
            'name' => 'Target',
            'slug' => 'target',
            'repo_url' => 'https://github.com/acme/demo.git',
            'branch' => 'main',
        ]);

        Queue::fake();

        $payload = $this->pushPayload();
        $headers = $this->githubHeaders($payload, deliveryId: 'dup-delivery');

        $this->withHeaders($headers)->postJson('/api/webhooks/github', $payload)
            ->assertOk()
            ->assertJson(['status' => 'ok', 'dispatched' => 1]);

        $this->withHeaders($headers)->postJson('/api/webhooks/github', $payload)
            ->assertOk()
            ->assertJson(['status' => 'duplicate', 'delivery_id' => 'dup-delivery']);

        Queue::assertPushed(SyncFromWebhookJob::class, 1);
    }

    public function test_it_requires_refs_heads_branch_format(): void
    {
        config()->set('pixelkraft.github_webhook_require_signature', true);
        config()->set('pixelkraft.github_webhook_secret', 'test-secret');

        Site::create([
            'name' => 'Target',
            'slug' => 'target',
            'repo_url' => 'https://github.com/acme/demo.git',
            'branch' => 'main',
        ]);

        Queue::fake();

        $payload = $this->pushPayload(ref: 'refs/tags/v1.0.0');
        $response = $this
            ->withHeaders($this->githubHeaders($payload, deliveryId: 'bad-ref'))
            ->postJson('/api/webhooks/github', $payload);

        $response
            ->assertStatus(400)
            ->assertJson(['error' => 'Missing branch ref']);

        Queue::assertNotPushed(SyncFromWebhookJob::class);
    }

    public function test_it_accepts_site_scoped_webhook_route_and_records_site_id(): void
    {
        config()->set('pixelkraft.github_webhook_require_signature', true);
        config()->set('pixelkraft.github_webhook_secret', 'test-secret');

        $site = Site::create([
            'name' => 'Scoped',
            'slug' => 'scoped',
            'repo_url' => 'https://github.com/acme/demo.git',
            'branch' => 'main',
        ]);

        Queue::fake();

        $payload = $this->pushPayload();
        $response = $this
            ->withHeaders($this->githubHeaders($payload, deliveryId: 'scoped-delivery'))
            ->postJson(route('webhooks.github.site', ['site' => $site]), $payload);

        $response
            ->assertOk()
            ->assertJson([
                'status' => 'ok',
                'dispatched' => 1,
            ]);

        $this->assertDatabaseHas('webhook_deliveries', [
            'delivery_id' => 'scoped-delivery',
            'site_id' => $site->id,
            'status' => 'received',
        ]);
    }

    public function test_sync_job_dispatches_deploy_when_site_is_configured_for_webhook_deploy(): void
    {
        $site = Site::create([
            'name' => 'Deploy On Webhook',
            'slug' => 'deploy-on-webhook',
            'repo_url' => 'https://github.com/acme/demo.git',
            'branch' => 'main',
            'deploy_on_webhook' => true,
        ]);

        Queue::fake();

        $job = new SyncFromWebhookJob($site, [
            'pusher' => ['name' => 'octocat'],
            'head_commit' => ['message' => 'Webhook update'],
        ]);

        $git = Mockery::mock(\App\Services\GitSyncService::class);
        $git->shouldReceive('pull')->once()->withArgs(function (Site $passedSite) use ($site) {
            return $passedSite->id === $site->id;
        })->andReturn(true);

        $job->handle($git);

        Queue::assertPushed(ParseSiteJob::class, function (ParseSiteJob $queued) use ($site) {
            return $queued->site->id === $site->id;
        });
        Queue::assertPushed(DeploySiteJob::class, function (DeploySiteJob $queued) use ($site) {
            return $queued->site->id === $site->id && $queued->triggeredBy === 'webhook';
        });
    }

    private function pushPayload(string $fullName = 'acme/demo', string $ref = 'refs/heads/main'): array
    {
        return [
            'ref' => $ref,
            'repository' => [
                'full_name' => $fullName,
                'clone_url' => "https://github.com/{$fullName}.git",
            ],
            'pusher' => ['name' => 'octocat'],
            'head_commit' => ['message' => 'Update content'],
        ];
    }

    private function githubHeaders(
        array $payload,
        string $deliveryId = 'delivery-1',
        bool $includeSignature = true,
        bool $includeDeliveryId = true,
    ): array {
        $headers = [
            'X-GitHub-Event' => 'push',
            'Accept' => 'application/json',
        ];

        if ($includeDeliveryId) {
            $headers['X-GitHub-Delivery'] = $deliveryId;
        }

        if ($includeSignature) {
            $body = json_encode($payload, JSON_THROW_ON_ERROR);
            $secret = (string) config('pixelkraft.github_webhook_secret', '');
            $headers['X-Hub-Signature-256'] = 'sha256=' . hash_hmac('sha256', $body, $secret);
        }

        return $headers;
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
