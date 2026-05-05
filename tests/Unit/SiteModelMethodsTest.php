<?php

namespace Tests\Unit;

use App\Models\Site;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SiteModelMethodsTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $email): User
    {
        return User::create([
            'name' => 'U',
            'email' => $email,
            'password' => Hash::make('password'),
            'role' => 'editor',
        ]);
    }

    private function makeSite(User $user, array $attrs = []): Site
    {
        return Site::create(array_merge([
            'user_id' => $user->id,
            'name' => 'Test Site',
            'slug' => 'test-site-'.uniqid(),
            'repo_url' => 'https://github.com/example/test',
            'branch' => 'main',
            'project_type' => 'static_html',
        ], $attrs));
    }

    // ── normalizeGithubRepository ─────────────────

    public function test_normalize_returns_null_for_empty_string(): void
    {
        $this->assertNull(Site::normalizeGithubRepository(''));
        $this->assertNull(Site::normalizeGithubRepository(null));
    }

    public function test_normalize_https_github_url(): void
    {
        $this->assertSame('owner/repo', Site::normalizeGithubRepository('https://github.com/owner/repo'));
        $this->assertSame('owner/repo', Site::normalizeGithubRepository('https://github.com/owner/repo.git'));
    }

    public function test_normalize_ssh_github_url(): void
    {
        $this->assertSame('owner/repo', Site::normalizeGithubRepository('git@github.com:owner/repo.git'));
        $this->assertSame('owner/repo', Site::normalizeGithubRepository('git@github.com:owner/repo'));
    }

    public function test_normalize_plain_owner_slash_repo(): void
    {
        $this->assertSame('owner/repo', Site::normalizeGithubRepository('Owner/Repo'));
    }

    public function test_normalize_returns_lowercase(): void
    {
        $this->assertSame('myorg/myrepo', Site::normalizeGithubRepository('MyOrg/MyRepo'));
    }

    // ── clientDisplayName ─────────────────────────

    public function test_client_display_name_with_company_only(): void
    {
        $user = $this->makeUser('cdn1@x.com');
        $site = $this->makeSite($user, [
            'client_company' => 'Acme Corp',
        ]);
        $this->assertSame('Acme Corp', $site->clientDisplayName());
    }

    public function test_client_display_name_with_full_name(): void
    {
        $user = $this->makeUser('cdn2@x.com');
        $site = $this->makeSite($user, [
            'client_first_name' => 'Jane',
            'client_last_name' => 'Smith',
        ]);
        $this->assertStringContainsString('Jane', $site->clientDisplayName());
        $this->assertStringContainsString('Smith', $site->clientDisplayName());
    }

    public function test_client_display_name_falls_back_to_client_literal(): void
    {
        $user = $this->makeUser('cdn3@x.com');
        $site = $this->makeSite($user); // no client fields set
        $this->assertSame('Client', $site->clientDisplayName());
    }

    public function test_client_display_name_uses_email_when_no_name(): void
    {
        $user = $this->makeUser('cdn4@x.com');
        $site = $this->makeSite($user, ['client_email' => 'client@example.com']);
        $this->assertSame('client@example.com', $site->clientDisplayName());
    }

    // ── scopeVisibleTo ────────────────────────────

    public function test_scope_visible_to_editor_shows_own_sites_only(): void
    {
        $alice = $this->makeUser('alice@sv.com');
        $bob = $this->makeUser('bob@sv.com');

        $this->makeSite($alice);
        $this->makeSite($alice);
        $this->makeSite($bob);

        $visible = Site::query()->visibleTo($alice)->get();
        $this->assertCount(2, $visible);
        $visible->each(fn ($s) => $this->assertSame($alice->id, $s->user_id));
    }

    public function test_scope_visible_to_admin_shows_all_sites(): void
    {
        $admin = User::create([
            'name' => 'Admin',
            'email' => 'admin@sv.com',
            'password' => Hash::make('password'),
            'role' => 'admin',
        ]);
        $editor = $this->makeUser('editor@sv.com');

        $this->makeSite($editor);
        $this->makeSite($editor);
        $this->makeSite($admin);

        $visible = Site::query()->visibleTo($admin)->get();
        $this->assertCount(3, $visible);
    }

    public function test_scope_visible_to_null_user_returns_nothing(): void
    {
        $user = $this->makeUser('nobody@sv.com');
        $this->makeSite($user);

        $visible = Site::query()->visibleTo(null)->get();
        $this->assertCount(0, $visible);
    }

    // ── findVisibleOrFail ─────────────────────────

    public function test_find_visible_or_fail_returns_own_site(): void
    {
        $user = $this->makeUser('fvof@sv.com');
        $site = $this->makeSite($user);

        $found = Site::findVisibleOrFail($site->id, $user);
        $this->assertTrue($found->is($site));
    }

    public function test_find_visible_or_fail_throws_for_other_users_site(): void
    {
        $owner = $this->makeUser('owner@fvof.com');
        $other = $this->makeUser('other@fvof.com');
        $site = $this->makeSite($owner);

        $this->expectException(ModelNotFoundException::class);
        Site::findVisibleOrFail($site->id, $other);
    }
}
