<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

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
