<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $region_id
 * @property string|null $user_id
 * @property string|null $content_before
 * @property string|null $content_after
 * @property string|null $commit_sha
 * @property \Carbon\Carbon|null $created_at
 * @property-read \App\Models\EditableRegion|null $region
 * @property-read \App\Models\User|null $user
 */
class ContentRevision extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'region_id',
        'user_id',
        'content_before',
        'content_after',
        'commit_sha',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function region()
    {
        return $this->belongsTo(EditableRegion::class, 'region_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
