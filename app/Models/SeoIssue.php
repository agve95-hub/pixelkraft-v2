<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
