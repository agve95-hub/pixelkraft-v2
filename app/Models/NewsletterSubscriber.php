<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $site_id
 * @property string $email
 * @property string|null $name
 * @property array|null $segments
 * @property string $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Site|null $site
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

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
