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

class AdditionalPoliciesTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $role = 'editor'): User
    {
        return User::create([
            'name' => 'U',
            'email' => 'pol2-'.uniqid().'@x.com',
            'password' => Hash::make('secret'),
            'role' => $role,
        ]);
    }

    private function makeSite(User $user): Site
    {
        return Site::create([
            'user_id' => $user->id,
            'name' => 'S',
            'slug' => 'pol2-'.uniqid(),
            'repo_url' => 'https://github.com/example/pol2',
            'branch' => 'main',
            'project_type' => 'static_html',
        ]);
    }

    // ── SitePolicy ───────────────────────────────

    public function test_site_policy_owner_can_view(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);

        $this->assertTrue((new SitePolicy)->view($user, $site));
    }

    public function test_site_policy_owner_can_update(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);

        $this->assertTrue((new SitePolicy)->update($user, $site));
    }

    public function test_site_policy_owner_can_delete(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);

        $this->assertTrue((new SitePolicy)->delete($user, $site));
    }

    public function test_site_policy_non_owner_cannot_view(): void
    {
        $owner = $this->makeUser();
        $other = $this->makeUser();
        $site = $this->makeSite($owner);

        $this->assertFalse((new SitePolicy)->view($other, $site));
    }

    public function test_site_policy_non_owner_cannot_update(): void
    {
        $owner = $this->makeUser();
        $other = $this->makeUser();
        $site = $this->makeSite($owner);

        $this->assertFalse((new SitePolicy)->update($other, $site));
    }

    public function test_site_policy_admin_bypasses_via_before(): void
    {
        $admin = $this->makeUser('admin');
        $owner = $this->makeUser();
        $site = $this->makeSite($owner);

        $this->assertTrue((new SitePolicy)->before($admin, 'view'));
        $this->assertNull((new SitePolicy)->before($owner, 'view'));
    }

    // ── BlogPostPolicy ───────────────────────────

    public function test_blog_post_policy_owner_can_view_own_post(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);

        $post = BlogPost::create([
            'site_id' => $site->id,
            'title' => 'Test Post',
            'slug' => 'test-post',
            'body' => 'content',
            'status' => 'draft',
        ]);

        $this->assertTrue((new BlogPostPolicy)->view($user, $post));
    }

    public function test_blog_post_policy_non_owner_cannot_view(): void
    {
        $owner = $this->makeUser();
        $other = $this->makeUser();
        $site = $this->makeSite($owner);

        $post = BlogPost::create([
            'site_id' => $site->id,
            'title' => 'T',
            'slug' => 'pol2-'.uniqid(),
            'body' => 'c',
            'status' => 'draft',
        ]);

        $this->assertFalse((new BlogPostPolicy)->view($other, $post));
    }

    public function test_blog_post_policy_admin_bypasses_via_before(): void
    {
        $admin = $this->makeUser('admin');
        $this->assertTrue((new BlogPostPolicy)->before($admin, 'update'));
    }

    // ── InvoicePolicy ────────────────────────────

    public function test_invoice_policy_owner_can_view(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);

        $invoice = Invoice::create([
            'site_id' => $site->id,
            'number' => 'INV-001',
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'status' => 'unpaid',
            'currency_code' => 'USD',
        ]);

        $this->assertTrue((new InvoicePolicy)->view($user, $invoice));
    }

    public function test_invoice_policy_non_owner_cannot_view(): void
    {
        $owner = $this->makeUser();
        $other = $this->makeUser();
        $site = $this->makeSite($owner);

        $invoice = Invoice::create([
            'site_id' => $site->id,
            'number' => 'INV-002',
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'status' => 'unpaid',
            'currency_code' => 'USD',
        ]);

        $this->assertFalse((new InvoicePolicy)->view($other, $invoice));
    }

    public function test_invoice_policy_admin_bypasses(): void
    {
        $admin = $this->makeUser('admin');
        $this->assertTrue((new InvoicePolicy)->before($admin, 'delete'));
    }

    // ── PagePolicy ───────────────────────────────

    public function test_page_policy_owner_can_view(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);

        $page = Page::create([
            'site_id' => $site->id,
            'file_path' => 'index.html',
            'url_path' => '/',
            'title' => 'Home',
        ]);

        $this->assertTrue((new PagePolicy)->view($user, $page));
    }

    public function test_page_policy_non_owner_cannot_update(): void
    {
        $owner = $this->makeUser();
        $other = $this->makeUser();
        $site = $this->makeSite($owner);

        $page = Page::create([
            'site_id' => $site->id,
            'file_path' => 'about.html',
            'url_path' => '/about',
            'title' => 'About',
        ]);

        $this->assertFalse((new PagePolicy)->update($other, $page));
    }

    public function test_page_policy_admin_bypasses(): void
    {
        $admin = $this->makeUser('admin');
        $this->assertTrue((new PagePolicy)->before($admin, 'view'));
    }
}
