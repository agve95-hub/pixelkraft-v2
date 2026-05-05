<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SiteCampaignCrudTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $email = 'campaign@example.com'): User
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
            'name' => 'Campaign Site',
            'slug' => 'campaign-site-'.uniqid(),
            'repo_url' => 'https://github.com/example/camp',
            'branch' => 'main',
            'project_type' => 'static_html',
        ]);
    }

    private function makeCampaign(Site $site, array $attrs = []): Campaign
    {
        return Campaign::create(array_merge([
            'site_id' => $site->id,
            'name' => 'Test Banner',
            'trigger' => 'on_load',
            'priority' => 0,
            'locale' => 'en',
            'is_enabled' => false,
        ], $attrs));
    }

    public function test_owner_can_create_campaign(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);

        $this->actingAs($user)
            ->post(route('sites.campaigns.store', $site), [
                'name' => 'Welcome Banner',
                'headline' => 'Hello!',
                'body' => 'Welcome to our site.',
                'trigger' => 'on_load',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('campaigns', [
            'site_id' => $site->id,
            'name' => 'Welcome Banner',
            'trigger' => 'on_load',
            'is_enabled' => false,
        ]);
    }

    public function test_campaign_name_is_required(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);

        $this->actingAs($user)
            ->postJson(route('sites.campaigns.store', $site), ['trigger' => 'on_load'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_owner_can_update_campaign(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);
        $campaign = $this->makeCampaign($site);

        $this->actingAs($user)
            ->put(route('sites.campaigns.update', [$site, $campaign]), [
                'name' => 'Updated Banner',
                'trigger' => 'on_scroll',
            ])
            ->assertRedirect();

        $fresh = $campaign->fresh();
        $this->assertSame('Updated Banner', $fresh->name);
        $this->assertSame('on_scroll', $fresh->trigger);
    }

    public function test_owner_can_toggle_campaign(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);
        $campaign = $this->makeCampaign($site, ['is_enabled' => false]);

        $this->actingAs($user)
            ->post(route('sites.campaigns.toggle', [$site, $campaign]))
            ->assertRedirect();

        $this->assertTrue((bool) $campaign->fresh()->is_enabled);

        // Toggle back
        $this->actingAs($user)
            ->post(route('sites.campaigns.toggle', [$site, $campaign]))
            ->assertRedirect();

        $this->assertFalse((bool) $campaign->fresh()->is_enabled);
    }

    public function test_owner_can_duplicate_campaign(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);
        $campaign = $this->makeCampaign($site, ['name' => 'Original']);

        $this->actingAs($user)
            ->post(route('sites.campaigns.duplicate', [$site, $campaign]))
            ->assertRedirect();

        $this->assertDatabaseHas('campaigns', [
            'site_id' => $site->id,
            'name' => 'Original (copy)',
            'is_enabled' => false,
        ]);
        $this->assertSame(2, Campaign::where('site_id', $site->id)->count());
    }

    public function test_owner_can_delete_campaign(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);
        $campaign = $this->makeCampaign($site);

        $this->actingAs($user)
            ->delete(route('sites.campaigns.destroy', [$site, $campaign]))
            ->assertRedirect();

        $this->assertDatabaseMissing('campaigns', ['id' => $campaign->id]);
    }

    public function test_other_user_cannot_access_campaigns(): void
    {
        $owner = $this->makeUser('owner@c.com');
        $other = $this->makeUser('other@c.com');
        $site = $this->makeSite($owner);
        $campaign = $this->makeCampaign($site);

        $this->actingAs($other)
            ->delete(route('sites.campaigns.destroy', [$site, $campaign]))
            ->assertStatus(404);

        $this->assertDatabaseHas('campaigns', ['id' => $campaign->id]);
    }
}
