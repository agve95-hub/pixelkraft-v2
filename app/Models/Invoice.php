<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * @property string $id
 * @property string $site_id
 * @property string $number
 * @property Carbon|null $invoice_date
 * @property Carbon|null $due_date
 * @property string $status
 * @property string $currency_code
 * @property string|null $tax_rate
 * @property string|null $discount_percent
 * @property string|null $payment_terms
 * @property string|null $notes
 * @property string|null $payment_details
 * @property string|null $from_address
 * @property string|null $bill_to
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Site|null $site
 */
class Invoice extends Model
{
    use HasUuids;

    public const STATUS_UNPAID = 'unpaid';

    public const STATUS_PAID = 'paid';

    protected $fillable = [
        'site_id',
        'number',
        'invoice_date',
        'due_date',
        'status',
        'currency_code',
        'tax_rate',
        'discount_percent',
        'payment_terms',
        'notes',
        'payment_details',
        'from_address',
        'bill_to',
    ];

    protected function casts(): array
    {
        return [
            'invoice_date' => 'date',
            'due_date' => 'date',
            'tax_rate' => 'decimal:4',
            'discount_percent' => 'decimal:4',
        ];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class)->orderBy('sort_order');
    }

    public function subtotal(): string
    {
        $sum = $this->items->sum(fn (InvoiceItem $item) => (float) $item->quantity * (float) $item->rate);

        return number_format($sum, 2, '.', '');
    }

    public function discountAmount(): string
    {
        $sub = (float) $this->subtotal();
        $pct = (float) $this->discount_percent;
        $amount = $sub * ($pct / 100);

        return number_format($amount, 2, '.', '');
    }

    public function amountAfterDiscount(): string
    {
        $sub = (float) $this->subtotal();
        $disc = (float) $this->discountAmount();

        return number_format(max(0, $sub - $disc), 2, '.', '');
    }

    public function taxAmount(): string
    {
        $base = (float) $this->amountAfterDiscount();
        $pct = (float) $this->tax_rate;
        $tax = $base * ($pct / 100);

        return number_format($tax, 2, '.', '');
    }

    public function total(): string
    {
        $base = (float) $this->amountAfterDiscount();
        $tax = (float) $this->taxAmount();

        return number_format($base + $tax, 2, '.', '');
    }

    public function isOverdue(): bool
    {
        if ($this->status !== self::STATUS_UNPAID) {
            return false;
        }

        return $this->due_date->toDateString() < now()->toDateString();
    }

    public function displayStatus(): string
    {
        if ($this->status === self::STATUS_PAID) {
            return 'Paid';
        }

        return $this->isOverdue() ? 'Overdue' : 'Unpaid';
    }

    public static function currencySymbol(string $code): string
    {
        return match (strtoupper($code)) {
            'EUR' => '€',
            'USD' => '$',
            'GBP' => '£',
            'CHF' => 'CHF ',
            'SEK', 'NOK' => 'kr ',
            'CAD' => 'CA$',
            'AUD' => 'A$',
            default => strtoupper($code).' ',
        };
    }

    public function displayCurrencySymbol(): string
    {
        return self::currencySymbol((string) $this->currency_code);
    }

    /**
     * Integer after the last hyphen in `number` (e.g. INV-2026-010 → 10).
     * String sort on `number` is wrong for same-length tails (010 vs 009).
     */
    public function numberTrailingSequence(): int
    {
        if (preg_match('/-(\d+)\s*$/', (string) $this->number, $matches)) {
            return (int) $matches[1];
        }

        return 0;
    }

    /**
     * Key for descending list order: newer invoice_date first, then higher sequence for the same date.
     */
    public function listSortKeyNewestFirst(): string
    {
        return $this->invoice_date->format('Y-m-d')
            .'_'
            .str_pad((string) $this->numberTrailingSequence(), 12, '0', STR_PAD_LEFT);
    }

    public static function nextNumberForSite(Site $site): string
    {
        $year = (string) now()->year;
        $prefix = 'INV-'.$year.'-';

        $maxSeq = (int) $site->invoices()
            ->where('number', 'like', $prefix.'%')
            ->get()
            ->map(function (Invoice $inv) use ($prefix) {
                $tail = Str::after($inv->number, $prefix);

                return (int) $tail;
            })
            ->max();

        return $prefix.str_pad((string) ($maxSeq + 1), 3, '0', STR_PAD_LEFT);
    }
}
