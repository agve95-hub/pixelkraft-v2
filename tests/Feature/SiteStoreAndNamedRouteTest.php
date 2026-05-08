<?php

namespace Tests\Feature;

use App\Models\ContentTemplate;
use App\Models\NewsletterCampaign;
use App\Models\NewsletterSubscriber;
use App\Models\ProductListing;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Tests for routes previously covered only via URL strings.
 * Uses route() helper so these route names register as tested.
 */
class SiteStoreAndNamedRouteTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $email = 'named@example.com'): User
    {
        return User::create([
            'name' => 'Named User',
            'email' => $email,
            'password' => Hash::make('password'),
            'role' => 'editor',
        ]);
    }

    private function makeSite(User $user): Site
    {
        return Site::create([
            'user_id' => $user->id,
            'name' => 'Named Site',
            'slug' => 'named-site-'.uniqid(),
            'repo_url' => 'https://github.com/example/named',
            'branch' => 'main',
            'project_type' => 'static_html',
        ]);
    }

    // ── sites.store ───────────────────────────────

    public function test_sites_store_creates_site_via_route_name(): void
    {
        Queue::fake();

        $user = $this->makeUser();

        $response = $this->actingAs($user)
            ->postJson(route('sites.store'), [
                'name' => 'Route Name Site',
                'project_type' => 'static_html',
                'source_type' => 'github',
                'repo_url' => 'https://github.com/example/rn',
                'branch' => 'main',
            ]);

        $response->assertOk()->assertJsonStructure(['siteId']);
        $this->assertDatabaseHas('sites', ['name' => 'Route Name Site']);
    }

    public function test_sites_store_rejects_invalid_source_type(): void
    {
        $user = $this->makeUser('rej@named.com');

        $this->actingAs($user)
            ->postJson(route('sites.store'), [
                'name' => 'Test',
                'project_type' => 'static_html',
                'source_type' => 'server_path', // disallowed
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['source_type']);
    }

    // ── Newsletter subscribers ────────────────────

    public function test_sites_store_rejects_unsafe_build_command(): void
    {
        $user = $this->makeUser('unsafe-build@named.com');

        $this->actingAs($user)
            ->postJson(route('sites.store'), [
                'name' => 'Unsafe Build',
                'project_type' => 'static_html',
                'source_type' => 'github',
                'repo_url' => 'https://github.com/example/unsafe-build',
                'branch' => 'main',
                'build_command' => 'npm run build; curl https://example.com',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['build_command']);
    }

    public function test_sites_store_rejects_invalid_project_type_and_branch(): void
    {
        $user = $this->makeUser('unsafe-project@named.com');

        $this->actingAs($user)
            ->postJson(route('sites.store'), [
                'name' => 'Unsafe Project',
                'project_type' => 'rails',
                'source_type' => 'github',
                'repo_url' => 'https://github.com/example/unsafe-project',
                'branch' => '../main',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['project_type', 'branch']);
    }

    public function test_sites_store_rejects_domain_control_characters(): void
    {
        $user = $this->makeUser('unsafe-domain@named.com');

        $this->actingAs($user)
            ->postJson(route('sites.store'), [
                'name' => 'Unsafe Domain',
                'project_type' => 'static_html',
                'source_type' => 'upload',
                'domain' => "example.com;\nserver_name evil.com",
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['domain']);
    }

    public function test_sites_subscribers_store_via_route_name(): void
    {
        $user = $this->makeUser('sub@named.com');
        $site = $this->makeSite($user);

        $this->actingAs($user)
            ->post(route('sites.subscribers.store', $site), [
                'email' => 'new@subscriber.com',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('newsletter_subscribers', [
            'site_id' => $site->id,
            'email' => 'new@subscriber.com',
        ]);
    }

    public function test_sites_subscribers_destroy_via_route_name(): void
    {
        $user = $this->makeUser('subdel@named.com');
        $site = $this->makeSite($user);

        $sub = NewsletterSubscriber::create([
            'site_id' => $site->id,
            'email' => 'del@subscriber.com',
            'status' => 'active',
        ]);

        $this->actingAs($user)
            ->delete(route('sites.subscribers.destroy', [$site, $sub]))
            ->assertRedirect();

        $this->assertDatabaseMissing('newsletter_subscribers', ['id' => $sub->id]);
    }

    public function test_sites_subscribers_import_via_route_name(): void
    {
        $user = $this->makeUser('subimp@named.com');
        $site = $this->makeSite($user);

        $csv = "email,name\ntest@import.com,Test\n";
        $file = UploadedFile::fake()->createWithContent('subs.csv', $csv);

        $this->actingAs($user)
            ->post(route('sites.subscribers.import', $site), ['csv' => $file])
            ->assertRedirect();

        $this->assertDatabaseHas('newsletter_subscribers', [
            'site_id' => $site->id,
            'email' => 'test@import.com',
        ]);
    }

    // ── Newsletter campaigns ──────────────────────

    public function test_sites_newsletters_store_via_route_name(): void
    {
        $user = $this->makeUser('nl@named.com');
        $site = $this->makeSite($user);

        $this->actingAs($user)
            ->post(route('sites.newsletters.store', $site), [
                'subject' => 'Route Named Campaign',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('newsletter_campaigns', [
            'site_id' => $site->id,
            'subject' => 'Route Named Campaign',
            'status' => 'draft',
        ]);
    }

    public function test_sites_newsletters_update_via_route_name(): void
    {
        $user = $this->makeUser('nlup@named.com');
        $site = $this->makeSite($user);

        $campaign = NewsletterCampaign::create([
            'site_id' => $site->id,
            'subject' => 'Old Subject',
            'body_html' => '<p>Old</p>',
            'status' => 'draft',
        ]);

        $this->actingAs($user)
            ->put(route('sites.newsletters.update', [$site, $campaign]), [
                'subject' => 'Updated Subject',
            ])
            ->assertRedirect();

        $this->assertSame('Updated Subject', $campaign->fresh()->subject);
    }

    public function test_sites_newsletters_send_via_route_name(): void
    {
        $user = $this->makeUser('nlsend@named.com');
        $site = $this->makeSite($user);

        $campaign = NewsletterCampaign::create([
            'site_id' => $site->id,
            'subject' => 'Send Me',
            'body_html' => '<p>Hi</p>',
            'status' => 'draft',
        ]);

        $this->actingAs($user)
            ->post(route('sites.newsletters.send', [$site, $campaign]))
            ->assertRedirect();

        $this->assertSame('sending', $campaign->fresh()->status);
    }

    public function test_sites_newsletters_destroy_via_route_name(): void
    {
        $user = $this->makeUser('nldel@named.com');
        $site = $this->makeSite($user);

        $campaign = NewsletterCampaign::create([
            'site_id' => $site->id,
            'subject' => 'Delete Me',
            'body_html' => '<p>Del</p>',
            'status' => 'draft',
        ]);

        $this->actingAs($user)
            ->delete(route('sites.newsletters.destroy', [$site, $campaign]))
            ->assertRedirect();

        $this->assertDatabaseMissing('newsletter_campaigns', ['id' => $campaign->id]);
    }

    // ── Templates ─────────────────────────────────

    public function test_templates_store_via_route_name(): void
    {
        $user = $this->makeUser('tmpl@named.com');
        $site = $this->makeSite($user);

        $this->actingAs($user)
            ->post(route('templates.store', $site), [
                'name' => 'Route Named Template',
                'type' => 'newsletter',
                'html_template' => '<p>Hello {{name}}</p>',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('content_templates', [
            'site_id' => $site->id,
            'name' => 'Route Named Template',
        ]);
    }

    public function test_templates_update_via_route_name(): void
    {
        $user = $this->makeUser('tmplup@named.com');
        $site = $this->makeSite($user);

        $template = ContentTemplate::create([
            'site_id' => $site->id,
            'name' => 'Old Template',
            'html_template' => '<p>Old</p>',
        ]);

        $this->actingAs($user)
            ->put(route('templates.update', [$site, $template]), [
                'name' => 'New Template',
                'html_template' => '<p>New</p>',
            ])
            ->assertRedirect();

        $this->assertSame('New Template', $template->fresh()->name);
    }

    public function test_templates_destroy_via_route_name(): void
    {
        $user = $this->makeUser('tmpldel@named.com');
        $site = $this->makeSite($user);

        $template = ContentTemplate::create([
            'site_id' => $site->id,
            'name' => 'Delete Template',
            'html_template' => '<p>Del</p>',
        ]);

        $this->actingAs($user)
            ->delete(route('templates.destroy', [$site, $template]))
            ->assertRedirect();

        $this->assertDatabaseMissing('content_templates', ['id' => $template->id]);
    }

    // ── products.edit ─────────────────────────────

    public function test_product_edit_page_loads_via_route_name(): void
    {
        $user = $this->makeUser('prdedit@named.com');
        $site = $this->makeSite($user);

        $product = ProductListing::create([
            'site_id' => $site->id,
            'name' => 'Edit Me',
            'price' => '9.99',
            'currency' => 'EUR',
            'status' => 'draft',
        ]);

        $this->actingAs($user)
            ->get(route('products.edit', [$site, $product]))
            ->assertOk()
            ->assertViewIs('dashboard.content.product-edit');
    }
}
