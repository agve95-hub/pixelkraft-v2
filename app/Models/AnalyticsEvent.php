<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class AnalyticsEvent extends Model
{
    use HasUuids;

    protected $fillable = [
        'site_id',
        'page_id',
        'event_name',
        'path',
        'visitor_id',
        'session_id',
        'referrer',
        'ip_hash',
        'user_agent',
        'payload',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    public function site()
    {
        return $this->belongsTo(Site::class);
    }

    public function page()
    {
        return $this->belongsTo(Page::class);
    }
}
