<?php

namespace Tests\Feature;

use App\Models\Site;
use App\Models\User;
use App\Policies\SitePolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Verify that only admins can change the build command / output dir.
 * These fields execute shell commands on the server during deploy.
 */
class SiteSettingsBuildCommandPolicyTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $role): User
    {
        return User::create([
            'name' => 'U', 'email' => 'sbcp-'.uniqid().'@x.com',
            'password' => Hash::make('secret'), 'role' => $role,
        ]);
    }

    private function makeSite(User $owner): Site
    {
        return Site::create([
            'user_id' => $owner->id, 'name' => 'S',
            'slug' => 'sbcp-'.uniqid(), 'branch' => 'main', 'project_type' => 'static_html',
        ]);
    }

    public function test_admin_can_configure_build(): void
    {
        $admin = $this->makeUser('admin');
        $site = $this->makeSite($admin);

        $this->actingAs($admin);
        $this->assertTrue(Gate::allows('configureBuild', $site));
    }

    public function test_editor_who_owns_site_cannot_configure_build(): void
    {
        $editor = $this->makeUser('editor');
        $site = $this->makeSite($editor);

        $this->actingAs($editor);
        $this->assertFalse(Gate::allows('configureBuild', $site));
    }

    public function test_editor_who_does_not_own_site_cannot_configure_build(): void
    {
        $owner = $this->makeUser('editor');
        $otherEditor = $this->makeUser('editor');
        $site = $this->makeSite($owner);

        $this->actingAs($otherEditor);
        $this->assertFalse(Gate::allows('configureBuild', $site));
    }

    public function test_policy_before_hook_grants_admin_all_site_abilities(): void
    {
        $admin = $this->makeUser('admin');
        $owner = $this->makeUser('editor');
        $site = $this->makeSite($owner); // admin does not own this site

        $this->actingAs($admin);

        // The before() hook returns true for admins on any site.
        $this->assertTrue(Gate::allows('view', $site));
        $this->assertTrue(Gate::allows('update', $site));
        $this->assertTrue(Gate::allows('delete', $site));
        $this->assertTrue(Gate::allows('configureBuild', $site));
    }
}
