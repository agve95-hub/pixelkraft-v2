<?php

namespace Tests\Feature;

use App\Models\Redirect;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SeoRedirectTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $email = 'seo@example.com'): User
    {
        return User::create([
            'name' => 'SEO User',
            'email' => $email,
            'password' => Hash::make('password'),
            'role' => 'editor',
        ]);
    }

    private function makeSite(User $user): Site
    {
        return Site::create([
            'user_id' => $user->id,
            'name' => 'SEO Site',
            'slug' => 'seo-site-'.uniqid(),
            'repo_url' => 'https://github.com/example/seo',
            'branch' => 'main',
            'project_type' => 'static_html',
        ]);
    }

    public function test_redirects_page_loads(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);

        $this->actingAs($user)
            ->get(route('seo.redirects', $site))
            ->assertOk()
            ->assertViewIs('dashboard.seo.redirects');
    }

    public function test_owner_can_create_redirect(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);

        $this->actingAs($user)
            ->post(route('seo.redirects.store', $site), [
                'from_path' => '/old-page',
                'to_path' => '/new-page',
                'status_code' => 301,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('redirects', [
            'site_id' => $site->id,
            'from_path' => '/old-page',
            'to_path' => '/new-page',
            'status_code' => 301,
            'is_active' => true,
        ]);
    }

    public function test_from_path_is_required(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);

        $this->actingAs($user)
            ->postJson(route('seo.redirects.store', $site), ['to_path' => '/new'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['from_path']);
    }

    public function test_owner_can_toggle_redirect(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);

        $redirect = Redirect::create([
            'site_id' => $site->id,
            'from_path' => '/a',
            'to_path' => '/b',
            'status_code' => 301,
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->post(route('seo.redirects.toggle', [$site, $redirect]))
            ->assertRedirect();

        $this->assertFalse((bool) $redirect->fresh()->is_active);

        // Toggle again
        $this->actingAs($user)
            ->post(route('seo.redirects.toggle', [$site, $redirect]))
            ->assertRedirect();

        $this->assertTrue((bool) $redirect->fresh()->is_active);
    }

    public function test_owner_can_delete_redirect(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);

        $redirect = Redirect::create([
            'site_id' => $site->id,
            'from_path' => '/delete-me',
            'to_path' => '/gone',
            'status_code' => 302,
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->delete(route('seo.redirects.destroy', [$site, $redirect]))
            ->assertRedirect();

        $this->assertDatabaseMissing('redirects', ['id' => $redirect->id]);
    }

    public function test_other_user_cannot_manage_redirects(): void
    {
        $owner = $this->makeUser('owner@seo.com');
        $other = $this->makeUser('other@seo.com');
        $site = $this->makeSite($owner);

        $this->actingAs($other)
            ->get(route('seo.redirects', $site))
            ->assertStatus(404);
    }
}
