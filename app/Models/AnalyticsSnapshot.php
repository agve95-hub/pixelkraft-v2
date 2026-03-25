<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class AnalyticsSnapshot extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'page_id',
        'date',
        'source',
        'visitors',
        'pageviews',
        'bounce_rate',
        'avg_session_sec',
        'custom_events',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'date'            => 'date',
            'visitors'        => 'integer',
            'pageviews'       => 'integer',
            'bounce_rate'     => 'float',
            'avg_session_sec' => 'integer',
            'custom_events'   => 'array',
            'created_at'      => 'datetime',
        ];
    }

    public function page()
    {
        return $this->belongsTo(Page::class);
    }

    public function isFromGoogle(): bool
    {
        return $this->source === 'google_analytics';
    }

    public function isFromCloudflare(): bool
    {
        return $this->source === 'cloudflare';
    }
}
