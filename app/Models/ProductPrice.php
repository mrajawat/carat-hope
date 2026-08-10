<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductPrice extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'region_id',
        'price',
        'compare_at_price',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'compare_at_price' => 'decimal:2',
    ];

    /**
     * Get the product associated with this price.
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get the region associated with this price.
     */
    public function region()
    {
        return $this->belongsTo(Region::class);
    }
}
