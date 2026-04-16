<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $site_id
 * @property string|null $deployment_target_id
 * @property string|null $deploy_log_id
 * @property string|null $rollback_of_release_id
 * @property string|null $source_commit_sha
 * @property string|null $source_branch
 * @property string|null $artifact_path
 * @property string|null $tracking_version
 * @property string $status
 * @property bool $is_current
 * @property array|null $meta
 * @property Carbon|null $activated_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Site|null $site
 */
class DeploymentRelease extends Model
{
    use HasUuids;

    protected $fillable = [
        'site_id',
        'deployment_target_id',
        'deploy_log_id',
        'rollback_of_release_id',
        'source_commit_sha',
        'source_branch',
        'artifact_path',
        'tracking_version',
        'status',
        'is_current',
        'meta',
        'activated_at',
    ];

    protected function casts(): array
    {
        return [
            'is_current' => 'boolean',
            'meta' => 'array',
            'activated_at' => 'datetime',
        ];
    }

    public function site()
    {
        return $this->belongsTo(Site::class);
    }

    public function deploymentTarget()
    {
        return $this->belongsTo(DeploymentTarget::class);
    }

    public function deployLog()
    {
        return $this->belongsTo(DeployLog::class);
    }

    public function rollbackOfRelease()
    {
        return $this->belongsTo(self::class, 'rollback_of_release_id');
    }
}
