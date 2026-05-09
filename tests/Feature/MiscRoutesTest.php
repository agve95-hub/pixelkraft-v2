<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Page;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MiscRoutesTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $email = 'misc@example.com'): User
    {
        return User::create([
            'name' => 'Misc User',
            'email' => $email,
            'password' => Hash::make('password'),
            'role' => 'editor',
        ]);
    }

    private function makeSite(User $user): Site
    {
        return Site::create([
            'user_id' => $user->id,
            'name' => 'Misc Site',
            'slug' => 'misc-site-'.uniqid(),
            'repo_url' => 'https://github.com/example/misc',
            'branch' => 'main',
            'project_type' => 'static_html',
        ]);
    }

    private function makeInvoice(Site $site): Invoice
    {
        return Invoice::create([
            'site_id' => $site->id,
            'number' => 'INV-'.uniqid(),
            'invoice_date' => now()->toDateString(),
            'currency_code' => 'EUR',
            'status' => 'unpaid',
            'tax_rate' => 0,
            'discount_percent' => 0,
            'payment_terms' => 'net30',
        ]);
    }

    // ── Invoice duplicate ─────────────────────────

    public function test_owner_can_duplicate_invoice(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);
        $invoice = $this->makeInvoice($site);

        $this->actingAs($user)
            ->post(route('sites.invoices.duplicate', [$site, $invoice]))
            ->assertRedirect();

        $this->assertSame(2, Invoice::where('site_id', $site->id)->count());

        $copy = Invoice::where('site_id', $site->id)
            ->where('id', '!=', $invoice->id)
            ->firstOrFail();

        $this->assertSame('unpaid', $copy->status);
        $this->assertNull($copy->paid_at);
    }

    public function test_cross_site_invoice_duplicate_is_blocked(): void
    {
        $owner = $this->makeUser('own@m.com');
        $other = $this->makeUser('oth@m.com');
        $site = $this->makeSite($owner);
        $invoice = $this->makeInvoice($site);

        $this->actingAs($other)
            ->post(route('sites.invoices.duplicate', [$site, $invoice]))
            ->assertStatus(404);

        $this->assertSame(1, Invoice::where('site_id', $site->id)->count());
    }

    // ── SEO meta update ───────────────────────────

    public function test_owner_can_update_page_seo_meta(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);

        $page = Page::create([
            'site_id' => $site->id,
            'file_path' => 'index.html',
            'url_path' => '/',
            'title' => 'Home',
        ]);

        $this->actingAs($user)
            ->put(route('seo.meta.update', [$site, $page]), [
                'title' => 'SEO Title',
                'meta_description' => 'A great description.',
                'og_title' => 'OG Title',
            ])
            ->assertRedirect();

        $fresh = $page->fresh();
        $this->assertSame('SEO Title', $fresh->title);
        $this->assertSame('A great description.', $fresh->meta_description);
    }

    public function test_seo_meta_page_loads(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);

        $page = Page::create([
            'site_id' => $site->id,
            'file_path' => 'about.html',
            'url_path' => '/about',
            'title' => 'About',
        ]);

        $this->actingAs($user)
            ->get(route('seo.meta', [$site, $page]))
            ->assertOk()
            ->assertViewIs('dashboard.seo.meta');
    }

    // ── Site destroy ──────────────────────────────

    public function test_owner_can_delete_site(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);

        $this->actingAs($user)
            ->deleteJson(route('sites.destroy', $site->id))
            ->assertOk()
            ->assertJson(['deleted' => true]);

        $this->assertDatabaseMissing('sites', ['id' => $site->id]);
    }

    public function test_other_user_cannot_delete_site(): void
    {
        $owner = $this->makeUser('own@d.com');
        $other = $this->makeUser('oth@d.com');
        $site = $this->makeSite($owner);

        $this->actingAs($other)
            ->deleteJson(route('sites.destroy', $site->id))
            ->assertStatus(404);

        $this->assertDatabaseHas('sites', ['id' => $site->id]);
    }

    // ── Maintenance preview ───────────────────────

    public function test_maintenance_preview_escapes_html(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);
        $site->update([
            'name' => '<img src=x onerror=alert(1)>',
            'maintenance_settings' => [
                'enabled' => true,
                'title' => '<script>alert("xss")</script>',
                'message' => 'Back soon',
                'allowed_ips' => [],
            ],
        ]);

        $response = $this->actingAs($user)
            ->get(route('sites.maintenance.preview', $site))
            ->assertOk();

        // The title should be HTML-escaped, not injected as raw script
        $this->assertStringNotContainsString('<script>alert', $response->content());
        $this->assertStringNotContainsString('<img src=x', $response->content());
        $this->assertStringContainsString('&lt;script&gt;', $response->content());
        $this->assertStringContainsString('&lt;img src=x', $response->content());
    }

    // ── System diagnostics (admin only) ───────────

    public function test_editor_cannot_access_system_diagnostics(): void
    {
        $user = $this->makeUser(); // role = editor

        $this->actingAs($user)
            ->get(route('system.diagnostics'))
            ->assertStatus(403);
    }

    public function test_admin_can_access_system_diagnostics(): void
    {
        $admin = User::create([
            'name' => 'Admin',
            'email' => 'admin-misc@example.com',
            'password' => Hash::make('password'),
            'role' => 'admin',
        ]);

        $this->actingAs($admin)
            ->get(route('system.diagnostics'))
            ->assertOk();
    }
}
