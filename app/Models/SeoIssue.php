<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $site_id
 * @property string|null $page_id
 * @property string $severity
 * @property string $code
 * @property string|null $message
 * @property array|null $meta
 * @property Carbon|null $resolved_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Site|null $site
 * @property-read Page|null $page
 */
class SeoIssue extends Model
{
    use HasUuids;

    protected $fillable = [
        'site_id',
        'page_id',
        'severity',
        'code',
        'message',
        'meta',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'resolved_at' => 'datetime',
        ];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    public function scopeOpen($query)
    {
        return $query->whereNull('resolved_at');
    }
}
