<?php

namespace Tests\Feature;

use App\Models\SeoIssue;
use App\Models\Site;
use App\Models\User;
use Database\Seeders\DemoSiteSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoSiteSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_seeder_creates_site_pages_and_seo_issues(): void
    {
        $this->seed(DemoSiteSeeder::class);

        $user = User::query()->where('email', 'demo@pixelkraft.local')->firstOrFail();
        $this->assertSame('admin', $user->role);

        $site = Site::query()->where('slug', 'demo-studio')->firstOrFail();
        $this->assertTrue($site->pages()->count() >= 1);

        $this->assertGreaterThan(0, SeoIssue::query()->where('site_id', $site->id)->count());
    }
}
