<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
