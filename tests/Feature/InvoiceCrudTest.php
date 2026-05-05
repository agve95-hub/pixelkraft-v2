<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class InvoiceCrudTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $email = 'inv@example.com'): User
    {
        return User::create([
            'name' => 'Invoice User',
            'email' => $email,
            'password' => Hash::make('password'),
            'role' => 'editor',
        ]);
    }

    private function makeSite(User $user): Site
    {
        return Site::create([
            'user_id' => $user->id,
            'name' => 'Invoice Site',
            'slug' => 'invoice-site-'.uniqid(),
            'repo_url' => 'https://github.com/example/inv',
            'branch' => 'main',
            'project_type' => 'static_html',
        ]);
    }

    private function invoicePayload(array $overrides = []): array
    {
        return array_merge([
            'invoice_date' => now()->toDateString(),
            'currency_code' => 'EUR',
        ], $overrides);
    }

    public function test_invoice_page_loads(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);

        $this->actingAs($user)
            ->get(route('sites.invoices', $site))
            ->assertOk()
            ->assertViewIs('dashboard.sites.invoices');
    }

    public function test_owner_can_create_invoice(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);

        $this->actingAs($user)
            ->post(route('sites.invoices.store', $site), $this->invoicePayload([
                'bill_to' => 'Acme Corp',
                'items' => [
                    ['description' => 'Web design', 'quantity' => 1, 'rate' => 500],
                ],
            ]))
            ->assertRedirect();

        $this->assertDatabaseHas('invoices', [
            'site_id' => $site->id,
            'bill_to' => 'Acme Corp',
            'status' => 'unpaid',
        ]);

        $invoice = Invoice::where('site_id', $site->id)->firstOrFail();
        $this->assertCount(1, $invoice->items);
        $this->assertSame('Web design', $invoice->items->first()->description);
    }

    public function test_creating_invoice_without_items_works(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);

        $this->actingAs($user)
            ->post(route('sites.invoices.store', $site), $this->invoicePayload())
            ->assertRedirect();

        $this->assertDatabaseHas('invoices', ['site_id' => $site->id, 'status' => 'unpaid']);
    }

    public function test_invoice_date_is_required(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);

        $this->actingAs($user)
            ->postJson(route('sites.invoices.store', $site), ['currency_code' => 'EUR'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['invoice_date']);
    }

    public function test_owner_can_update_invoice(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);

        $invoice = Invoice::create([
            'site_id' => $site->id,
            'number' => 'INV-001',
            'invoice_date' => now()->toDateString(),
            'currency_code' => 'EUR',
            'status' => 'unpaid',
            'tax_rate' => 0,
            'discount_percent' => 0,
            'payment_terms' => 'net30',
        ]);

        $this->actingAs($user)
            ->put(route('sites.invoices.update', [$site, $invoice]), $this->invoicePayload([
                'bill_to' => 'Updated Client',
                'items' => [
                    ['description' => 'Hosting', 'quantity' => 12, 'rate' => 10],
                ],
            ]))
            ->assertRedirect();

        $fresh = $invoice->fresh();
        $this->assertSame('Updated Client', $fresh->bill_to);
        $this->assertCount(1, $fresh->items);
    }

    public function test_owner_can_mark_invoice_as_paid(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);

        $invoice = Invoice::create([
            'site_id' => $site->id,
            'number' => 'INV-002',
            'invoice_date' => now()->toDateString(),
            'currency_code' => 'EUR',
            'status' => 'unpaid',
            'tax_rate' => 0,
            'discount_percent' => 0,
            'payment_terms' => 'net30',
        ]);

        $this->actingAs($user)
            ->post(route('sites.invoices.mark-paid', [$site, $invoice]))
            ->assertRedirect();

        $this->assertSame('paid', $invoice->fresh()->status);
        $this->assertNotNull($invoice->fresh()->paid_at);
    }

    public function test_owner_can_delete_invoice(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);

        $invoice = Invoice::create([
            'site_id' => $site->id,
            'number' => 'INV-003',
            'invoice_date' => now()->toDateString(),
            'currency_code' => 'EUR',
            'status' => 'unpaid',
            'tax_rate' => 0,
            'discount_percent' => 0,
            'payment_terms' => 'net30',
        ]);

        $this->actingAs($user)
            ->delete(route('sites.invoices.destroy', [$site, $invoice]))
            ->assertRedirect();

        $this->assertDatabaseMissing('invoices', ['id' => $invoice->id]);
    }

    public function test_other_user_cannot_access_invoices(): void
    {
        $owner = $this->makeUser('owner@inv.com');
        $other = $this->makeUser('other@inv.com');
        $site = $this->makeSite($owner);

        $this->actingAs($other)
            ->get(route('sites.invoices', $site))
            ->assertStatus(404);
    }

    public function test_unauthenticated_cannot_access_invoices(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);

        $this->get(route('sites.invoices', $site))->assertRedirect('/login');
    }
}
