<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string|null $page_id
 * @property Carbon $date
 * @property string $source
 * @property int $visitors
 * @property int $pageviews
 * @property float|null $bounce_rate
 * @property int|null $avg_session_sec
 * @property array|null $custom_events
 * @property Carbon|null $created_at
 * @property-read string|null $site_id
 * @property-read Page|null $page
 */
class AnalyticsSnapshot extends Model
{
    use HasUuids;

    /** GA4 organic search (SEO) channel — see AnalyticsAggregator::syncGoogleAnalytics */
    public const SOURCE_GOOGLE_ORGANIC = 'google_analytics_organic';

    public const SOURCE_PIXELKRAFT_TRACKER = 'pixelkraft_tracker';

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
            'date' => 'date',
            'visitors' => 'integer',
            'pageviews' => 'integer',
            'bounce_rate' => 'float',
            'avg_session_sec' => 'integer',
            'custom_events' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Page, $this> */
    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    public function isFromGoogle(): bool
    {
        return str_starts_with((string) $this->source, 'google_analytics');
    }

    public function isFromCloudflare(): bool
    {
        return $this->source === 'cloudflare';
    }
}
