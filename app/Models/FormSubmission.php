<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $site_id
 * @property string $form_name
 * @property array|null $data
 * @property string|null $ip_address
 * @property bool $is_read
 * @property bool $is_spam
 * @property Carbon|null $created_at
 * @property-read Site|null $site
 */
class FormSubmission extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'site_id',
        'form_name',
        'data',
        'ip_address',
        'is_read',
        'is_spam',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'is_read' => 'boolean',
            'is_spam' => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    public function site()
    {
        return $this->belongsTo(Site::class);
    }
}
