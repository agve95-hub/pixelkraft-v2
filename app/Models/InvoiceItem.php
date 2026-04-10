<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceItem extends Model
{
    use HasUuids;

    protected $fillable = [
        'invoice_id',
        'description',
        'quantity',
        'rate',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'rate'     => 'decimal:4',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function lineTotal(): string
    {
        $t = (float) $this->quantity * (float) $this->rate;

        return number_format($t, 2, '.', '');
    }
}
