<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $site_id
 * @property string $label
 * @property string $amount
 * @property string|null $currency
 * @property \Carbon\Carbon|null $expense_date
 * @property array|null $meta
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 * @property-read \App\Models\Site|null $site
 */
class Expense extends Model
{
    use HasUuids;

    protected $fillable = [
        'site_id',
        'label',
        'amount',
        'currency',
        'expense_date',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'expense_date' => 'date',
            'meta' => 'array',
        ];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }
}
