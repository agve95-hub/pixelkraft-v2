<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $site_id
 * @property string $provider
 * @property string|null $measurement_id
 * @property string|null $container_id
 * @property string|null $script_route
 * @property string|null $collector_path
 * @property bool $consent_mode
 * @property bool $is_active
 * @property array|null $settings
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Site|null $site
 */
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
