<?php

namespace Tests\Unit;

use App\Models\Page;
use App\Models\SeoIssue;
use App\Models\Site;
use App\Models\User;
use App\Services\SeoAnalyzer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SeoAnalyzerSeoIssuesTest extends TestCase
{
    use RefreshDatabase;

    public function test_analyze_creates_open_seo_issues_for_suggestions(): void
    {
        $user = User::create([
            'name' => 'Editor',
            'email' => 'editor@example.com',
            'password' => Hash::make('password'),
            'role' => 'editor',
        ]);

        $site = Site::create([
            'user_id' => $user->id,
            'name' => 'Test Site',
            'slug' => 'test-site',
            'repo_url' => 'https://github.com/example/test',
            'branch' => 'main',
            'project_type' => 'static_html',
        ]);

        $page = Page::create([
            'site_id' => $site->id,
            'file_path' => 'index.html',
            'url_path' => '/',
            'title' => null,
            'meta_description' => null,
            'is_published' => true,
        ]);

        $result = app(SeoAnalyzer::class)->analyze($page->fresh());

        $this->assertIsArray($result['suggestions']);
        $this->assertNotEmpty($result['suggestions']);

        $open = SeoIssue::query()
            ->where('page_id', $page->id)
            ->whereNull('resolved_at')
            ->get();

        $this->assertNotEmpty($open);
        $this->assertTrue($open->contains(fn (SeoIssue $issue) => str_starts_with((string) $issue->code, 'analyzer:')));
    }

    public function test_analyze_resolves_stale_analyzer_issues_when_clean(): void
    {
        $user = User::create([
            'name' => 'Editor',
            'email' => 'editor2@example.com',
            'password' => Hash::make('password'),
            'role' => 'editor',
        ]);

        $site = Site::create([
            'user_id' => $user->id,
            'name' => 'Other Site',
            'slug' => 'other-site',
            'repo_url' => 'https://github.com/example/other',
            'branch' => 'main',
            'project_type' => 'static_html',
        ]);

        $page = Page::create([
            'site_id' => $site->id,
            'file_path' => 'about.html',
            'url_path' => '/about',
            'title' => 'A perfectly fine title for SEO length checks here',
            'meta_description' => str_repeat('word ', 40).'end',
            'og_title' => 'OG',
            'og_description' => str_repeat('og ', 40).'end',
            'og_image' => 'https://example.com/preview.png',
            'canonical_url' => 'https://example.com/about',
            'schema_json' => ['@context' => 'https://schema.org', '@type' => 'WebPage', 'name' => 'About'],
            'is_published' => true,
        ]);

        SeoIssue::create([
            'site_id' => $site->id,
            'page_id' => $page->id,
            'severity' => 'warning',
            'code' => 'analyzer:title',
            'message' => 'Stale title warning',
            'resolved_at' => null,
        ]);

        app(SeoAnalyzer::class)->analyze($page->fresh());

        $this->assertFalse(
            SeoIssue::query()
                ->where('page_id', $page->id)
                ->where('code', 'analyzer:title')
                ->whereNull('resolved_at')
                ->exists(),
            'Stale analyzer:title issue should be resolved when the title check passes.'
        );
    }
}
