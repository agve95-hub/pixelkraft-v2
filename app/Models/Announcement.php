<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
