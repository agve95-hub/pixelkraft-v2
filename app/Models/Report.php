<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $site_id
 * @property string $title
 * @property Carbon|null $report_date
 * @property string|null $summary
 * @property array|null $meta
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Site|null $site
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

    /** @return BelongsTo<Site, $this> */
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }
}
