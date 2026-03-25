<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class DeployLog extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'site_id',
        'status',
        'commit_sha',
        'commit_message',
        'output_log',
        'duration_ms',
        'triggered_by',
        'snapshot_tag',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'duration_ms' => 'integer',
            'created_at'  => 'datetime',
        ];
    }

    public function site()
    {
        return $this->belongsTo(Site::class);
    }

    public function isSuccess(): bool
    {
        return $this->status === 'success';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function durationFormatted(): string
    {
        if (! $this->duration_ms) {
            return '—';
        }

        $seconds = $this->duration_ms / 1000;

        return $seconds >= 60
            ? round($seconds / 60, 1) . 'm'
            : round($seconds, 1) . 's';
    }

    public function appendLog(string $line): void
    {
        $this->output_log = ($this->output_log ?? '') . $line . "\n";
        $this->save();
    }
}
