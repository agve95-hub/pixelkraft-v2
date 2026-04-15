<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $site_id
 * @property string $title
 * @property \Carbon\Carbon|null $report_date
 * @property string|null $summary
 * @property array|null $meta
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 * @property-read \App\Models\Site|null $site
 */
class Report extends Model
{
    use HasUuids;

    protected $fillable = [
        'site_id',
        'title',
        'report_date',
        'summary',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'report_date' => 'date',
            'meta' => 'array',
        ];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }
}
