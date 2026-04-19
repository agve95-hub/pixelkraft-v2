<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $site_id
 * @property string|null $file_path
 * @property string|null $url_path
 * @property string|null $title
 * @property string|null $meta_description
 * @property string|null $meta_keywords
 * @property string|null $og_title
 * @property string|null $og_description
 * @property string|null $og_image
 * @property string|null $canonical_url
 * @property array|null $schema_json
 * @property int|null $seo_score
 * @property array|null $lighthouse_score
 * @property string|null $screenshot_url
 * @property string|null $content_hash
 * @property bool $is_published
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Site|null $site
 * @property-read \Illuminate\Database\Eloquent\Collection<int, EditableRegion> $editableRegions
 */
class Page extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'site_id',
        'file_path',
        'url_path',
        'title',
        'meta_description',
        'meta_keywords',
        'og_title',
        'og_description',
        'og_image',
        'canonical_url',
        'schema_json',
        'seo_score',
        'lighthouse_score',
        'screenshot_url',
        'content_hash',
        'is_published',
    ];

    protected function casts(): array
    {
        return [
            'schema_json' => 'array',
            'lighthouse_score' => 'array',
            'seo_score' => 'integer',
            'is_published' => 'boolean',
        ];
    }

    // ── Relationships ───────────────────────────

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    /** @return HasMany<EditableRegion, $this> */
    public function editableRegions(): HasMany
    {
        return $this->hasMany(EditableRegion::class);
    }

    /** @return HasMany<EditableRegion, $this> */
    public function dynamicRegions(): HasMany
    {
        return $this->hasMany(EditableRegion::class)->where('is_static', false);
    }

    public function analyticsSnapshots()
    {
        return $this->hasMany(AnalyticsSnapshot::class);
    }

    public function analyticsEvents()
    {
        return $this->hasMany(AnalyticsEvent::class);
    }

    public function editSessions()
    {
        return $this->hasMany(EditSession::class);
    }

    public function seoIssues()
    {
        return $this->hasMany(SeoIssue::class);
    }

    protected function url(): Attribute
    {
        return Attribute::get(fn () => $this->url_path ?: '/');
    }

    protected function status(): Attribute
    {
        return Attribute::get(fn () => $this->is_published ? 'Published' : 'Draft');
    }

    // ── Helpers ──────────────────────────────────

    public function totalVisitors(int $days = 30): int
    {
        return $this->analyticsSnapshots()
            ->where('date', '>=', now()->subDays($days))
            ->sum('visitors');
    }
}
