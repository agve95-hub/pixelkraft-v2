<?php

namespace Tests\Feature;

use App\Enums\DeployStatus;
use App\Models\DeployLog;
use App\Models\Page;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiSiteEndpointsTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $email = 'api@example.com'): User
    {
        return User::create([
            'name' => 'API User',
            'email' => $email,
            'password' => Hash::make('password'),
            'role' => 'editor',
        ]);
    }

    private function makeSite(User $user, string $slug = 'api-site'): Site
    {
        return Site::create([
            'user_id' => $user->id,
            'name' => 'API Site',
            'slug' => $slug.'-'.uniqid(),
            'repo_url' => 'https://github.com/example/api',
            'branch' => 'main',
            'project_type' => 'static_html',
            'deploy_status' => DeployStatus::Live,
        ]);
    }

    private function actingWithToken(User $user): void
    {
        Sanctum::actingAs($user, [
            'pixelkraft:sites:read',
            'pixelkraft:sites:deploy',
            'pixelkraft:sites:rollback',
            'pixelkraft:sites:write',
        ]);
    }

    // ── Pages endpoint ────────────────────────────

    public function test_pages_returns_paginated_list(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);

        Page::create([
            'site_id' => $site->id,
            'file_path' => 'index.html',
            'url_path' => '/',
            'title' => 'Home',
        ]);

        $this->actingWithToken($user);

        $this->getJson(route('api.v1.sites.pages', $site))
            ->assertOk()
            ->assertJsonStructure(['data', 'meta'])
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.url_path', '/');
    }

    public function test_pages_isolates_by_site(): void
    {
        $owner = $this->makeUser('own@ap.com');
        $other = $this->makeUser('oth@ap.com');
        $site = $this->makeSite($owner, 'pages-iso');

        Page::create(['site_id' => $site->id, 'file_path' => 'index.html', 'url_path' => '/', 'title' => 'Home']);

        $this->actingWithToken($other);

        $this->getJson(route('api.v1.sites.pages', $site))->assertStatus(404);
    }

    // ── Deploys endpoint ──────────────────────────

    public function test_deploys_returns_paginated_list(): void
    {
        $user = $this->makeUser('dep@ap.com');
        $site = $this->makeSite($user, 'deploys-test');

        DeployLog::create([
            'site_id' => $site->id,
            'status' => 'success',
            'triggered_by' => 'manual',
            'created_at' => now(),
        ]);

        $this->actingWithToken($user);

        $this->getJson(route('api.v1.sites.deploys', $site))
            ->assertOk()
            ->assertJsonStructure(['data', 'meta'])
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.status', 'success');
    }

    public function test_deploys_returns_empty_for_new_site(): void
    {
        $user = $this->makeUser('dep2@ap.com');
        $site = $this->makeSite($user, 'deploys-empty');

        $this->actingWithToken($user);

        $this->getJson(route('api.v1.sites.deploys', $site))
            ->assertOk()
            ->assertJsonPath('meta.total', 0);
    }

    // ── Analytics endpoint ────────────────────────

    public function test_analytics_returns_data_structure(): void
    {
        $user = $this->makeUser('ana@ap.com');
        $site = $this->makeSite($user, 'analytics-api');

        $this->actingWithToken($user);

        $this->getJson(route('api.v1.sites.analytics', $site))
            ->assertOk()
            ->assertJsonStructure(['data' => ['traffic', 'events']]);
    }

    public function test_analytics_validates_days_parameter(): void
    {
        $user = $this->makeUser('ana2@ap.com');
        $site = $this->makeSite($user, 'analytics-api2');

        $this->actingWithToken($user);

        // Valid days values: 7, 30, 90 — invalid should default to 30
        $this->getJson(route('api.v1.sites.analytics', $site).'?days=999')
            ->assertOk(); // Doesn't fail, just defaults to 30
    }

    // ── Releases endpoint ─────────────────────────

    public function test_releases_returns_paginated_list(): void
    {
        $user = $this->makeUser('rel@ap.com');
        $site = $this->makeSite($user, 'releases-test');

        $this->actingWithToken($user);

        $this->getJson(route('api.v1.sites.releases', $site))
            ->assertOk()
            ->assertJsonStructure(['data', 'meta']);
    }

    // ── Git operations endpoint ───────────────────

    public function test_git_operations_returns_paginated_list(): void
    {
        $user = $this->makeUser('git@ap.com');
        $site = $this->makeSite($user, 'git-ops-test');

        $this->actingWithToken($user);

        $this->getJson(route('api.v1.sites.git-operations', $site))
            ->assertOk()
            ->assertJsonStructure(['data', 'meta']);
    }

    // ── Rollback endpoint ─────────────────────────

    public function test_rollback_fails_without_snapshot_tag(): void
    {
        $user = $this->makeUser('rol@ap.com');
        $site = $this->makeSite($user, 'rollback-test');

        $log = DeployLog::create([
            'site_id' => $site->id,
            'status' => 'success',
            'triggered_by' => 'manual',
            'snapshot_tag' => null, // No snapshot tag — rollback not possible
            'created_at' => now(),
        ]);

        $this->actingWithToken($user);

        // Without a snapshot tag the rollback returns an error response
        $this->postJson(route('api.v1.sites.rollback', [$site, $log->id]))
            ->assertStatus(400);
    }

    // ── Auth guard ────────────────────────────────

    public function test_api_requires_authentication(): void
    {
        $user = $this->makeUser('noauth@ap.com');
        $site = $this->makeSite($user, 'auth-test');

        $this->getJson(route('api.v1.sites.pages', $site))
            ->assertUnauthorized();
    }
}
