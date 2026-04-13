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
            'pixelkraft.inbox_inbound_secret' => 'correct-token',
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
            'pixelkraft.inbox_inbound_secret' => 'correct-token',
        ]);

        $site = Site::create([
            'name' => 'Secured OK',
            'slug' => 'secured-ok',
            'repo_url' => 'https://github.com/example/sok.git',
            'branch' => 'main',
            'is_active' => true,
        ]);

        $this->withHeaders(['Authorization' => 'Bearer correct-token'])
            ->postJson('/api/inbox/secured-ok', [
                'subject' => 'API inbound',
                'body' => 'Content',
            ])
            ->assertCreated();

        $this->assertTrue(
            SiteInboxMessage::query()->where('site_id', $site->id)->where('subject', 'API inbound')->exists()
        );
    }
}
