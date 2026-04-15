<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $site_id
 * @property string|null $page_id
 * @property string $event_name
 * @property string|null $path
 * @property string|null $visitor_id
 * @property string|null $session_id
 * @property string|null $referrer
 * @property string|null $ip_hash
 * @property string|null $user_agent
 * @property array|null $payload
 * @property \Carbon\Carbon|null $occurred_at
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 * @property-read \App\Models\Site|null $site
 * @property-read \App\Models\Page|null $page
 */
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
