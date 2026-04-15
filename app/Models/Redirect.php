<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $site_id
 * @property string $from_path
 * @property string $to_path
 * @property int $status_code
 * @property bool $is_active
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 * @property-read \App\Models\Site|null $site
 */
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
