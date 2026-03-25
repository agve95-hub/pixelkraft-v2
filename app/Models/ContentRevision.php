<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

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
