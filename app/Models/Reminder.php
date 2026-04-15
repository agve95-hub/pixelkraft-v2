<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $site_id
 * @property string $title
 * @property \Carbon\Carbon|null $due_date
 * @property bool $is_done
 * @property string|null $notes
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 * @property-read \App\Models\Site|null $site
 */
class Reminder extends Model
{
    use HasUuids;

    protected $fillable = [
        'site_id',
        'title',
        'due_date',
        'is_done',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'is_done' => 'boolean',
        ];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }
}
