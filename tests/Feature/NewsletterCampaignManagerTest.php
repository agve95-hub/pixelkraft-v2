<?php

namespace Tests\Feature;

use App\Models\NewsletterCampaign;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class NewsletterCampaignManagerTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $email = 'camp@example.com'): User
    {
        return User::create([
            'name' => 'Camp User',
            'email' => $email,
            'password' => Hash::make('password'),
            'role' => 'editor',
        ]);
    }

    private function makeSite(User $user): Site
    {
        return Site::create([
            'user_id' => $user->id,
            'name' => 'Camp Site',
            'slug' => 'camp-site',
            'repo_url' => 'https://github.com/example/camp',
            'branch' => 'main',
            'project_type' => 'static_html',
        ]);
    }

    public function test_campaigns_page_loads(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);

        $this->actingAs($user)
            ->get("/dashboard/sites/{$site->id}/newsletters")
            ->assertOk();
    }

    public function test_owner_can_create_draft_campaign(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);

        $this->actingAs($user)
            ->postJson("/dashboard/sites/{$site->id}/newsletters", [
                'subject' => 'Hello world',
                'body_html' => '<p>Hi!</p>',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('newsletter_campaigns', [
            'site_id' => $site->id,
            'subject' => 'Hello world',
            'status' => 'draft',
        ]);
    }

    public function test_campaign_with_scheduled_at_gets_scheduled_status(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);

        $this->actingAs($user)
            ->postJson("/dashboard/sites/{$site->id}/newsletters", [
                'subject' => 'Scheduled',
                'scheduled_at' => now()->addDay()->toDateTimeString(),
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('newsletter_campaigns', [
            'site_id' => $site->id,
            'subject' => 'Scheduled',
            'status' => 'scheduled',
        ]);
    }

    public function test_owner_can_update_draft_campaign(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);

        $campaign = NewsletterCampaign::create([
            'site_id' => $site->id,
            'subject' => 'Old subject',
            'body_html' => '<p>Old</p>',
            'status' => 'draft',
        ]);

        $this->actingAs($user)
            ->putJson("/dashboard/sites/{$site->id}/newsletters/{$campaign->id}", [
                'subject' => 'New subject',
            ])
            ->assertRedirect();

        $this->assertSame('New subject', $campaign->fresh()->subject);
    }

    public function test_sent_campaign_cannot_be_edited(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);

        $campaign = NewsletterCampaign::create([
            'site_id' => $site->id,
            'subject' => 'Done',
            'body_html' => '<p>Done</p>',
            'status' => 'sent',
        ]);

        $this->actingAs($user)
            ->putJson("/dashboard/sites/{$site->id}/newsletters/{$campaign->id}", [
                'subject' => 'Changed',
            ])
            ->assertStatus(403);

        $this->assertSame('Done', $campaign->fresh()->subject);
    }

    public function test_send_now_transitions_draft_to_sending(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);

        $campaign = NewsletterCampaign::create([
            'site_id' => $site->id,
            'subject' => 'Ready',
            'body_html' => '<p>Ready</p>',
            'status' => 'draft',
        ]);

        $this->actingAs($user)
            ->postJson("/dashboard/sites/{$site->id}/newsletters/{$campaign->id}/send")
            ->assertRedirect();

        $this->assertSame('sending', $campaign->fresh()->status);
    }

    public function test_sent_campaign_cannot_be_sent_again(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);

        $campaign = NewsletterCampaign::create([
            'site_id' => $site->id,
            'subject' => 'Done',
            'body_html' => '<p>Done</p>',
            'status' => 'sent',
        ]);

        $this->actingAs($user)
            ->postJson("/dashboard/sites/{$site->id}/newsletters/{$campaign->id}/send")
            ->assertStatus(403);
    }

    public function test_owner_can_delete_draft_campaign(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);

        $campaign = NewsletterCampaign::create([
            'site_id' => $site->id,
            'subject' => 'Deletable',
            'body_html' => '<p>Deletable</p>',
            'status' => 'draft',
        ]);

        $this->actingAs($user)
            ->deleteJson("/dashboard/sites/{$site->id}/newsletters/{$campaign->id}")
            ->assertRedirect();

        $this->assertDatabaseMissing('newsletter_campaigns', ['id' => $campaign->id]);
    }

    public function test_sent_campaign_cannot_be_deleted(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);

        $campaign = NewsletterCampaign::create([
            'site_id' => $site->id,
            'subject' => 'Permanent',
            'body_html' => '<p>Permanent</p>',
            'status' => 'sent',
        ]);

        $this->actingAs($user)
            ->deleteJson("/dashboard/sites/{$site->id}/newsletters/{$campaign->id}")
            ->assertStatus(403);

        $this->assertDatabaseHas('newsletter_campaigns', ['id' => $campaign->id]);
    }

    public function test_user_cannot_access_another_sites_campaigns(): void
    {
        $owner = $this->makeUser('owner@c.com');
        $other = $this->makeUser('other@c.com');
        $site = $this->makeSite($owner);

        $campaign = NewsletterCampaign::create([
            'site_id' => $site->id,
            'subject' => 'Private',
            'body_html' => '<p>x</p>',
            'status' => 'draft',
        ]);

        $this->actingAs($other)
            ->deleteJson("/dashboard/sites/{$site->id}/newsletters/{$campaign->id}")
            ->assertStatus(404);
    }

    public function test_subject_is_required(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);

        $this->actingAs($user)
            ->postJson("/dashboard/sites/{$site->id}/newsletters", [
                'subject' => '',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['subject']);
    }
}
