<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $site_id
 * @property string $message
 * @property string|null $style
 * @property string|null $cta_text
 * @property string|null $cta_url
 * @property string|null $placement
 * @property bool $is_dismissible
 * @property Carbon|null $starts_at
 * @property Carbon|null $ends_at
 * @property int $priority
 * @property string|null $locale
 * @property bool $is_enabled
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Site|null $site
 */
class Announcement extends Model
{
    use HasUuids;

    protected $fillable = [
        'site_id',
        'message',
        'style',
        'cta_text',
        'cta_url',
        'placement',
        'is_dismissible',
        'starts_at',
        'ends_at',
        'priority',
        'locale',
        'is_enabled',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_dismissible' => 'boolean',
            'is_enabled' => 'boolean',
            'priority' => 'integer',
        ];
    }

    /** @return BelongsTo<Site, $this> */
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    /**
     * Scope to announcements that are currently active (enabled and within schedule).
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query
            ->where('is_enabled', true)
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>=', now());
    }

    public function isActive(): bool
    {
        return $this->is_enabled
            && $this->starts_at->lte(now())
            && $this->ends_at->gte(now());
    }
}
