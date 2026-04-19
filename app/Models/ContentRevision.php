<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $region_id
 * @property string|null $user_id
 * @property string|null $content_before
 * @property string|null $content_after
 * @property string|null $commit_sha
 * @property Carbon|null $created_at
 * @property-read EditableRegion|null $region
 * @property-read User|null $user
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

    /** @return BelongsTo<EditableRegion, $this> */
    public function region(): BelongsTo
    {
        return $this->belongsTo(EditableRegion::class, 'region_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
