<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AnalyzeSeoCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_requires_site_or_all_flag(): void
    {
        $this->artisan('pixelkraft:analyze-seo')
            ->assertExitCode(1);
    }

    public function test_command_analyzes_pages_for_site_slug(): void
    {
        $user = User::create([
            'name' => 'U',
            'email' => 'u-seo-cmd@example.com',
            'password' => Hash::make('password'),
            'role' => 'editor',
        ]);

        $site = Site::create([
            'user_id' => $user->id,
            'name' => 'Cmd Site',
            'slug' => 'cmd-site-seo',
            'repo_url' => 'https://github.com/example/cmd',
            'branch' => 'main',
            'project_type' => 'static_html',
        ]);

        Page::create([
            'site_id' => $site->id,
            'file_path' => 'x.html',
            'url_path' => '/x',
            'title' => 'Hello world title here',
            'meta_description' => str_repeat('word ', 35).'end',
            'og_title' => 'OG',
            'og_description' => str_repeat('og ', 35).'end',
            'og_image' => 'https://example.com/i.png',
            'canonical_url' => 'https://example.com/x',
            'schema_json' => ['@context' => 'https://schema.org', '@type' => 'WebPage'],
            'is_published' => true,
        ]);

        $exitCode = Artisan::call('pixelkraft:analyze-seo', ['--site' => 'cmd-site-seo']);

        $this->assertSame(0, $exitCode);
    }
}
