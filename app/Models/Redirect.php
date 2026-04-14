<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Redirect extends Model
{
    use HasUuids;

    protected $fillable = [
        'site_id',
        'from_path',
        'to_path',
        'status_code',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'status_code' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function site()
    {
        return $this->belongsTo(Site::class);
    }

    public function isPermanent(): bool
    {
        return $this->status_code === 301;
    }
}
