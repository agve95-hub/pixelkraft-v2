<?php

namespace Tests\Unit;

use App\Models\Page;
use App\Models\SeoIssue;
use App\Models\Site;
use App\Models\User;
use App\Support\SeoIssueSummary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SeoIssueSummaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_open_count_for_site_ids(): void
    {
        $user = User::create([
            'name' => 'U',
            'email' => 'u@example.com',
            'password' => Hash::make('password'),
            'role' => 'editor',
        ]);

        $site = Site::create([
            'user_id' => $user->id,
            'name' => 'S',
            'slug' => 's-open-count',
            'repo_url' => 'https://github.com/example/s',
            'branch' => 'main',
            'project_type' => 'static_html',
        ]);

        $page = Page::create([
            'site_id' => $site->id,
            'file_path' => 'i.html',
            'url_path' => '/',
            'title' => 'T',
            'is_published' => true,
        ]);

        SeoIssue::create([
            'site_id' => $site->id,
            'page_id' => $page->id,
            'severity' => 'info',
            'code' => 'analyzer:canonical_url',
            'message' => 'No canonical',
            'resolved_at' => null,
        ]);

        SeoIssue::create([
            'site_id' => $site->id,
            'page_id' => $page->id,
            'severity' => 'warning',
            'code' => 'analyzer:title',
            'message' => 'Title short',
            'resolved_at' => now(),
        ]);

        $this->assertSame(1, SeoIssueSummary::openCountForSiteIds([$site->id]));
    }

    public function test_open_aggregates_for_site_groups_by_code(): void
    {
        $user = User::create([
            'name' => 'U2',
            'email' => 'u2@example.com',
            'password' => Hash::make('password'),
            'role' => 'editor',
        ]);

        $site = Site::create([
            'user_id' => $user->id,
            'name' => 'S2',
            'slug' => 's-agg',
            'repo_url' => 'https://github.com/example/s2',
            'branch' => 'main',
            'project_type' => 'static_html',
        ]);

        $p1 = Page::create([
            'site_id' => $site->id,
            'file_path' => '1.html',
            'url_path' => '/1',
            'title' => 'A',
            'is_published' => true,
        ]);

        $p2 = Page::create([
            'site_id' => $site->id,
            'file_path' => '2.html',
            'url_path' => '/2',
            'title' => 'B',
            'is_published' => true,
        ]);

        SeoIssue::create([
            'site_id' => $site->id,
            'page_id' => $p1->id,
            'severity' => 'info',
            'code' => 'analyzer:og_image',
            'message' => 'No og:image',
            'resolved_at' => null,
        ]);

        SeoIssue::create([
            'site_id' => $site->id,
            'page_id' => $p2->id,
            'severity' => 'warning',
            'code' => 'analyzer:og_image',
            'message' => 'No og:image',
            'resolved_at' => null,
        ]);

        $agg = SeoIssueSummary::openAggregatesForSite($site);

        $this->assertCount(1, $agg);
        $this->assertSame(2, $agg->first()['count']);
        $this->assertSame('warning', $agg->first()['severity']);
    }
}
