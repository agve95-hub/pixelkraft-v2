<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class TrackingInstallation extends Model
{
    use HasUuids;

    protected $fillable = [
        'site_id',
        'provider',
        'measurement_id',
        'container_id',
        'script_route',
        'collector_path',
        'consent_mode',
        'is_active',
        'settings',
    ];

    protected function casts(): array
    {
        return [
            'consent_mode' => 'boolean',
            'is_active' => 'boolean',
            'settings' => 'array',
        ];
    }

    public function site()
    {
        return $this->belongsTo(Site::class);
    }
}
