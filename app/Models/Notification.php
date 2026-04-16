<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string|null $site_id
 * @property string $type
 * @property string $title
 * @property string|null $body
 * @property bool $is_read
 * @property array|null $data
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Site|null $site
 */
class Notification extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $table = 'notifications';

    protected $fillable = [
        'type',
        'title',
        'body',
        'site_id',
        'is_read',
        'data',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'is_read' => 'boolean',
            'data' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function site()
    {
        return $this->belongsTo(Site::class);
    }

    public function markAsRead(): void
    {
        $this->update(['is_read' => true]);
    }

    // ── Scopes ──────────────────────────────────

    public function scopeUnread($query)
    {
        return $query->where('is_read', false);
    }

    public function scopeForSite($query, string $siteId)
    {
        return $query->where('site_id', $siteId);
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    // ── Factory Methods ─────────────────────────

    public static function createAlert(
        string $type,
        string $title,
        ?string $body = null,
        ?string $siteId = null,
        ?array $data = null,
    ): static {
        return static::create([
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'site_id' => $siteId,
            'data' => $data,
            'created_at' => now(),
        ]);
    }
}
