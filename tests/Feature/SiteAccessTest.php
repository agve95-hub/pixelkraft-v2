<?php

namespace Tests\Feature;

use App\Models\Site;
use App\Models\User;
use App\Support\SiteAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SiteAccessTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $role = 'editor'): User
    {
        return User::create([
            'name' => 'U',
            'email' => 'sa-'.uniqid().'@x.com',
            'password' => Hash::make('secret'),
            'role' => $role,
        ]);
    }

    private function makeSite(User $user): Site
    {
        return Site::create([
            'user_id' => $user->id,
            'name' => 'S',
            'slug' => 'sa-'.uniqid(),
            'repo_url' => 'https://github.com/example/sa',
            'branch' => 'main',
            'project_type' => 'static_html',
        ]);
    }

    // ── query ────────────────────────────────────

    public function test_query_returns_only_own_sites_for_editor(): void
    {
        $owner = $this->makeUser('editor');
        $other = $this->makeUser('editor');

        $ownSite = $this->makeSite($owner);
        $this->makeSite($other);

        Auth::login($owner);

        $sites = SiteAccess::query()->get();

        $this->assertCount(1, $sites);
        $this->assertTrue($sites->first()->is($ownSite));
    }

    public function test_query_returns_all_sites_for_admin(): void
    {
        $admin = $this->makeUser('admin');
        $editor = $this->makeUser('editor');

        $this->makeSite($admin);
        $this->makeSite($editor);

        Auth::login($admin);

        $sites = SiteAccess::query()->get();

        $this->assertCount(2, $sites);
    }

    public function test_query_returns_empty_when_no_user_authenticated(): void
    {
        $user = $this->makeUser();
        $this->makeSite($user);

        // No auth
        $sites = SiteAccess::query()->get();

        $this->assertCount(0, $sites);
    }

    // ── findOrFail ───────────────────────────────

    public function test_find_or_fail_returns_site_for_owner(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);

        Auth::login($user);

        $found = SiteAccess::findOrFail($site->id);

        $this->assertTrue($found->is($site));
    }

    public function test_find_or_fail_returns_any_site_for_admin(): void
    {
        $owner = $this->makeUser('editor');
        $site = $this->makeSite($owner);
        $admin = $this->makeUser('admin');

        Auth::login($admin);

        $found = SiteAccess::findOrFail($site->id);

        $this->assertTrue($found->is($site));
    }

    public function test_find_or_fail_throws_for_other_user_site(): void
    {
        $owner = $this->makeUser('editor');
        $site = $this->makeSite($owner);
        $other = $this->makeUser('editor');

        Auth::login($other);

        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

        SiteAccess::findOrFail($site->id);
    }

    public function test_find_or_fail_throws_for_nonexistent_site(): void
    {
        $user = $this->makeUser();
        Auth::login($user);

        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

        SiteAccess::findOrFail('00000000-0000-0000-0000-000000000000');
    }
}
