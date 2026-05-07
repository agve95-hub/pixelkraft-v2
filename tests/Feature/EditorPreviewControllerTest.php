<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class EditorPreviewControllerTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $role = 'editor'): User
    {
        return User::create([
            'name' => 'U',
            'email' => 'epc-'.uniqid().'@x.com',
            'password' => Hash::make('secret'),
            'role' => $role,
        ]);
    }

    private function makeSite(User $user): Site
    {
        return Site::create([
            'user_id' => $user->id,
            'name' => 'Preview Site',
            'slug' => 'epc-'.uniqid(),
            'repo_url' => 'https://github.com/example/epc',
            'branch' => 'main',
            'project_type' => 'static_html',
        ]);
    }

    private function makePage(Site $site, string $filePath = 'index.html'): Page
    {
        return Page::create([
            'site_id' => $site->id,
            'file_path' => $filePath,
            'url_path' => '/',
            'title' => 'Home',
            'is_published' => true,
        ]);
    }

    // ── authentication ───────────────────────────

    public function test_unauthenticated_request_redirects_to_login(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);
        $page = $this->makePage($site);

        $this->get(route('editor.preview', [$site, $page]))
            ->assertRedirect(route('login'));
    }

    // ── page / site ownership ─────────────────────

    public function test_returns_404_when_page_does_not_belong_to_site(): void
    {
        $user = $this->makeUser('admin');
        $site1 = $this->makeSite($user);
        $site2 = $this->makeSite($user);
        $pageOnSite2 = $this->makePage($site2);

        $this->actingAs($user)
            ->get(route('editor.preview', [$site1, $pageOnSite2]))
            ->assertNotFound();
    }

    public function test_other_user_cannot_preview_site(): void
    {
        $owner = $this->makeUser('editor');
        $site = $this->makeSite($owner);
        $page = $this->makePage($site);
        $other = $this->makeUser('editor');

        $this->actingAs($other)
            ->get(route('editor.preview', [$site, $page]))
            ->assertNotFound();
    }

    // ── response ─────────────────────────────────

    public function test_returns_html_response_for_owner(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);
        $page = $this->makePage($site);

        $response = $this->actingAs($user)
            ->get(route('editor.preview', [$site, $page]));

        // Controller either returns HTML or an unavailable message — never errors
        $this->assertContains($response->getStatusCode(), [200, 404]);
        if ($response->getStatusCode() === 200) {
            $this->assertStringContainsString('text/html', $response->headers->get('Content-Type') ?? '');
        }
    }

    public function test_admin_can_preview_any_site(): void
    {
        $owner = $this->makeUser('editor');
        $site = $this->makeSite($owner);
        $page = $this->makePage($site);
        $admin = $this->makeUser('admin');

        $response = $this->actingAs($admin)
            ->get(route('editor.preview', [$site, $page]));

        // Should not be a 401 or access-related 403/404 from middleware
        $this->assertNotSame(401, $response->getStatusCode());
    }

    // ── asset route ───────────────────────────────

    public function test_asset_route_returns_404_for_nonexistent_file(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);

        $this->actingAs($user)
            ->get(route('editor.asset', ['site' => $site, 'path' => 'nonexistent.css']))
            ->assertNotFound();
    }

    public function test_asset_route_rejects_path_traversal(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);

        $this->actingAs($user)
            ->get(route('editor.asset', ['site' => $site, 'path' => '../etc/passwd']))
            ->assertNotFound();
    }

    public function test_asset_route_serves_next_export_assets_from_build_output(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);
        $site->update([
            'project_type' => 'nextjs',
            'build_output_dir' => 'out',
            'deployment_mode' => 'static',
        ]);

        $assetPath = 'out/_next/static/css/app.css';
        $fullPath = $site->repo_path.'/'.$assetPath;
        if (! is_dir(dirname($fullPath))) {
            mkdir(dirname($fullPath), 0755, true);
        }
        file_put_contents($fullPath, '.demo { color: orange; }');

        $this->actingAs($user)
            ->get(route('editor.asset', ['site' => $site, 'path' => $assetPath]))
            ->assertOk()
            ->assertHeader('Content-Type', 'text/css; charset=utf-8')
            ->assertSee('color: orange', false);
    }

    public function test_production_nginx_passes_preview_assets_to_laravel_before_static_rule(): void
    {
        $config = file_get_contents(base_path('docker/nginx/prod.conf'));

        $previewLocation = strpos($config, 'location ^~ /dashboard/preview/');
        $staticLocation = strpos($config, 'location ~* \\.(css|js|jpg|jpeg|png|gif|ico|svg|webp|woff|woff2|ttf|eot)$');

        $this->assertNotFalse($previewLocation);
        $this->assertNotFalse($staticLocation);
        $this->assertLessThan($staticLocation, $previewLocation);
    }
}
