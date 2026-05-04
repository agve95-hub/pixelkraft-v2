<?php

namespace Tests\Feature;

use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SiteSettingsUpdateTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $email = 'settings@example.com'): User
    {
        return User::create([
            'name' => 'Settings User',
            'email' => $email,
            'password' => Hash::make('password'),
            'role' => 'editor',
        ]);
    }

    private function makeSite(User $user): Site
    {
        return Site::create([
            'user_id' => $user->id,
            'name' => 'Settings Site',
            'slug' => 'settings-site-'.uniqid(),
            'repo_url' => 'https://github.com/example/settings',
            'branch' => 'main',
            'project_type' => 'static_html',
        ]);
    }

    public function test_settings_page_loads(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);

        $this->actingAs($user)
            ->get(route('sites.settings', $site))
            ->assertOk()
            ->assertViewIs('dashboard.sites.settings')
            ->assertViewHas('site', fn ($s) => $s->id === $site->id);
    }

    public function test_owner_can_update_site_name(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);

        $this->actingAs($user)
            ->put(route('sites.settings.update', $site), [
                'name' => 'Renamed Site',
                'domain' => null,
                'repo_url' => 'https://github.com/example/settings',
                'branch' => 'main',
                'project_type' => 'static_html',
            ])
            ->assertRedirect();

        $this->assertSame('Renamed Site', $site->fresh()->name);
    }

    public function test_owner_can_update_client_details(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);

        $this->actingAs($user)
            ->put(route('sites.settings.update', $site), [
                'name' => $site->name,
                'client_first_name' => 'Jane',
                'client_last_name' => 'Smith',
                'client_email' => 'jane@client.com',
                'client_company' => 'Acme Corp',
            ])
            ->assertRedirect();

        $fresh = $site->fresh();
        $this->assertSame('Jane', $fresh->client_first_name);
        $this->assertSame('Acme Corp', $fresh->client_company);
    }

    public function test_repo_url_must_be_an_allowed_git_host(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);

        $this->actingAs($user)
            ->putJson(route('sites.settings.update', $site), [
                'name' => $site->name,
                'repo_url' => 'https://attacker.com/steal/tokens',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['repo_url']);

        // Name unchanged
        $this->assertSame($site->name, $site->fresh()->name);
    }

    public function test_name_is_required(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);

        $this->actingAs($user)
            ->putJson(route('sites.settings.update', $site), ['name' => ''])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_other_user_cannot_update_settings(): void
    {
        $owner = $this->makeUser('owner@s.com');
        $other = $this->makeUser('other@s.com');
        $site = $this->makeSite($owner);

        $this->actingAs($other)
            ->put(route('sites.settings.update', $site), ['name' => 'Stolen'])
            ->assertStatus(404);

        $this->assertSame($site->name, $site->fresh()->name);
    }

    public function test_unauthenticated_user_cannot_view_settings(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);

        $this->get(route('sites.settings', $site))->assertRedirect('/login');
    }
}
