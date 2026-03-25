<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

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
            'stats'          => 'array',
            'scheduled_at'   => 'datetime',
            'sent_at'        => 'datetime',
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
