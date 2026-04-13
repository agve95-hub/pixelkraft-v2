<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

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
