<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EditableRegion extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'page_id',
        'selector',
        'render_selector',
        'marker_id',
        'region_type',
        'is_static',
        'detection_method',
        'confidence_score',
        'current_content',
        'source_location',
        'dom_fingerprint',
        'source_anchor',
        'last_verified_at',
    ];

    protected function casts(): array
    {
        return [
            'is_static'        => 'boolean',
            'confidence_score' => 'float',
            'source_location'  => 'array',
            'dom_fingerprint'  => 'array',
            'source_anchor'    => 'array',
            'last_verified_at' => 'datetime',
        ];
    }

    // ── Relationships ───────────────────────────

    public function page()
    {
        return $this->belongsTo(Page::class);
    }

    public function revisions()
    {
        return $this->hasMany(ContentRevision::class, 'region_id');
    }

    // ── Helpers ──────────────────────────────────

    public function isDynamic(): bool
    {
        return ! $this->is_static;
    }

    public function isConfirmed(): bool
    {
        return $this->detection_method === 'marker' || ! empty($this->marker_id);
    }

    public function hasHighConfidence(): bool
    {
        return $this->confidence_score >= 0.7;
    }

    public function hasVerifiedAnchor(): bool
    {
        return ! empty($this->marker_id)
            || ! empty(data_get($this->source_anchor, 'context_hash'));
    }
}
