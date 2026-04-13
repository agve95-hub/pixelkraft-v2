<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DashboardIndexTenancyTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_metrics_exclude_other_users_sites(): void
    {
        $alice = User::create([
            'name' => 'Alice',
            'email' => 'alice@example.com',
            'password' => Hash::make('password'),
            'role' => 'editor',
        ]);

        $bob = User::create([
            'name' => 'Bob',
            'email' => 'bob@example.com',
            'password' => Hash::make('password'),
            'role' => 'editor',
        ]);

        $aliceSite = Site::create([
            'user_id' => $alice->id,
            'name' => 'Alice Owned Project',
            'slug' => 'alice-owned',
            'repo_url' => 'https://github.com/example/alice',
            'branch' => 'main',
            'project_type' => 'static_html',
        ]);

        $bobSite = Site::create([
            'user_id' => $bob->id,
            'name' => 'Bob Secret Workspace',
            'slug' => 'bob-secret',
            'repo_url' => 'https://github.com/example/bob',
            'branch' => 'main',
            'project_type' => 'static_html',
        ]);

        Page::create([
            'site_id' => $aliceSite->id,
            'file_path' => 'index.html',
            'url_path' => '/',
            'title' => 'Home',
            'is_published' => true,
        ]);

        Page::create([
            'site_id' => $bobSite->id,
            'file_path' => 'index.html',
            'url_path' => '/',
            'title' => 'Bob Home',
            'is_published' => true,
        ]);

        $response = $this->actingAs($alice)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('Alice Owned Project', false);
        $response->assertDontSee('Bob Secret Workspace', false);
    }
}
