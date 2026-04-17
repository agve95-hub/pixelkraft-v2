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
 * @property string $name
 * @property string|null $headline
 * @property string|null $body
 * @property string|null $cta_text
 * @property string|null $cta_url
 * @property string|null $trigger
 * @property int|null $trigger_delay_ms
 * @property array|null $target_pages
 * @property array|null $audience_conditions
 * @property Carbon|null $starts_at
 * @property Carbon|null $ends_at
 * @property int $priority
 * @property bool $is_dismissible
 * @property array|null $dismissal_rules
 * @property string|null $locale
 * @property bool $is_enabled
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Site|null $site
 */
class Campaign extends Model
{
    use HasUuids;

    protected $fillable = [
        'site_id',
        'name',
        'headline',
        'body',
        'cta_text',
        'cta_url',
        'trigger',
        'trigger_delay_ms',
        'target_pages',
        'audience_conditions',
        'starts_at',
        'ends_at',
        'priority',
        'is_dismissible',
        'dismissal_rules',
        'locale',
        'is_enabled',
    ];

    protected function casts(): array
    {
        return [
            'target_pages' => 'array',
            'audience_conditions' => 'array',
            'dismissal_rules' => 'array',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_dismissible' => 'boolean',
            'is_enabled' => 'boolean',
            'priority' => 'integer',
            'trigger_delay_ms' => 'integer',
        ];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    /**
     * Scope to campaigns that are currently active (enabled and within schedule).
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query
            ->where('is_enabled', true)
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', now()));
    }

    public function isActive(): bool
    {
        if (! $this->is_enabled) {
            return false;
        }
        if ($this->starts_at !== null && $this->starts_at->gt(now())) {
            return false;
        }
        if ($this->ends_at !== null && $this->ends_at->lt(now())) {
            return false;
        }

        return true;
    }
}
