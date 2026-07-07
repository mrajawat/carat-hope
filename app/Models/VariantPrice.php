<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VariantPrice extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_variant_id',
        'region_id',
        'price',
        'compare_at_price',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'compare_at_price' => 'decimal:2',
    ];

    /**
     * Get the variant associated with this price.
     */
    public function productVariant()
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    /**
     * Get the region associated with this price.
     */
    public function region()
    {
        return $this->belongsTo(Region::class);
    }
}
