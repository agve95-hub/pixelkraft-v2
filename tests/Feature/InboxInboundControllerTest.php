<?php

namespace Tests\Feature;

use App\Models\Site;
use App\Models\SiteInboxMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InboxInboundControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_accepts_payload_when_secret_not_required(): void
    {
        config(['pixelkraft.inbox_inbound_require_secret' => false]);

        $site = Site::create([
            'name' => 'Inbox Site',
            'slug' => 'inbox-site',
            'repo_url' => 'https://github.com/example/inbox.git',
            'branch' => 'main',
            'is_active' => true,
        ]);

        $this->postJson('/api/inbox/inbox-site', [
            'from_email' => 'relay@example.com',
            'subject' => 'Forwarded message',
            'body' => 'Body text here.',
        ])->assertCreated()
            ->assertJson(['status' => 'ok']);

        $this->assertTrue(
            SiteInboxMessage::query()
                ->where('site_id', $site->id)
                ->where('source', 'webhook')
                ->where('subject', 'Forwarded message')
                ->exists()
        );
    }

    public function test_rejects_wrong_bearer_when_secret_configured(): void
    {
        config([
            'pixelkraft.inbox_inbound_require_secret' => true,
            'pixelkraft.inbox_inbound_secret' => 'correct-token-that-is-32-chars-ok',
        ]);

        Site::create([
            'name' => 'Secured',
            'slug' => 'secured-inbox',
            'repo_url' => 'https://github.com/example/sec.git',
            'branch' => 'main',
            'is_active' => true,
        ]);

        $this->withHeaders(['Authorization' => 'Bearer wrong-token'])
            ->postJson('/api/inbox/secured-inbox', [
                'subject' => 'S',
                'body' => 'B',
            ])
            ->assertUnauthorized();
    }

    public function test_accepts_valid_bearer_token(): void
    {
        config([
            'pixelkraft.inbox_inbound_require_secret' => true,
            'pixelkraft.inbox_inbound_secret' => 'correct-token-that-is-32-chars-ok',
        ]);

        $site = Site::create([
            'name' => 'Secured OK',
            'slug' => 'secured-ok',
            'repo_url' => 'https://github.com/example/sok.git',
            'branch' => 'main',
            'is_active' => true,
        ]);

        $this->withHeaders(['Authorization' => 'Bearer correct-token-that-is-32-chars-ok'])
            ->postJson('/api/inbox/secured-ok', [
                'subject' => 'API inbound',
                'body' => 'Content',
            ])
            ->assertCreated();

        $this->assertTrue(
            SiteInboxMessage::query()->where('site_id', $site->id)->where('subject', 'API inbound')->exists()
        );
    }

    public function test_per_site_secret_used_when_global_unset(): void
    {
        config([
            'pixelkraft.inbox_inbound_require_secret' => true,
            'pixelkraft.inbox_inbound_secret' => null,
        ]);

        $site = Site::create([
            'name' => 'Site Token',
            'slug' => 'site-token-inbox',
            'repo_url' => 'https://github.com/example/sti.git',
            'branch' => 'main',
            'is_active' => true,
            'inbox_inbound_secret' => 'per-site-inbox-secret-32chars-min',
        ]);

        $this->withHeaders(['Authorization' => 'Bearer per-site-inbox-secret-32chars-min'])
            ->postJson('/api/inbox/site-token-inbox', [
                'subject' => 'Per-site',
                'body' => 'Hello',
            ])
            ->assertCreated();

        $this->assertTrue(
            SiteInboxMessage::query()->where('site_id', $site->id)->where('subject', 'Per-site')->exists()
        );
    }

    public function test_per_site_secret_takes_precedence_over_global(): void
    {
        config([
            'pixelkraft.inbox_inbound_require_secret' => true,
            'pixelkraft.inbox_inbound_secret' => 'global-inbox-secret-32-chars-min-ok',
        ]);

        $site = Site::create([
            'name' => 'Dual',
            'slug' => 'dual-inbox',
            'repo_url' => 'https://github.com/example/dual.git',
            'branch' => 'main',
            'is_active' => true,
            'inbox_inbound_secret' => 'site-specific-inbox-secret-32chars-ok',
        ]);

        $this->withHeaders(['Authorization' => 'Bearer site-specific-inbox-secret-32chars-ok'])
            ->postJson('/api/inbox/dual-inbox', [
                'subject' => 'Site auth',
                'body' => 'B',
            ])
            ->assertCreated();

        $this->withHeaders(['Authorization' => 'Bearer global-inbox-secret-32-chars-min-ok'])
            ->postJson('/api/inbox/dual-inbox', [
                'subject' => 'Wrong for this site',
                'body' => 'B',
            ])
            ->assertUnauthorized();
    }
}
