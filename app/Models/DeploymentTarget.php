<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $site_id
 * @property string $environment
 * @property string|null $host
 * @property string|null $deploy_path
 * @property string|null $runtime_type
 * @property string|null $health_check_url
 * @property string|null $release_strategy
 * @property bool $is_active
 * @property array|null $config
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Site|null $site
 */
class DeploymentTarget extends Model
{
    use HasUuids;

    protected $fillable = [
        'site_id',
        'environment',
        'host',
        'deploy_path',
        'runtime_type',
        'health_check_url',
        'release_strategy',
        'is_active',
        'config',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'config' => 'array',
        ];
    }

    /** @return BelongsTo<Site, $this> */
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    /** @return HasMany<DeploymentRelease, $this> */
    public function releases(): HasMany
    {
        return $this->hasMany(DeploymentRelease::class);
    }
}
