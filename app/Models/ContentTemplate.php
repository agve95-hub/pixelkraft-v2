<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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

    public function site()
    {
        return $this->belongsTo(Site::class);
    }

    public function blogPosts()
    {
        return $this->hasMany(BlogPost::class, 'template_id');
    }

    public function isGlobal(): bool
    {
        return is_null($this->site_id);
    }

    /**
     * Render template by replacing {{placeholders}} with data.
     */
    public function render(array $data): string
    {
        $html = $this->html_template;

        foreach ($data as $key => $value) {
            $html = str_replace('{{'.$key.'}}', (string) $value, $html);
        }

        return $html;
    }
}
