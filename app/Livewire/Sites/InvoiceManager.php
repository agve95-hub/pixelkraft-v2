<?php

namespace App\Livewire\Sites;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Support\SiteAccess;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Component;

class InvoiceManager extends Component
{
    public string $siteId;

    public string $screen = 'index';

    public ?string $activeInvoiceId = null;

    /** @var array<string, mixed>|null Built from the create form for preview-only rendering */
    public ?array $previewData = null;

    public string $previewReturnScreen = 'create';

    public string $form_number = '';

    public string $form_invoice_date = '';

    public string $form_due_date = '';

    public string $form_payment_terms = 'net30';

    public string $form_currency_code = 'EUR';

    public string $form_tax_rate = '0';

    public string $form_discount_percent = '0';

    public string $form_notes = '';

    public string $form_payment_details = '';

    public string $form_from_address = '';

    public string $form_bill_to = '';

    /** @var array<int, array{description: string, quantity: string, rate: string}> */
    public array $form_lines = [];

    /** @var array<int, array{value: string, label: string, days: int|null}> */
    public array $paymentTermOptions = [];

    /** @var array<int, array{code: string, symbol: string, name: string}> */
    public array $currencyOptions = [];

    public function mount(string $siteId): void
    {
        $this->siteId = $siteId;
        $this->paymentTermOptions = [
            ['value' => 'due_on_receipt', 'label' => 'Due on receipt', 'days' => 0],
            ['value' => 'net7', 'label' => 'Net 7', 'days' => 7],
            ['value' => 'net15', 'label' => 'Net 15', 'days' => 15],
            ['value' => 'net30', 'label' => 'Net 30', 'days' => 30],
            ['value' => 'net45', 'label' => 'Net 45', 'days' => 45],
            ['value' => 'net60', 'label' => 'Net 60', 'days' => 60],
            ['value' => 'net90', 'label' => 'Net 90', 'days' => 90],
        ];
        $this->currencyOptions = [
            ['code' => 'EUR', 'symbol' => '€', 'name' => 'Euro'],
            ['code' => 'USD', 'symbol' => '$', 'name' => 'US Dollar'],
            ['code' => 'CHF', 'symbol' => 'CHF', 'name' => 'Swiss Franc'],
            ['code' => 'GBP', 'symbol' => '£', 'name' => 'British Pound'],
            ['code' => 'SEK', 'symbol' => 'kr', 'name' => 'Swedish Krona'],
            ['code' => 'NOK', 'symbol' => 'kr', 'name' => 'Norwegian Krone'],
            ['code' => 'CAD', 'symbol' => 'CA$', 'name' => 'Canadian Dollar'],
            ['code' => 'AUD', 'symbol' => 'A$', 'name' => 'Australian Dollar'],
        ];
        $this->resetCreateForm();
    }

    public function resetCreateForm(): void
    {
        $site = SiteAccess::findOrFail($this->siteId);
        $this->form_number = Invoice::nextNumberForSite($site);
        $this->form_invoice_date = now()->toDateString();
        $this->form_payment_terms = 'net30';
        $this->applyPaymentTermsDueDate();
        $this->form_currency_code = 'EUR';
        $this->form_tax_rate = '0';
        $this->form_discount_percent = '0';
        $this->form_payment_details = "IBAN: XX00 0000 0000 0000 0000\nBIC/SWIFT: XXXXXX\nBank: Example Bank";
        $this->form_from_address = "platform\n".auth()->user()->name."\n".config('app.name');
        $bill = trim((string) $site->client_address);
        $this->form_bill_to = $bill !== '' ? $bill : $site->clientDisplayName();
        $termDays = $this->currentPaymentTermDays() ?? 30;
        $this->form_notes = "Payment due within {$termDays} days of invoice date. Late payments may incur a 1.5% monthly fee.";
        $this->form_lines = [
            ['description' => '', 'quantity' => '1', 'rate' => '0'],
        ];
    }

    public function updatedFormPaymentTerms(): void
    {
        $this->applyPaymentTermsDueDate();
        $termDays = $this->currentPaymentTermDays() ?? 30;
        $this->form_notes = "Payment due within {$termDays} days of invoice date. Late payments may incur a 1.5% monthly fee.";
    }

    public function updatedFormInvoiceDate(): void
    {
        $this->applyPaymentTermsDueDate();
    }

    protected function currentPaymentTermDays(): ?int
    {
        return $this->paymentTermDaysForValue($this->form_payment_terms);
    }

    protected function paymentTermDaysForValue(string $value): ?int
    {
        foreach ($this->paymentTermOptions as $opt) {
            if ($opt['value'] === $value) {
                return $opt['days'];
            }
        }

        return null;
    }

    protected function applyPaymentTermsDueDate(): void
    {
        $days = $this->currentPaymentTermDays();
        if ($days === null) {
            return;
        }
        try {
            $base = Carbon::parse($this->form_invoice_date);
            $this->form_due_date = $base->copy()->addDays($days)->toDateString();
        } catch (\Throwable) {
            $this->form_due_date = now()->addDays($days)->toDateString();
        }
    }

    public function startCreate(): void
    {
        $this->previewData = null;
        $this->resetCreateForm();
        $this->screen = 'create';
        $this->activeInvoiceId = null;
    }

    public function cancelCreate(): void
    {
        $this->previewData = null;
        $this->screen = 'index';
        $this->resetCreateForm();
    }

    public function startEdit(): void
    {
        $invoice = $this->resolveActiveInvoice();
        if (! $invoice) {
            return;
        }

        $this->previewData = null;
        $this->fillFormFromInvoice($invoice);
        $this->screen = 'edit';
    }

    public function cancelEdit(): void
    {
        $this->previewData = null;
        $this->screen = 'show';
    }

    public function previewDraft(): void
    {
        $lines = $this->billableFormLines();

        if ($lines->isEmpty()) {
            $this->addError('form_lines', 'Add at least one line item with a description and a rate greater than zero to preview.');

            return;
        }

        $this->validate([
            'form_number' => ['required', 'string', 'max:64'],
            'form_invoice_date' => ['required', 'date'],
            'form_due_date' => ['required', 'date'],
            'form_currency_code' => ['required', 'string', 'size:3'],
            'form_tax_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'form_discount_percent' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);

        $items = $lines->values()->map(fn (array $row) => [
            'description' => trim((string) $row['description']),
            'quantity' => $this->parseFormLineQuantity($row),
            'rate' => (float) ($row['rate'] ?? 0),
        ])->all();

        $sub = collect($items)->sum(fn (array $i) => $i['quantity'] * $i['rate']);
        $discPct = (float) $this->form_discount_percent;
        $disc = $sub * ($discPct / 100);
        $after = max(0, $sub - $disc);
        $taxPct = (float) $this->form_tax_rate;
        $tax = $after * ($taxPct / 100);
        $total = $after + $tax;

        $this->previewData = [
            'number' => $this->form_number,
            'invoice_date' => $this->form_invoice_date,
            'due_date' => $this->form_due_date,
            'status' => Invoice::STATUS_UNPAID,
            'currency_code' => strtoupper($this->form_currency_code),
            'tax_rate' => $this->form_tax_rate,
            'discount_percent' => $this->form_discount_percent,
            'notes' => $this->form_notes,
            'payment_details' => $this->form_payment_details,
            'from_address' => $this->form_from_address,
            'bill_to' => $this->form_bill_to,
            'items' => $items,
            'subtotal' => number_format($sub, 2, '.', ''),
            'discount_amount' => number_format($disc, 2, '.', ''),
            'tax_amount' => number_format($tax, 2, '.', ''),
            'total' => number_format($total, 2, '.', ''),
        ];

        $this->previewReturnScreen = $this->screen === 'edit' ? 'edit' : 'create';
        $this->screen = 'preview';
    }

    public function backFromPreview(): void
    {
        $this->previewData = null;
        $this->screen = $this->previewReturnScreen;
    }

    public function addLine(): void
    {
        $this->form_lines[] = ['description' => '', 'quantity' => '1', 'rate' => '0'];
    }

    public function removeLine(int $index): void
    {
        if (count($this->form_lines) <= 1) {
            return;
        }
        unset($this->form_lines[$index]);
        $this->form_lines = array_values($this->form_lines);
    }

    public function openInvoice(string $invoiceId): void
    {
        $this->activeInvoiceId = $invoiceId;
        $this->screen = 'show';
    }

    public function backToList(): void
    {
        $this->screen = 'index';
        $this->activeInvoiceId = null;
    }

    public function markPaid(): void
    {
        SiteAccess::findOrFail($this->siteId);

        $invoice = $this->resolveActiveInvoice();
        if (! $invoice) {
            return;
        }
        $invoice->update(['status' => Invoice::STATUS_PAID, 'paid_at' => now()]);
        $this->dispatch('invoice-updated');
    }

    public function duplicate(): void
    {
        $source = $this->resolveActiveInvoice();
        if (! $source) {
            return;
        }
        $site = SiteAccess::findOrFail($this->siteId);

        DB::transaction(function () use ($source, $site) {
            $new = $source->replicate([
                'id', 'created_at', 'updated_at',
            ]);
            $new->number = Invoice::nextNumberForSite($site);
            $invoiceDate = Carbon::now()->startOfDay();
            $new->invoice_date = $invoiceDate->toDateString();
            $termDays = $this->paymentTermDaysForValue((string) $source->payment_terms);
            if ($termDays !== null) {
                $new->due_date = $invoiceDate->copy()->addDays($termDays)->toDateString();
            } else {
                $span = max(0, (int) $source->invoice_date->copy()->startOfDay()->diffInDays($source->due_date->copy()->startOfDay(), false));
                $new->due_date = $invoiceDate->copy()->addDays($span)->toDateString();
            }
            $new->status = Invoice::STATUS_UNPAID;
            $new->save();

            foreach ($source->items as $i => $item) {
                InvoiceItem::create([
                    'invoice_id' => $new->id,
                    'description' => $item->description,
                    'quantity' => $item->quantity,
                    'rate' => $item->rate,
                    'sort_order' => $i,
                ]);
            }

            $this->activeInvoiceId = $new->id;
        });

        $this->dispatch('invoice-updated');
    }

    public function saveInvoice(): void
    {
        $site = SiteAccess::findOrFail($this->siteId);

        $this->validate($this->formRules($site));

        $lines = $this->billableFormLines();

        if ($lines->isEmpty()) {
            $this->addError('form_lines', 'Add at least one line item with a description and a rate greater than zero.');

            return;
        }

        $this->persistFormInvoice($site, $lines);

        $this->screen = 'index';
        $this->resetCreateForm();
        $this->dispatch('invoice-updated');
    }

    public function updateInvoice(): void
    {
        $site = SiteAccess::findOrFail($this->siteId);
        $invoice = $this->resolveActiveInvoice();
        if (! $invoice) {
            return;
        }

        $this->validate($this->formRules($site, $invoice));

        $lines = $this->billableFormLines();

        if ($lines->isEmpty()) {
            $this->addError('form_lines', 'Add at least one line item with a description and a rate greater than zero.');

            return;
        }

        $this->persistFormInvoice($site, $lines, $invoice);

        $this->previewData = null;
        $this->screen = 'show';
        $this->dispatch('invoice-updated');
    }

    /**
     * @return array<string, mixed>
     */
    protected function formRules(\App\Models\Site $site, ?Invoice $invoice = null): array
    {
        return [
            'form_number' => [
                'required',
                'string',
                'max:64',
                Rule::unique('invoices', 'number')
                    ->where('site_id', $site->id)
                    ->ignore($invoice?->id),
            ],
            'form_invoice_date' => ['required', 'date'],
            'form_due_date' => ['required', 'date'],
            'form_currency_code' => ['required', 'string', 'size:3'],
            'form_tax_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'form_discount_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'form_payment_terms' => ['required', 'string', 'max:32'],
            'form_notes' => ['nullable', 'string', 'max:20000'],
            'form_payment_details' => ['nullable', 'string', 'max:20000'],
            'form_from_address' => ['nullable', 'string', 'max:5000'],
            'form_bill_to' => ['nullable', 'string', 'max:5000'],
            'form_lines' => ['required', 'array', 'min:1'],
            'form_lines.*.description' => ['nullable', 'string', 'max:2000'],
            'form_lines.*.quantity' => ['nullable', 'numeric', 'min:0'],
            'form_lines.*.rate' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    /**
     * @param Collection<int, array{description: string, quantity: string, rate: string}> $lines
     */
    protected function persistFormInvoice(\App\Models\Site $site, Collection $lines, ?Invoice $invoice = null): Invoice
    {
        return DB::transaction(function () use ($site, $lines, $invoice) {
            $invoice ??= new Invoice([
                'site_id' => $site->id,
                'status' => Invoice::STATUS_UNPAID,
            ]);

            $invoice->fill([
                'number' => $this->form_number,
                'invoice_date' => $this->form_invoice_date,
                'due_date' => $this->form_due_date,
                'currency_code' => strtoupper($this->form_currency_code),
                'tax_rate' => $this->form_tax_rate,
                'discount_percent' => $this->form_discount_percent,
                'payment_terms' => $this->form_payment_terms,
                'notes' => $this->form_notes ?: null,
                'payment_details' => $this->form_payment_details ?: null,
                'from_address' => $this->form_from_address ?: null,
                'bill_to' => $this->form_bill_to ?: null,
            ])->save();

            $invoice->items()->delete();
            foreach ($lines->values() as $i => $row) {
                InvoiceItem::create([
                    'invoice_id' => $invoice->id,
                    'description' => trim((string) $row['description']),
                    'quantity' => $this->parseFormLineQuantity($row),
                    'rate' => (float) ($row['rate'] ?? 0),
                    'sort_order' => $i,
                ]);
            }

            $this->activeInvoiceId = $invoice->id;

            return $invoice;
        });
    }

    protected function fillFormFromInvoice(Invoice $invoice): void
    {
        $invoice->loadMissing('items');

        $this->form_number = (string) $invoice->number;
        $this->form_invoice_date = $invoice->invoice_date?->toDateString() ?? now()->toDateString();
        $this->form_due_date = $invoice->due_date?->toDateString() ?? now()->addDays(30)->toDateString();
        $this->form_payment_terms = (string) ($invoice->payment_terms ?: 'net30');
        $this->form_currency_code = (string) ($invoice->currency_code ?: 'EUR');
        $this->form_tax_rate = (string) ($invoice->tax_rate ?? '0');
        $this->form_discount_percent = (string) ($invoice->discount_percent ?? '0');
        $this->form_notes = (string) ($invoice->notes ?? '');
        $this->form_payment_details = (string) ($invoice->payment_details ?? '');
        $this->form_from_address = (string) ($invoice->from_address ?? '');
        $this->form_bill_to = (string) ($invoice->bill_to ?? '');
        $this->form_lines = $invoice->items->map(fn (InvoiceItem $item) => [
            'description' => (string) $item->description,
            'quantity' => (string) $item->quantity,
            'rate' => (string) $item->rate,
        ])->values()->all() ?: [
            ['description' => '', 'quantity' => '1', 'rate' => '0'],
        ];
    }

    protected function resolveActiveInvoice(): ?Invoice
    {
        if (! $this->activeInvoiceId) {
            return null;
        }

        return Invoice::query()
            ->where('site_id', $this->siteId)
            ->whereKey($this->activeInvoiceId)
            ->with('items')
            ->first();
    }

    /**
     * Quantity from the form: preserves 0 (do not use ?: which treats 0.0 as falsy).
     */
    private function parseFormLineQuantity(array $row): float
    {
        return (float) ($row['quantity'] ?? 0);
    }

    /**
     * Lines that count toward subtotal — same filter as preview and save (non-empty description, rate > 0).
     *
     * @return Collection<int, array{description: string, quantity: string, rate: string}>
     */
    private function billableFormLines(): Collection
    {
        return collect($this->form_lines)->filter(function (array $row) {
            $d = trim((string) ($row['description'] ?? ''));

            return $d !== '' && (float) ($row['rate'] ?? 0) > 0;
        });
    }

    public function createTotals(): array
    {
        $sub = 0.0;
        foreach ($this->billableFormLines() as $row) {
            $q = $this->parseFormLineQuantity($row);
            $r = (float) ($row['rate'] ?? 0);
            $sub += $q * $r;
        }
        $discPct = (float) $this->form_discount_percent;
        $disc = $sub * ($discPct / 100);
        $after = max(0, $sub - $disc);
        $taxPct = (float) $this->form_tax_rate;
        $tax = $after * ($taxPct / 100);
        $total = $after + $tax;

        return [
            'subtotal' => $sub,
            'discount' => $disc,
            'tax' => $tax,
            'total' => $total,
        ];
    }

    public function render()
    {
        $site = SiteAccess::findOrFail($this->siteId);

        $invoices = $site->invoices()
            ->with('items')
            ->get()
            ->sortByDesc(fn (Invoice $invoice) => $invoice->listSortKeyNewestFirst())
            ->values();

        $paidInvoices = $invoices->where('status', Invoice::STATUS_PAID)->values();
        $unpaidInvoices = $invoices->where('status', Invoice::STATUS_UNPAID)->values();

        $totalPaidAmount = $paidInvoices->sum(fn (Invoice $i) => (float) $i->total());
        $totalUnpaidAmount = $unpaidInvoices->sum(fn (Invoice $i) => (float) $i->total());
        $totalAllAmount = $totalPaidAmount + $totalUnpaidAmount;

        $activeInvoice = $this->screen === 'show'
            ? $this->resolveActiveInvoice()
            : null;

        return view('livewire.sites.invoice-manager', [
            'site' => $site,
            'invoices' => $invoices,
            'paidInvoices' => $paidInvoices,
            'unpaidInvoices' => $unpaidInvoices,
            'totalPaidAmount' => $totalPaidAmount,
            'totalUnpaidAmount' => $totalUnpaidAmount,
            'totalAllAmount' => $totalAllAmount,
            'activeInvoice' => $activeInvoice,
        ]);
    }
}
