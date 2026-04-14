<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BlogPost extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'site_id',
        'template_id',
        'title',
        'slug',
        'body',
        'excerpt',
        'featured_image',
        'tags',
        'seo_title',
        'seo_description',
        'og_image',
        'schema_json',
        'output_path',
        'status',
        'published_at',
        'scheduled_at',
    ];

    protected function casts(): array
    {
        return [
            'tags' => 'array',
            'schema_json' => 'array',
            'published_at' => 'datetime',
            'scheduled_at' => 'datetime',
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

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }

    public function isScheduled(): bool
    {
        return $this->status === 'scheduled' && $this->scheduled_at?->isFuture();
    }

    public function shouldPublish(): bool
    {
        return $this->status === 'scheduled'
            && $this->scheduled_at
            && $this->scheduled_at->isPast();
    }
}
