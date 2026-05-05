<?php

namespace Tests\Feature;

use App\Enums\DeployStatus;
use App\Models\BlogPost;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Smoke tests: every main dashboard page must return 200 for an authenticated owner.
 */
class DashboardPageLoadTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Site $site;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'name' => 'Page Load User',
            'email' => 'pageload@example.com',
            'password' => Hash::make('password'),
            'role' => 'editor',
        ]);

        $this->site = Site::create([
            'user_id' => $this->user->id,
            'name' => 'Test Site',
            'slug' => 'test-site-pl',
            'repo_url' => 'https://github.com/example/test',
            'branch' => 'main',
            'project_type' => 'static_html',
            'deploy_status' => DeployStatus::Live,
        ]);
    }

    private function fetch(string $route, mixed $params = []): TestResponse
    {
        return $this->actingAs($this->user)->get(route($route, $params));
    }

    // ── Global dashboard ──────────────────────────

    public function test_dashboard_index_loads(): void
    {
        $this->fetch('dashboard')->assertOk();
    }

    // ── Sites ─────────────────────────────────────

    public function test_sites_index_loads(): void
    {
        $this->fetch('sites.index')->assertOk()->assertViewIs('dashboard.sites.index');
    }

    public function test_sites_create_loads(): void
    {
        $this->fetch('sites.create')->assertOk()->assertViewIs('dashboard.sites.create');
    }

    public function test_site_show_loads(): void
    {
        $this->fetch('sites.show', $this->site)->assertOk()->assertViewIs('dashboard.sites.show');
    }

    public function test_site_pages_loads(): void
    {
        $this->fetch('sites.pages', $this->site)->assertOk();
    }

    public function test_site_inbox_loads(): void
    {
        $this->fetch('sites.inbox', $this->site)->assertOk()->assertViewIs('dashboard.sites.inbox');
    }

    public function test_site_invoices_loads(): void
    {
        $this->fetch('sites.invoices', $this->site)->assertOk()->assertViewIs('dashboard.sites.invoices');
    }

    public function test_site_campaigns_loads(): void
    {
        $this->fetch('sites.campaigns', $this->site)->assertOk()->assertViewIs('dashboard.sites.campaigns');
    }

    public function test_site_expenses_loads(): void
    {
        $this->fetch('sites.expenses', $this->site)->assertOk()->assertViewIs('dashboard.sites.expenses');
    }

    public function test_site_reminders_loads(): void
    {
        $this->fetch('sites.reminders', $this->site)->assertOk()->assertViewIs('dashboard.sites.reminders');
    }

    public function test_site_reports_loads(): void
    {
        $this->fetch('sites.reports', $this->site)->assertOk()->assertViewIs('dashboard.sites.reports');
    }

    public function test_site_analytics_loads(): void
    {
        $this->fetch('sites.analytics', $this->site)->assertOk()->assertViewIs('dashboard.sites.analytics');
    }

    public function test_site_maintenance_loads(): void
    {
        $this->fetch('sites.maintenance', $this->site)->assertOk()->assertViewIs('dashboard.sites.maintenance');
    }

    public function test_site_settings_loads(): void
    {
        $this->fetch('sites.settings', $this->site)->assertOk()->assertViewIs('dashboard.sites.settings');
    }

    public function test_site_files_loads(): void
    {
        $this->fetch('sites.files', $this->site)->assertOk()->assertViewIs('dashboard.sites.files');
    }

    // ── Content ───────────────────────────────────

    public function test_blog_index_loads(): void
    {
        $this->fetch('blog.index', $this->site)->assertOk()->assertViewIs('dashboard.content.blog-index');
    }

    public function test_blog_create_loads(): void
    {
        $this->fetch('blog.create', $this->site)->assertOk()->assertViewIs('dashboard.content.blog-create');
    }

    public function test_blog_edit_loads(): void
    {
        $post = BlogPost::create([
            'site_id' => $this->site->id,
            'title' => 'Test Post',
            'slug' => 'test-post',
            'status' => 'draft',
        ]);

        $this->fetch('blog.edit', [$this->site, $post])->assertOk()->assertViewIs('dashboard.content.blog-edit');
    }

    public function test_products_index_loads(): void
    {
        $this->fetch('products.index', $this->site)->assertOk()->assertViewIs('dashboard.content.product-index');
    }

    public function test_products_create_loads(): void
    {
        $this->fetch('products.create', $this->site)->assertOk()->assertViewIs('dashboard.content.product-create');
    }

    public function test_templates_index_loads(): void
    {
        $this->fetch('templates.index', $this->site)->assertOk()->assertViewIs('dashboard.content.templates');
    }

    // ── SEO ───────────────────────────────────────

    public function test_seo_redirects_loads(): void
    {
        $this->fetch('seo.redirects', $this->site)->assertOk()->assertViewIs('dashboard.seo.redirects');
    }

    // ── Email ─────────────────────────────────────

    public function test_site_subscribers_loads(): void
    {
        $this->fetch('sites.subscribers', $this->site)->assertOk()->assertViewIs('dashboard.email.subscribers');
    }

    public function test_site_newsletters_loads(): void
    {
        $this->fetch('sites.newsletters', $this->site)->assertOk()->assertViewIs('dashboard.email.campaigns');
    }

    // ── Global email + analytics ──────────────────

    public function test_global_analytics_loads(): void
    {
        $this->fetch('analytics')->assertOk()->assertViewIs('dashboard.analytics.index');
    }

    public function test_global_inbox_loads(): void
    {
        $this->fetch('inbox')->assertOk()->assertViewIs('dashboard.email.inbox');
    }

    public function test_global_subscribers_loads(): void
    {
        $this->fetch('subscribers')->assertOk()->assertViewIs('dashboard.email.subscribers');
    }

    public function test_global_newsletters_loads(): void
    {
        $this->fetch('newsletters')->assertOk()->assertViewIs('dashboard.email.campaigns');
    }

    // ── Settings ──────────────────────────────────

    public function test_settings_page_loads(): void
    {
        $this->fetch('settings')->assertOk()->assertViewIs('dashboard.settings.index');
    }

    public function test_system_diagnostics_loads_for_admin(): void
    {
        $admin = User::create([
            'name' => 'Admin',
            'email' => 'admin-pl@example.com',
            'password' => Hash::make('password'),
            'role' => 'admin',
        ]);

        $this->actingAs($admin)->get(route('system.diagnostics'))->assertOk();
    }

    // ── Auth guard ────────────────────────────────

    public function test_dashboard_redirects_unauthenticated_to_login(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }
}
