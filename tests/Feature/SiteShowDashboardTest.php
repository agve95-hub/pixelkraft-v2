<?php

namespace Tests\Feature;

use App\Models\AnalyticsSnapshot;
use App\Models\Notification;
use App\Models\Page;
use App\Models\Site;
use App\Models\UptimeCheck;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SiteShowDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_site_show_renders_dashboard_sections_with_metrics(): void
    {
        $user = User::create([
            'name' => 'Demo User',
            'email' => 'demo@example.com',
            'password' => Hash::make('password'),
        ]);

        $site = Site::create([
            'user_id' => $user->id,
            'name' => 'Ashton',
            'slug' => 'ashton',
            'repo_url' => 'https://github.com/example/ashton',
            'branch' => 'main',
            'project_type' => 'react',
            'deploy_status' => 'live',
            'ssl_status' => 'pending',
            'domain' => 'ashton.test',
            'client_first_name' => 'Robert',
            'client_last_name' => 'Artho',
        ]);

        $home = Page::create([
            'site_id' => $site->id,
            'file_path' => 'index.html',
            'url_path' => '/',
            'title' => 'Home',
            'seo_score' => 90,
            'meta_description' => 'Home page meta',
            'is_published' => true,
        ]);

        AnalyticsSnapshot::create([
            'page_id' => $home->id,
            'date' => today(),
            'source' => 'ga4',
            'visitors' => 20,
            'pageviews' => 30,
            'created_at' => now(),
        ]);

        UptimeCheck::create([
            'site_id' => $site->id,
            'status_code' => 200,
            'response_time_ms' => 142,
            'is_up' => true,
            'is_degraded' => false,
            'checked_at' => now(),
        ]);

        Notification::create([
            'type' => 'deploy_failed',
            'title' => 'Deploy failed',
            'body' => 'Error.',
            'site_id' => $site->id,
            'is_read' => false,
            'created_at' => now(),
        ]);

        $response = $this->actingAs($user)->get(route('sites.show', $site));

        $response->assertOk();
        $response->assertViewIs('dashboard.sites.show');
        $response->assertViewHas('site', fn ($s) => $s->id === $site->id && $s->name === 'Ashton');
        $response->assertSee('Ashton');
    }
}
