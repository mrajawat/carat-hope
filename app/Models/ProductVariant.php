<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductVariant extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'sku',
        'weight_grams',
        'making_charges',
        'base_price',
        'stock_quantity',
        'processing_days',
        'variant_images',
        'is_active',
    ];

    protected $casts = [
        'weight_grams' => 'decimal:3',
        'making_charges' => 'decimal:2',
        'base_price' => 'decimal:2',
        'stock_quantity' => 'integer',
        'processing_days' => 'integer',
        'variant_images' => 'array',
        'is_active' => 'boolean',
    ];

    /**
     * Get the parent product.
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get the regional prices for this variant.
     */
    public function prices()
    {
        return $this->hasMany(VariantPrice::class);
    }

    /**
     * Variants sellable in a region: priced for that region, or belonging to a
     * product whose prices don't vary (those share the product's regional price).
     */
    public function scopePricedInRegion($query, int $regionId)
    {
        return $query->where(function ($q) use ($regionId) {
            $q->whereHas('prices', fn ($q) => $q->where('region_id', $regionId))
              ->orWhereHas('product', fn ($q) => $q->where('prices_vary', false));
        });
    }

    /**
     * Get the direct pivot records.
     */
    public function variantAttributeValues()
    {
        return $this->hasMany(VariantAttributeValue::class);
    }

    /**
     * Get the attribute values associated with this variant.
     */
    public function attributeValues()
    {
        return $this->belongsToMany(AttributeValue::class, 'variant_attribute_values', 'product_variant_id', 'attribute_value_id')
            ->withPivot('attribute_id')
            ->withTimestamps();
    }
}
