<?php

namespace Tests\Unit;

use App\Models\Page;
use App\Models\Site;
use App\Models\User;
use App\Services\SiteSupportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SiteSupportServiceTest extends TestCase
{
    use RefreshDatabase;

    private SiteSupportService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(SiteSupportService::class);
    }

    private function makeSite(string $projectType = 'static_html'): Site
    {
        $user = User::create([
            'name' => 'U',
            'email' => 'sss-'.uniqid().'@x.com',
            'password' => Hash::make('password'),
            'role' => 'editor',
        ]);

        return Site::create([
            'user_id' => $user->id,
            'name' => 'S',
            'slug' => 'sss-'.uniqid(),
            'repo_url' => 'https://github.com/example/s',
            'branch' => 'main',
            'project_type' => $projectType,
        ]);
    }

    private function makePage(Site $site, string $filePath = 'index.html'): Page
    {
        return Page::create([
            'site_id' => $site->id,
            'file_path' => $filePath,
            'url_path' => '/',
            'title' => 'Home',
        ]);
    }

    // ── supportsVisualEditing ─────────────────────

    #[DataProvider('visualEditingStaticTypes')]
    public function test_static_types_support_visual_editing(string $type): void
    {
        $site = $this->makeSite($type);
        $this->assertTrue($this->service->supportsVisualEditing($site));
    }

    public static function visualEditingStaticTypes(): array
    {
        return [['static_html'], ['php_site'], ['hugo'], ['eleventy']];
    }

    #[DataProvider('visualEditingComponentTypes')]
    public function test_component_types_support_visual_editing(string $type): void
    {
        $site = $this->makeSite($type);
        $this->assertTrue($this->service->supportsVisualEditing($site));
    }

    public static function visualEditingComponentTypes(): array
    {
        return [['nextjs'], ['nuxt'], ['react'], ['vue'], ['svelte'], ['astro']];
    }

    #[DataProvider('directVisualSourcePaths')]
    public function test_direct_visual_source_paths_enable_visual_editing(string $filePath): void
    {
        $site = $this->makeSite('custom');
        $page = $this->makePage($site, $filePath);
        $this->assertTrue($this->service->supportsVisualEditing($site, $page));
    }

    public static function directVisualSourcePaths(): array
    {
        return [
            ['index.html'],
            ['page.htm'],
            ['post.md'],
            ['article.markdown'],
            ['template.blade.php'],
            ['page.njk'],
            ['page.liquid'],
            ['page.twig'],
            ['page.php'],
        ];
    }

    public function test_custom_type_without_visual_path_does_not_support_editing(): void
    {
        $site = $this->makeSite('custom');
        $page = $this->makePage($site, 'component.tsx');
        $this->assertFalse($this->service->supportsVisualEditing($site, $page));
    }

    public function test_supports_visual_editing_without_page_returns_false_for_custom(): void
    {
        $site = $this->makeSite('custom');
        $this->assertFalse($this->service->supportsVisualEditing($site));
    }

    // ── metaEditingMode ───────────────────────────

    #[DataProvider('htmlHeadEditablePaths')]
    public function test_html_head_paths_return_html_meta_mode(string $path): void
    {
        $site = $this->makeSite('static_html');
        $page = $this->makePage($site, $path);
        $this->assertSame('html', $this->service->metaEditingMode($site, $page));
    }

    public static function htmlHeadEditablePaths(): array
    {
        return [
            ['index.html'],
            ['page.htm'],
            ['template.blade.php'],
            ['page.njk'],
            ['page.php'],
        ];
    }

    public function test_tsx_on_non_nextjs_returns_unsupported_meta_mode(): void
    {
        $site = $this->makeSite('react');
        $page = $this->makePage($site, 'Home.tsx');
        $this->assertSame('unsupported', $this->service->metaEditingMode($site, $page));
    }

    public function test_md_file_returns_unsupported_meta_mode(): void
    {
        $site = $this->makeSite('hugo');
        $page = $this->makePage($site, 'post.md');
        $this->assertSame('unsupported', $this->service->metaEditingMode($site, $page));
    }

    // ── supportsSchemaEditing ─────────────────────

    public function test_html_files_support_schema_editing(): void
    {
        $site = $this->makeSite('static_html');
        $page = $this->makePage($site, 'index.html');
        $this->assertTrue($this->service->supportsSchemaEditing($site, $page));
    }

    public function test_tsx_files_do_not_support_schema_editing(): void
    {
        $site = $this->makeSite('nextjs');
        $page = $this->makePage($site, 'page.tsx');
        $this->assertFalse($this->service->supportsSchemaEditing($site, $page));
    }

    // ── siteProfile ───────────────────────────────

    public function test_site_profile_returns_expected_keys(): void
    {
        $site = $this->makeSite('static_html');
        $profile = $this->service->siteProfile($site);

        $this->assertArrayHasKey('deployment_mode', $profile);
        $this->assertArrayHasKey('editor_workflow', $profile);
        $this->assertArrayHasKey('visual_editing_supported', $profile);
        $this->assertArrayHasKey('summary', $profile);
    }

    public function test_site_profile_editor_workflow_is_visual_for_static(): void
    {
        $site = $this->makeSite('static_html');
        $profile = $this->service->siteProfile($site);

        $this->assertTrue($profile['visual_editing_supported']);
        $this->assertSame('visual_html', $profile['editor_workflow']);
    }

    public function test_site_profile_editor_workflow_is_code_first_for_custom(): void
    {
        $site = $this->makeSite('custom');
        $profile = $this->service->siteProfile($site);

        $this->assertFalse($profile['visual_editing_supported']);
        $this->assertSame('code_first', $profile['editor_workflow']);
    }

    // ── editorProfile ─────────────────────────────

    public function test_editor_profile_returns_expected_keys(): void
    {
        $site = $this->makeSite('static_html');
        $page = $this->makePage($site, 'index.html');
        $profile = $this->service->editorProfile($site, $page);

        $this->assertArrayHasKey('default_mode', $profile);
        $this->assertArrayHasKey('visual_editing_supported', $profile);
        $this->assertArrayHasKey('meta_editing_mode', $profile);
        $this->assertArrayHasKey('schema_editing_supported', $profile);
    }

    public function test_editor_profile_default_mode_visual_for_html(): void
    {
        $site = $this->makeSite('static_html');
        $page = $this->makePage($site, 'index.html');
        $profile = $this->service->editorProfile($site, $page);

        $this->assertSame('visual', $profile['default_mode']);
        $this->assertTrue($profile['meta_editing_supported']);
        $this->assertTrue($profile['schema_editing_supported']);
    }
}
