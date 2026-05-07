<?php

namespace Tests\Feature;

use App\Livewire\Dashboard\SiteList;
use App\Livewire\Seo\RedirectManager;
use App\Livewire\Settings\ApiTokens;
use App\Livewire\Sites\SiteManager;
use App\Models\Redirect;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class MoreLivewireComponentsTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $email = 'mlw@example.com'): User
    {
        return User::create([
            'name' => 'MLW User',
            'email' => $email,
            'password' => Hash::make('password'),
            'role' => 'editor',
        ]);
    }

    private function makeSite(User $user, array $attrs = []): Site
    {
        return Site::create(array_merge([
            'user_id' => $user->id,
            'name' => 'MLW Site',
            'slug' => 'mlw-site-'.uniqid(),
            'repo_url' => 'https://github.com/example/mlw',
            'branch' => 'main',
            'project_type' => 'static_html',
        ], $attrs));
    }

    // ── SiteList ──────────────────────────────────

    public function test_site_list_renders(): void
    {
        $user = $this->makeUser();
        $this->makeSite($user);

        Livewire::actingAs($user)
            ->test(SiteList::class)
            ->assertOk();
    }

    public function test_site_list_shows_only_own_sites(): void
    {
        $alice = $this->makeUser('alice@mlw.com');
        $bob = $this->makeUser('bob@mlw.com');

        $this->makeSite($alice, ['name' => 'Alice Site']);
        $this->makeSite($bob, ['name' => 'Bob Site']);

        Livewire::actingAs($alice)
            ->test(SiteList::class)
            ->assertSee('Alice Site')
            ->assertDontSee('Bob Site');
    }

    // ── SiteManager ───────────────────────────────

    public function test_site_manager_renders(): void
    {
        $user = $this->makeUser('sm@mlw.com');

        Livewire::actingAs($user)
            ->test(SiteManager::class)
            ->assertOk();
    }

    public function test_site_manager_requires_name_to_create(): void
    {
        $user = $this->makeUser('smv@mlw.com');

        Livewire::actingAs($user)
            ->test(SiteManager::class)
            ->set('name', '')
            ->call('create')
            ->assertHasErrors(['name']);
    }

    // ── RedirectManager ───────────────────────────

    public function test_site_manager_detects_stack_from_repo_url(): void
    {
        $user = $this->makeUser('detect@mlw.com');

        Livewire::actingAs($user)
            ->test(SiteManager::class)
            ->set('repoUrl', 'https://github.com/example/next-marketing-site.git')
            ->call('detectStack')
            ->assertSet('projectType', 'nextjs')
            ->assertSet('buildCommand', 'npm run build')
            ->assertSet('detectedLanguage', 'JavaScript / TypeScript');
    }

    public function test_site_manager_can_create_upload_project_draft_without_repo(): void
    {
        $user = $this->makeUser('upload-draft@mlw.com');

        Livewire::actingAs($user)
            ->test(SiteManager::class)
            ->set('name', 'Upload Draft')
            ->set('sourceMode', 'upload_ready_build')
            ->call('create');

        $this->assertDatabaseHas('sites', [
            'name' => 'Upload Draft',
            'source_type' => 'upload',
            'repo_url' => null,
        ]);
    }

    public function test_redirect_manager_renders(): void
    {
        $user = $this->makeUser('rdir@mlw.com');
        $site = $this->makeSite($user);

        Livewire::actingAs($user)
            ->test(RedirectManager::class, ['siteId' => $site->id])
            ->assertOk();
    }

    public function test_redirect_manager_can_save_new_redirect(): void
    {
        $user = $this->makeUser('rdirr@mlw.com');
        $site = $this->makeSite($user);

        Livewire::actingAs($user)
            ->test(RedirectManager::class, ['siteId' => $site->id])
            ->set('fromPath', '/old-page')
            ->set('toPath', '/new-page')
            ->set('statusCode', 301)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('redirects', [
            'site_id' => $site->id,
            'from_path' => '/old-page',
            'to_path' => '/new-page',
        ]);
    }

    public function test_redirect_manager_can_toggle_redirect(): void
    {
        $user = $this->makeUser('rdirt@mlw.com');
        $site = $this->makeSite($user);

        $redirect = Redirect::create([
            'site_id' => $site->id,
            'from_path' => '/a',
            'to_path' => '/b',
            'status_code' => 301,
            'is_active' => true,
        ]);

        Livewire::actingAs($user)
            ->test(RedirectManager::class, ['siteId' => $site->id])
            ->call('toggle', $redirect->id);

        $this->assertFalse((bool) $redirect->fresh()->is_active);
    }

    public function test_redirect_manager_can_delete_redirect(): void
    {
        $user = $this->makeUser('rdird@mlw.com');
        $site = $this->makeSite($user);

        $redirect = Redirect::create([
            'site_id' => $site->id,
            'from_path' => '/del',
            'to_path' => '/gone',
            'status_code' => 301,
            'is_active' => true,
        ]);

        Livewire::actingAs($user)
            ->test(RedirectManager::class, ['siteId' => $site->id])
            ->call('delete', $redirect->id);

        $this->assertDatabaseMissing('redirects', ['id' => $redirect->id]);
    }

    // ── ApiTokens ─────────────────────────────────

    public function test_api_tokens_renders(): void
    {
        $user = $this->makeUser('api@mlw.com');

        Livewire::actingAs($user)
            ->test(ApiTokens::class)
            ->assertOk();
    }

    public function test_api_tokens_can_create_token(): void
    {
        $user = $this->makeUser('apic@mlw.com');

        Livewire::actingAs($user)
            ->test(ApiTokens::class)
            ->set('tokenName', 'My CI Token')
            ->set('selectedAbilities', ['pixelkraft:sites:read'])
            ->call('createToken')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'name' => 'My CI Token',
        ]);
    }

    public function test_api_tokens_can_revoke_token(): void
    {
        $user = $this->makeUser('apir@mlw.com');

        $token = $user->createToken('Old Token', ['pixelkraft:sites:read']);

        Livewire::actingAs($user)
            ->test(ApiTokens::class)
            ->call('revokeToken', $token->accessToken->id);

        $this->assertDatabaseMissing('personal_access_tokens', [
            'id' => $token->accessToken->id,
        ]);
    }
}
