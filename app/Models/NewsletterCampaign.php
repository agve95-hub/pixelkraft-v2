<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $site_id
 * @property string $subject
 * @property string|null $body_html
 * @property string|null $template_id
 * @property array|null $segment_filter
 * @property string $status
 * @property Carbon|null $scheduled_at
 * @property Carbon|null $sent_at
 * @property array|null $stats
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Site|null $site
 * @property-read ContentTemplate|null $template
 */
class NewsletterCampaign extends Model
{
    use HasUuids;

    protected $fillable = [
        'site_id',
        'subject',
        'body_html',
        'template_id',
        'segment_filter',
        'status',
        'scheduled_at',
        'sent_at',
        'stats',
    ];

    protected function casts(): array
    {
        return [
            'segment_filter' => 'array',
            'stats' => 'array',
            'scheduled_at' => 'datetime',
            'sent_at' => 'datetime',
        ];
    }

    public function site()
    {
        return $this->belongsTo(Site::class);
    }

    public function template()
    {
        return $this->belongsTo(ContentTemplate::class, 'template_id');
    }

    public function isSent(): bool
    {
        return $this->status === 'sent';
    }

    public function shouldSend(): bool
    {
        return $this->status === 'scheduled'
            && $this->scheduled_at
            && $this->scheduled_at->isPast();
    }

    public function recipientCount(): int
    {
        return $this->stats['sent'] ?? 0;
    }
}
