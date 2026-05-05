<?php

namespace Tests\Feature;

use App\Models\Site;
use App\Models\SiteInboxMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SiteInboxActionsTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $email = 'inbox@example.com'): User
    {
        return User::create([
            'name' => 'Inbox User',
            'email' => $email,
            'password' => Hash::make('password'),
            'role' => 'editor',
        ]);
    }

    private function makeSite(User $user): Site
    {
        return Site::create([
            'user_id' => $user->id,
            'name' => 'Inbox Site',
            'slug' => 'inbox-site-'.uniqid(),
            'repo_url' => 'https://github.com/example/inbox',
            'branch' => 'main',
            'project_type' => 'static_html',
        ]);
    }

    private function makeMessage(Site $site, array $attrs = []): SiteInboxMessage
    {
        return SiteInboxMessage::create(array_merge([
            'site_id' => $site->id,
            'direction' => 'inbound',
            'from_email' => 'visitor@example.com',
            'subject' => 'Hello',
            'body' => 'Hi there!',
            'is_read' => false,
            'is_archived' => false,
        ], $attrs));
    }

    public function test_owner_can_compose_outbound_message(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);

        $this->actingAs($user)
            ->post(route('sites.inbox.compose', $site), [
                'to_email' => 'client@example.com',
                'subject' => 'Invoice ready',
                'body' => 'Your invoice is attached.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('site_inbox_messages', [
            'site_id' => $site->id,
            'direction' => 'outbound',
            'to_email' => 'client@example.com',
            'subject' => 'Invoice ready',
            'is_read' => true,
        ]);
    }

    public function test_compose_requires_valid_email(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);

        $this->actingAs($user)
            ->postJson(route('sites.inbox.compose', $site), [
                'to_email' => 'not-an-email',
                'subject' => 'Test',
                'body' => 'Body',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['to_email']);
    }

    public function test_owner_can_archive_message(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);
        $message = $this->makeMessage($site);

        $this->actingAs($user)
            ->post(route('sites.inbox.archive', [$site, $message]))
            ->assertRedirect();

        $this->assertTrue((bool) $message->fresh()->is_archived);

        // Unarchive
        $this->actingAs($user)
            ->post(route('sites.inbox.archive', [$site, $message]))
            ->assertRedirect();

        $this->assertFalse((bool) $message->fresh()->is_archived);
    }

    public function test_owner_can_mark_message_as_read(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);
        $message = $this->makeMessage($site, ['is_read' => false]);

        $this->actingAs($user)
            ->post(route('sites.inbox.read', [$site, $message]))
            ->assertRedirect();

        $this->assertTrue((bool) $message->fresh()->is_read);
    }

    public function test_owner_can_delete_message(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);
        $message = $this->makeMessage($site);

        $this->actingAs($user)
            ->delete(route('sites.inbox.destroy', [$site, $message]))
            ->assertRedirect();

        $this->assertDatabaseMissing('site_inbox_messages', ['id' => $message->id]);
    }

    public function test_other_user_cannot_archive_message(): void
    {
        $owner = $this->makeUser('owner@i.com');
        $other = $this->makeUser('other@i.com');
        $site = $this->makeSite($owner);
        $message = $this->makeMessage($site);

        $this->actingAs($other)
            ->post(route('sites.inbox.archive', [$site, $message]))
            ->assertStatus(404);

        $this->assertFalse((bool) $message->fresh()->is_archived);
    }
}
