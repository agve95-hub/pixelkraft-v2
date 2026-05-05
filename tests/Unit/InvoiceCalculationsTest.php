<?php

namespace Tests\Unit;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class InvoiceCalculationsTest extends TestCase
{
    use RefreshDatabase;

    private function makeInvoice(array $attrs = []): Invoice
    {
        $user = User::create([
            'name' => 'U',
            'email' => 'inv-calc-'.uniqid().'@x.com',
            'password' => Hash::make('password'),
            'role' => 'editor',
        ]);

        $site = Site::create([
            'user_id' => $user->id,
            'name' => 'S',
            'slug' => 'inv-calc-'.uniqid(),
            'repo_url' => 'https://github.com/example/s',
            'branch' => 'main',
            'project_type' => 'static_html',
        ]);

        return Invoice::create(array_merge([
            'site_id' => $site->id,
            'number' => 'INV-'.uniqid(),
            'invoice_date' => now()->toDateString(),
            'currency_code' => 'EUR',
            'status' => 'unpaid',
            'tax_rate' => 0,
            'discount_percent' => 0,
            'payment_terms' => 'net30',
        ], $attrs));
    }

    private function addItem(Invoice $invoice, float $qty, float $rate, int $sort = 0): InvoiceItem
    {
        return $invoice->items()->create([
            'description' => 'Item',
            'quantity' => $qty,
            'rate' => $rate,
            'sort_order' => $sort,
        ]);
    }

    // ── subtotal ──────────────────────────────────

    public function test_subtotal_is_zero_with_no_items(): void
    {
        $invoice = $this->makeInvoice();
        $invoice->load('items');
        $this->assertSame('0.00', $invoice->subtotal());
    }

    public function test_subtotal_sums_qty_times_rate(): void
    {
        $invoice = $this->makeInvoice();
        $this->addItem($invoice, 2, 100);   // 200
        $this->addItem($invoice, 3, 50.50); // 151.50
        $invoice->load('items');
        $this->assertSame('351.50', $invoice->subtotal());
    }

    // ── discount ──────────────────────────────────

    public function test_discount_amount_is_zero_when_no_discount(): void
    {
        $invoice = $this->makeInvoice(['discount_percent' => 0]);
        $this->addItem($invoice, 1, 200);
        $invoice->load('items');
        $this->assertSame('0.00', $invoice->discountAmount());
    }

    public function test_discount_amount_calculated_correctly(): void
    {
        $invoice = $this->makeInvoice(['discount_percent' => 10]);
        $this->addItem($invoice, 1, 500); // subtotal = 500, 10% = 50
        $invoice->load('items');
        $this->assertSame('50.00', $invoice->discountAmount());
    }

    public function test_amount_after_discount_is_correct(): void
    {
        $invoice = $this->makeInvoice(['discount_percent' => 20]);
        $this->addItem($invoice, 1, 1000); // 1000 - 200 = 800
        $invoice->load('items');
        $this->assertSame('800.00', $invoice->amountAfterDiscount());
    }

    // ── tax ───────────────────────────────────────

    public function test_tax_amount_is_zero_with_no_tax(): void
    {
        $invoice = $this->makeInvoice(['tax_rate' => 0]);
        $this->addItem($invoice, 1, 100);
        $invoice->load('items');
        $this->assertSame('0.00', $invoice->taxAmount());
    }

    public function test_tax_is_calculated_on_discounted_amount(): void
    {
        $invoice = $this->makeInvoice(['tax_rate' => 20, 'discount_percent' => 50]);
        $this->addItem($invoice, 1, 200); // subtotal=200, after discount=100, tax=20
        $invoice->load('items');
        $this->assertSame('20.00', $invoice->taxAmount());
    }

    public function test_total_is_after_discount_plus_tax(): void
    {
        $invoice = $this->makeInvoice(['tax_rate' => 10, 'discount_percent' => 10]);
        $this->addItem($invoice, 1, 1000); // 1000 - 100 = 900, +90 tax = 990
        $invoice->load('items');
        $this->assertSame('990.00', $invoice->total());
    }

    // ── isOverdue ─────────────────────────────────

    public function test_is_not_overdue_when_paid(): void
    {
        $invoice = $this->makeInvoice([
            'status' => 'paid',
            'due_date' => now()->subDays(30)->toDateString(),
        ]);
        $this->assertFalse($invoice->isOverdue());
    }

    public function test_is_not_overdue_when_no_due_date(): void
    {
        $invoice = $this->makeInvoice(['status' => 'unpaid', 'due_date' => null]);
        $this->assertFalse($invoice->isOverdue());
    }

    public function test_is_overdue_when_due_date_passed(): void
    {
        $invoice = $this->makeInvoice([
            'status' => 'unpaid',
            'due_date' => now()->subDays(1)->toDateString(),
        ]);
        $this->assertTrue($invoice->isOverdue());
    }

    public function test_is_not_overdue_when_due_today(): void
    {
        $invoice = $this->makeInvoice([
            'status' => 'unpaid',
            'due_date' => now()->toDateString(),
        ]);
        $this->assertFalse($invoice->isOverdue());
    }

    // ── displayStatus ─────────────────────────────

    public function test_display_status_paid(): void
    {
        $invoice = $this->makeInvoice(['status' => 'paid']);
        $this->assertSame('Paid', $invoice->displayStatus());
    }

    public function test_display_status_unpaid_not_overdue(): void
    {
        $invoice = $this->makeInvoice([
            'status' => 'unpaid',
            'due_date' => now()->addDays(30)->toDateString(),
        ]);
        $this->assertSame('Unpaid', $invoice->displayStatus());
    }

    public function test_display_status_overdue(): void
    {
        $invoice = $this->makeInvoice([
            'status' => 'unpaid',
            'due_date' => now()->subDays(5)->toDateString(),
        ]);
        $this->assertSame('Overdue', $invoice->displayStatus());
    }

    // ── currencySymbol ────────────────────────────

    public function test_currency_symbol_eur(): void
    {
        $this->assertSame('€', Invoice::currencySymbol('EUR'));
    }

    public function test_currency_symbol_usd(): void
    {
        $this->assertSame('$', Invoice::currencySymbol('USD'));
    }

    public function test_currency_symbol_gbp(): void
    {
        $this->assertSame('£', Invoice::currencySymbol('GBP'));
    }

    public function test_currency_symbol_unknown_returns_code(): void
    {
        $this->assertSame('XYZ ', Invoice::currencySymbol('xyz'));
    }

    // ── numberTrailingSequence ────────────────────

    public function test_trailing_sequence_parsed_from_number(): void
    {
        $invoice = $this->makeInvoice(['number' => 'INV-2026-007']);
        $this->assertSame(7, $invoice->numberTrailingSequence());
    }

    public function test_trailing_sequence_zero_for_non_matching_format(): void
    {
        $invoice = $this->makeInvoice(['number' => 'CUSTOM']);
        $this->assertSame(0, $invoice->numberTrailingSequence());
    }

    // ── nextNumberForSite ─────────────────────────

    public function test_next_number_starts_at_001_for_new_site(): void
    {
        $user = User::create([
            'name' => 'NN',
            'email' => 'nn-'.uniqid().'@x.com',
            'password' => Hash::make('password'),
            'role' => 'editor',
        ]);
        $site = Site::create([
            'user_id' => $user->id,
            'name' => 'NN',
            'slug' => 'nn-'.uniqid(),
            'repo_url' => 'https://github.com/example/nn',
            'branch' => 'main',
            'project_type' => 'static_html',
        ]);

        $year = now()->year;
        $this->assertSame("INV-{$year}-001", Invoice::nextNumberForSite($site));
    }

    public function test_next_number_increments_from_existing(): void
    {
        $user = User::create([
            'name' => 'NI',
            'email' => 'ni-'.uniqid().'@x.com',
            'password' => Hash::make('password'),
            'role' => 'editor',
        ]);
        $site = Site::create([
            'user_id' => $user->id,
            'name' => 'NI',
            'slug' => 'ni-'.uniqid(),
            'repo_url' => 'https://github.com/example/ni',
            'branch' => 'main',
            'project_type' => 'static_html',
        ]);

        $year = now()->year;
        Invoice::create([
            'site_id' => $site->id,
            'number' => "INV-{$year}-005",
            'invoice_date' => now()->toDateString(),
            'currency_code' => 'EUR',
            'status' => 'unpaid',
            'tax_rate' => 0,
            'discount_percent' => 0,
            'payment_terms' => 'net30',
        ]);

        $this->assertSame("INV-{$year}-006", Invoice::nextNumberForSite($site));
    }
}
