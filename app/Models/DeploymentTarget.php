<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

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

    public function site()
    {
        return $this->belongsTo(Site::class);
    }

    public function releases()
    {
        return $this->hasMany(DeploymentRelease::class);
    }
}
