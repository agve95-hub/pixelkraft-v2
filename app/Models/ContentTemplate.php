<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string|null $site_id
 * @property string $name
 * @property string|null $type
 * @property string|null $html_template
 * @property array|null $fields_schema
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Site|null $site
 */
class ContentTemplate extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'site_id',
        'name',
        'type',
        'html_template',
        'fields_schema',
    ];

    protected function casts(): array
    {
        return [
            'fields_schema' => 'array',
        ];
    }

    /** @return BelongsTo<Site, $this> */
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    /** @return HasMany<BlogPost, $this> */
    public function blogPosts(): HasMany
    {
        return $this->hasMany(BlogPost::class, 'template_id');
    }

    /**
     * Render the template by replacing {{placeholder}} tokens with values from $data.
     *
     * @param  array<string, string>  $data
     */
    public function render(array $data): string
    {
        $html = (string) $this->html_template;

        foreach ($data as $key => $value) {
            $html = str_replace('{{'.$key.'}}', (string) $value, $html);
        }

        return $html;
    }
}
