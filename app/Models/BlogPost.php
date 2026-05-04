<?php

namespace App\Models;

use App\Enums\BlogPostStatus;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $site_id
 * @property string|null $template_id
 * @property string $title
 * @property string $slug
 * @property string|null $body
 * @property string|null $excerpt
 * @property string|null $featured_image
 * @property array|null $tags
 * @property string|null $seo_title
 * @property string|null $seo_description
 * @property string|null $og_image
 * @property array|null $schema_json
 * @property string|null $output_path
 * @property BlogPostStatus $status
 * @property Carbon|null $published_at
 * @property Carbon|null $scheduled_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Site|null $site
 */
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
            'status' => BlogPostStatus::class,
        ];
    }

    /** @return BelongsTo<Site, $this> */
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    /** @return BelongsTo<ContentTemplate, $this> */
    public function template(): BelongsTo
    {
        return $this->belongsTo(ContentTemplate::class, 'template_id');
    }

    public function isPublished(): bool
    {
        return $this->status === BlogPostStatus::Published;
    }

    /**
     * Transition status with guard — throws if the transition is not allowed.
     */
    public function transitionStatus(BlogPostStatus $next): void
    {
        $current = $this->status ?? BlogPostStatus::Draft;

        if (! $current->canTransitionTo($next)) {
            throw new \LogicException(
                "Cannot transition blog post status from [{$current->value}] to [{$next->value}]."
            );
        }

        $this->update(['status' => $next]);
    }

    public function isScheduled(): bool
    {
        return $this->status === BlogPostStatus::Scheduled && $this->scheduled_at?->isFuture();
    }

    public function shouldPublish(): bool
    {
        return $this->status === BlogPostStatus::Scheduled
            && $this->scheduled_at
            && $this->scheduled_at->isPast();
    }
}
