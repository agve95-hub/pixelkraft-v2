<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class UptimeCheck extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'site_id',
        'status_code',
        'response_time_ms',
        'is_up',
        'is_degraded',
        'checked_at',
    ];

    protected function casts(): array
    {
        return [
            'status_code'      => 'integer',
            'response_time_ms' => 'integer',
            'is_up'            => 'boolean',
            'is_degraded'      => 'boolean',
            'checked_at'       => 'datetime',
        ];
    }

    public function site()
    {
        return $this->belongsTo(Site::class);
    }

    public function responseTimeFormatted(): string
    {
        if (! $this->response_time_ms) {
            return '—';
        }

        return $this->response_time_ms >= 1000
            ? round($this->response_time_ms / 1000, 2) . 's'
            : $this->response_time_ms . 'ms';
    }
}
