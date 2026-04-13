<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class EditSession extends Model
{
    use HasUuids;

    protected $fillable = [
        'site_id',
        'page_id',
        'started_by',
        'base_commit_sha',
        'working_branch',
        'status',
        'metadata',
        'started_at',
        'ended_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    public function site()
    {
        return $this->belongsTo(Site::class);
    }

    public function page()
    {
        return $this->belongsTo(Page::class);
    }

    public function startedBy()
    {
        return $this->belongsTo(User::class, 'started_by');
    }

    public function gitOperations()
    {
        return $this->hasMany(GitOperation::class);
    }

    public function close(string $status = 'closed', array $metadata = []): void
    {
        $this->update([
            'status' => $status,
            'metadata' => array_merge($this->metadata ?? [], $metadata),
            'ended_at' => now(),
        ]);
    }
}
