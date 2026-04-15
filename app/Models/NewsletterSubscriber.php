<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $site_id
 * @property string $email
 * @property string|null $name
 * @property array|null $segments
 * @property string $status
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 * @property-read \App\Models\Site|null $site
 */
class NewsletterSubscriber extends Model
{
    use HasUuids;

    protected $fillable = [
        'site_id',
        'email',
        'name',
        'segments',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'segments' => 'array',
        ];
    }

    public function site()
    {
        return $this->belongsTo(Site::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
