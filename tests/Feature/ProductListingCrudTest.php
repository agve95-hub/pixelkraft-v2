<?php

namespace Tests\Feature;

use App\Models\ProductListing;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProductListingCrudTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $email = 'prod@example.com'): User
    {
        return User::create([
            'name' => 'Prod User',
            'email' => $email,
            'password' => Hash::make('password'),
            'role' => 'editor',
        ]);
    }

    private function makeSite(User $user): Site
    {
        return Site::create([
            'user_id' => $user->id,
            'name' => 'Product Site',
            'slug' => 'product-site-'.uniqid(),
            'repo_url' => 'https://github.com/example/products',
            'branch' => 'main',
            'project_type' => 'static_html',
        ]);
    }

    public function test_products_index_loads(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);

        $this->actingAs($user)
            ->get(route('products.index', $site))
            ->assertOk()
            ->assertViewIs('dashboard.content.product-index')
            ->assertViewHas('site')
            ->assertViewHas('products');
    }

    public function test_create_page_loads(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);

        $this->actingAs($user)
            ->get(route('products.create', $site))
            ->assertOk()
            ->assertViewIs('dashboard.content.product-create');
    }

    public function test_owner_can_create_product(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);

        $this->actingAs($user)
            ->post(route('products.store', $site), [
                'name' => 'Widget Pro',
                'price' => '49.99',
                'currency' => 'EUR',
                'status' => 'draft',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('product_listings', [
            'site_id' => $site->id,
            'name' => 'Widget Pro',
            'status' => 'draft',
        ]);
    }

    public function test_price_defaults_to_zero_minimum(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);

        $this->actingAs($user)
            ->postJson(route('products.store', $site), [
                'name' => 'Free Thing',
                'price' => '-1',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['price']);
    }

    public function test_name_is_required(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);

        $this->actingAs($user)
            ->postJson(route('products.store', $site), ['name' => '', 'price' => '10'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_owner_can_update_product(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);

        $product = ProductListing::create([
            'site_id' => $site->id,
            'name' => 'Old Widget',
            'price' => '19.99',
            'currency' => 'EUR',
            'status' => 'draft',
        ]);

        $this->actingAs($user)
            ->put(route('products.update', [$site, $product]), [
                'name' => 'New Widget',
                'price' => '29.99',
                'status' => 'active',
            ])
            ->assertRedirect();

        $fresh = $product->fresh();
        $this->assertSame('New Widget', $fresh->name);
        $this->assertSame('active', $fresh->status);
    }

    public function test_owner_can_delete_product(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);

        $product = ProductListing::create([
            'site_id' => $site->id,
            'name' => 'Gone Widget',
            'price' => '9.99',
            'currency' => 'EUR',
            'status' => 'draft',
        ]);

        $this->actingAs($user)
            ->delete(route('products.destroy', [$site, $product]))
            ->assertRedirect();

        $this->assertDatabaseMissing('product_listings', ['id' => $product->id]);
    }

    public function test_user_cannot_edit_another_sites_product(): void
    {
        $owner = $this->makeUser('owner@p.com');
        $other = $this->makeUser('other@p.com');
        $site = $this->makeSite($owner);

        $product = ProductListing::create([
            'site_id' => $site->id,
            'name' => 'Protected',
            'price' => '9.99',
            'currency' => 'EUR',
            'status' => 'draft',
        ]);

        // Site is not visible to other user → 404 from EnsureSiteAccess middleware
        $this->actingAs($other)
            ->put(route('products.update', [$site, $product]), ['name' => 'Stolen', 'price' => '0'])
            ->assertStatus(404);

        $this->assertSame('Protected', $product->fresh()->name);
    }

    public function test_unauthenticated_user_cannot_access_products(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);

        $this->get(route('products.index', $site))->assertRedirect('/login');
    }
}
