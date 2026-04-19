<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $site_id
 * @property string|null $page_id
 * @property string $started_by
 * @property string|null $base_commit_sha
 * @property string|null $working_branch
 * @property string $status
 * @property array|null $metadata
 * @property Carbon|null $started_at
 * @property Carbon|null $ended_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Site|null $site
 * @property-read Page|null $page
 * @property-read User|null $startedBy
 */
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

    /** @return BelongsTo<Site, $this> */
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    /** @return BelongsTo<Page, $this> */
    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    /** @return BelongsTo<User, $this> */
    public function startedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'started_by');
    }

    /** @return HasMany<GitOperation, $this> */
    public function gitOperations(): HasMany
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
