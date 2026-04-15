<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $site_id
 * @property string|null $edit_session_id
 * @property string $operation
 * @property string $status
 * @property string|null $branch
 * @property string|null $working_branch
 * @property string|null $commit_sha
 * @property array|null $files
 * @property string|null $output_log
 * @property string|null $error_output
 * @property array|null $metadata
 * @property \Carbon\Carbon|null $started_at
 * @property \Carbon\Carbon|null $completed_at
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 * @property-read \App\Models\Site|null $site
 */
class GitOperation extends Model
{
    use HasUuids;

    protected $fillable = [
        'site_id',
        'edit_session_id',
        'operation',
        'status',
        'branch',
        'working_branch',
        'commit_sha',
        'files',
        'output_log',
        'error_output',
        'metadata',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'files' => 'array',
            'metadata' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function site()
    {
        return $this->belongsTo(Site::class);
    }

    public function editSession()
    {
        return $this->belongsTo(EditSession::class);
    }
}
