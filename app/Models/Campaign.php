<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
