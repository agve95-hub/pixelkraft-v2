<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class NewsletterSubscriber extends Model
{
    use HasUuids;

    protected $fillable = [
        'site_id',
        'email',
        'name',
        'segments',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'segments' => 'array',
        ];
    }

    public function site()
    {
        return $this->belongsTo(Site::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
