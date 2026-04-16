<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $provider
 * @property string|null $delivery_id
 * @property string|null $event
 * @property string|null $repository
 * @property string|null $site_id
 * @property string $status
 * @property array|null $headers
 * @property array|null $payload
 * @property Carbon|null $received_at
 * @property Carbon|null $processed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Site|null $site
 */
class WebhookDelivery extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'provider',
        'delivery_id',
        'event',
        'repository',
        'site_id',
        'status',
        'headers',
        'payload',
        'received_at',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'received_at' => 'datetime',
            'processed_at' => 'datetime',
            'headers' => 'array',
            'payload' => 'array',
        ];
    }

    public function site()
    {
        return $this->belongsTo(Site::class);
    }
}
