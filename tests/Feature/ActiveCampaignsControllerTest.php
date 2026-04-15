<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\Campaign;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ActiveCampaignsControllerTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(): User
    {
        static $n = 0;
        $n++;

        return User::create([
            'name' => "User {$n}",
            'email' => "user{$n}-ac@example.com",
            'password' => Hash::make('password'),
            'role' => 'editor',
        ]);
    }

    private function makeSite(User $user, string $suffix = 'ac'): Site
    {
        return Site::create([
            'user_id' => $user->id,
            'name' => "Site {$suffix}",
            'slug' => "site-{$suffix}",
            'repo_url' => 'https://github.com/example/ac.git',
            'branch' => 'main',
        ]);
    }

    public function test_returns_active_campaigns_and_announcements(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user, 'ret');

        Campaign::create([
            'site_id' => $site->id,
            'name' => 'Active Campaign',
            'headline' => 'Big Sale!',
            'body' => 'Save 50%',
            'trigger' => 'on_delay',
            'trigger_delay_ms' => 3000,
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addHour(),
            'priority' => 1,
            'is_dismissible' => true,
            'is_enabled' => true,
        ]);

        Announcement::create([
            'site_id' => $site->id,
            'message' => 'Welcome banner',
            'style' => 'info',
            'placement' => 'top_bar',
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addHour(),
            'priority' => 1,
            'is_dismissible' => false,
            'is_enabled' => true,
        ]);

        $response = $this->getJson("/api/sites/{$site->id}/active-campaigns");

        $response->assertOk()
            ->assertJsonCount(1, 'campaigns')
            ->assertJsonCount(1, 'announcements')
            ->assertJsonPath('campaigns.0.headline', 'Big Sale!')
            ->assertJsonPath('announcements.0.message', 'Welcome banner');
    }

    public function test_excludes_disabled_campaigns(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user, 'dis');

        Campaign::create([
            'site_id' => $site->id,
            'name' => 'Disabled',
            'headline' => 'Disabled',
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addHour(),
            'priority' => 1,
            'is_dismissible' => false,
            'is_enabled' => false,
        ]);

        $this->getJson("/api/sites/{$site->id}/active-campaigns")
            ->assertOk()
            ->assertJsonCount(0, 'campaigns');
    }

    public function test_excludes_expired_campaigns(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user, 'exp');

        Campaign::create([
            'site_id' => $site->id,
            'name' => 'Expired',
            'headline' => 'Expired',
            'starts_at' => now()->subDay(),
            'ends_at' => now()->subHour(),
            'priority' => 1,
            'is_dismissible' => false,
            'is_enabled' => true,
        ]);

        $this->getJson("/api/sites/{$site->id}/active-campaigns")
            ->assertOk()
            ->assertJsonCount(0, 'campaigns');
    }

    public function test_excludes_future_campaigns(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user, 'fut');

        Campaign::create([
            'site_id' => $site->id,
            'name' => 'Future',
            'headline' => 'Future',
            'starts_at' => now()->addHour(),
            'ends_at' => now()->addDay(),
            'priority' => 1,
            'is_dismissible' => false,
            'is_enabled' => true,
        ]);

        $this->getJson("/api/sites/{$site->id}/active-campaigns")
            ->assertOk()
            ->assertJsonCount(0, 'campaigns');
    }

    public function test_response_is_served_from_cache_on_second_request(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user, 'cache');

        Cache::flush();

        $cacheKey = "active-campaigns:{$site->id}";
        $this->assertFalse(Cache::has($cacheKey));

        $this->getJson("/api/sites/{$site->id}/active-campaigns")->assertOk();

        $this->assertTrue(Cache::has($cacheKey));
    }

    public function test_campaigns_ordered_by_priority_descending(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user, 'ord');

        Campaign::create([
            'site_id' => $site->id,
            'name' => 'Low priority',
            'headline' => 'Low',
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addHour(),
            'priority' => 1,
            'is_dismissible' => false,
            'is_enabled' => true,
        ]);

        Campaign::create([
            'site_id' => $site->id,
            'name' => 'High priority',
            'headline' => 'High',
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addHour(),
            'priority' => 10,
            'is_dismissible' => false,
            'is_enabled' => true,
        ]);

        $response = $this->getJson("/api/sites/{$site->id}/active-campaigns")->assertOk();
        $campaigns = $response->json('campaigns');

        $this->assertSame('High', $campaigns[0]['headline']);
        $this->assertSame('Low', $campaigns[1]['headline']);
    }

    public function test_returns_404_for_unknown_site(): void
    {
        $this->getJson('/api/sites/00000000-0000-0000-0000-000000000000/active-campaigns')
            ->assertNotFound();
    }
}
