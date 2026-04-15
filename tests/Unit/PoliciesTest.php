<?php

namespace Tests\Unit;

use App\Models\BlogPost;
use App\Models\Invoice;
use App\Models\Page;
use App\Models\Site;
use App\Models\User;
use App\Policies\BlogPostPolicy;
use App\Policies\InvoicePolicy;
use App\Policies\PagePolicy;
use App\Policies\SitePolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PoliciesTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdmin(): User
    {
        static $n = 0;
        $n++;

        return User::create([
            'name' => "Admin {$n}",
            'email' => "admin{$n}-pol@example.com",
            'password' => Hash::make('password'),
            'role' => 'admin',
        ]);
    }

    private function makeEditor(): User
    {
        static $n = 0;
        $n++;

        return User::create([
            'name' => "Editor {$n}",
            'email' => "editor{$n}-pol@example.com",
            'password' => Hash::make('password'),
            'role' => 'editor',
        ]);
    }

    private function makeSite(User $owner): Site
    {
        static $n = 0;
        $n++;

        return Site::create([
            'user_id' => $owner->id,
            'name' => "Site {$n}",
            'slug' => "site-pol-{$n}",
            'repo_url' => 'https://github.com/example/pol.git',
            'branch' => 'main',
        ]);
    }

    // ── SitePolicy ───────────────────────────────────────────────────────────

    public function test_site_owner_can_view_their_site(): void
    {
        $owner = $this->makeEditor();
        $site = $this->makeSite($owner);

        $this->assertTrue((new SitePolicy)->view($owner, $site));
    }

    public function test_site_non_owner_cannot_view_site(): void
    {
        $owner = $this->makeEditor();
        $other = $this->makeEditor();
        $site = $this->makeSite($owner);

        $this->assertFalse((new SitePolicy)->view($other, $site));
    }

    public function test_admin_bypasses_site_policy_via_before(): void
    {
        $admin = $this->makeAdmin();
        $owner = $this->makeEditor();
        $site = $this->makeSite($owner);

        // before() returns true for admin, short-circuiting view()
        $this->assertTrue((new SitePolicy)->before($admin, 'view'));
        $this->assertTrue((new SitePolicy)->before($admin, 'delete'));
    }

    public function test_before_returns_null_for_non_admin(): void
    {
        $editor = $this->makeEditor();

        $this->assertNull((new SitePolicy)->before($editor, 'view'));
    }

    public function test_site_owner_can_update_and_delete(): void
    {
        $owner = $this->makeEditor();
        $site = $this->makeSite($owner);
        $policy = new SitePolicy;

        $this->assertTrue($policy->update($owner, $site));
        $this->assertTrue($policy->delete($owner, $site));
    }

    public function test_site_non_owner_cannot_update_or_delete(): void
    {
        $owner = $this->makeEditor();
        $other = $this->makeEditor();
        $site = $this->makeSite($owner);
        $policy = new SitePolicy;

        $this->assertFalse($policy->update($other, $site));
        $this->assertFalse($policy->delete($other, $site));
    }

    // ── InvoicePolicy ────────────────────────────────────────────────────────

    public function test_invoice_owner_can_view_update_delete(): void
    {
        $owner = $this->makeEditor();
        $site = $this->makeSite($owner);

        $invoice = Invoice::create([
            'site_id' => $site->id,
            'number' => 'INV-2026-001',
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'status' => 'unpaid',
            'currency_code' => 'USD',
        ]);

        $policy = new InvoicePolicy;

        $this->assertTrue($policy->view($owner, $invoice));
        $this->assertTrue($policy->update($owner, $invoice));
        $this->assertTrue($policy->delete($owner, $invoice));
    }

    public function test_invoice_non_owner_cannot_view_update_delete(): void
    {
        $owner = $this->makeEditor();
        $other = $this->makeEditor();
        $site = $this->makeSite($owner);

        $invoice = Invoice::create([
            'site_id' => $site->id,
            'number' => 'INV-2026-002',
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'status' => 'unpaid',
            'currency_code' => 'USD',
        ]);

        $policy = new InvoicePolicy;

        $this->assertFalse($policy->view($other, $invoice));
        $this->assertFalse($policy->update($other, $invoice));
        $this->assertFalse($policy->delete($other, $invoice));
    }

    public function test_admin_bypasses_invoice_policy(): void
    {
        $admin = $this->makeAdmin();

        $this->assertTrue((new InvoicePolicy)->before($admin, 'delete'));
    }

    // ── BlogPostPolicy ───────────────────────────────────────────────────────

    public function test_blog_post_owner_can_view_update_delete(): void
    {
        $owner = $this->makeEditor();
        $site = $this->makeSite($owner);

        $post = BlogPost::create([
            'site_id' => $site->id,
            'title' => 'Hello',
            'slug' => 'hello',
            'body' => '',
            'status' => 'draft',
        ]);

        $policy = new BlogPostPolicy;

        $this->assertTrue($policy->view($owner, $post));
        $this->assertTrue($policy->update($owner, $post));
        $this->assertTrue($policy->delete($owner, $post));
    }

    public function test_blog_post_non_owner_cannot_view_update_delete(): void
    {
        $owner = $this->makeEditor();
        $other = $this->makeEditor();
        $site = $this->makeSite($owner);

        $post = BlogPost::create([
            'site_id' => $site->id,
            'title' => 'Hello',
            'slug' => 'hello-2',
            'body' => '',
            'status' => 'draft',
        ]);

        $policy = new BlogPostPolicy;

        $this->assertFalse($policy->view($other, $post));
        $this->assertFalse($policy->update($other, $post));
        $this->assertFalse($policy->delete($other, $post));
    }

    // ── PagePolicy ───────────────────────────────────────────────────────────

    public function test_page_owner_can_view_and_update(): void
    {
        $owner = $this->makeEditor();
        $site = $this->makeSite($owner);

        $page = Page::create([
            'site_id' => $site->id,
            'file_path' => 'index.html',
            'url_path' => '/',
        ]);

        $policy = new PagePolicy;

        $this->assertTrue($policy->view($owner, $page));
        $this->assertTrue($policy->update($owner, $page));
    }

    public function test_page_non_owner_cannot_view_or_update(): void
    {
        $owner = $this->makeEditor();
        $other = $this->makeEditor();
        $site = $this->makeSite($owner);

        $page = Page::create([
            'site_id' => $site->id,
            'file_path' => 'about.html',
            'url_path' => '/about',
        ]);

        $policy = new PagePolicy;

        $this->assertFalse($policy->view($other, $page));
        $this->assertFalse($policy->update($other, $page));
    }

    public function test_admin_bypasses_page_policy(): void
    {
        $admin = $this->makeAdmin();

        $this->assertTrue((new PagePolicy)->before($admin, 'update'));
    }
}
