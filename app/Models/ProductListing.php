<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductListing extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'site_id',
        'name',
        'description',
        'price',
        'currency',
        'images',
        'attributes',
        'output_path',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'images' => 'array',
            'attributes' => 'array',
        ];
    }

    public function site()
    {
        return $this->belongsTo(Site::class);
    }

    public function formattedPrice(): string
    {
        return $this->currency.' '.number_format((float) $this->price, 2);
    }
}
