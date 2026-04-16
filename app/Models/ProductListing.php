<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $site_id
 * @property string $name
 * @property string|null $description
 * @property string $price
 * @property string|null $currency
 * @property array|null $images
 * @property array|null $attributes
 * @property string|null $output_path
 * @property string $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Site|null $site
 */
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
