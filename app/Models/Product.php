<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'sku',
        'category_id',
        'price',
        'discount_price',
        'local_prices',
        'stock_qty',
        'description',
        'is_featured',
        'status',
        'prices_vary',
        'quantities_vary',
        'skus_vary',
        'max_variation_axes',
        'total_stock',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'discount_price' => 'decimal:2',
        'local_prices' => 'array',
        'is_featured' => 'boolean',
        'prices_vary' => 'boolean',
        'quantities_vary' => 'boolean',
        'skus_vary' => 'boolean',
        'max_variation_axes' => 'integer',
        'total_stock' => 'integer',
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function variants()
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function product_images()
    {
        return $this->hasMany(ProductImage::class);
    }

    public function primary_image()
    {
        return $this->hasOne(ProductImage::class)->where('is_primary', true);
    }

    public function reviews()
    {
        return $this->hasMany(ProductReview::class);
    }

    public function hasLocalPrice($countryCode)
    {
        return !empty($this->local_prices) && isset($this->local_prices[$countryCode]);
    }

    public function getLocalPrice($countryCode)
    {
        // 1. Agar country ka price set hai, toh wo return karo
        if (!empty($this->local_prices) && isset($this->local_prices[$countryCode])) {
            $local = $this->local_prices[$countryCode];
            return is_array($local) ? ($local['discount_price'] ?? $local['price']) : $local;
        }
        
        // 2. Agar country ka price NAHI hai, toh pehle 'US' (Dollar) ka price check karo
        if (!empty($this->local_prices) && isset($this->local_prices['US'])) {
            $local = $this->local_prices['US'];
            return is_array($local) ? ($local['discount_price'] ?? $local['price']) : $local;
        }

        // 3. Agar 'US' ka price bhi nahi hai, tabhi base (INR) price dikhao
        return $this->discount_price ?? $this->price;
    }

    public function getLocalOriginalPrice($countryCode)
    {
        if (!empty($this->local_prices) && isset($this->local_prices[$countryCode])) {
            $local = $this->local_prices[$countryCode];
            return is_array($local) ? $local['price'] : $local;
        }
        
        if (!empty($this->local_prices) && isset($this->local_prices['US'])) {
            $local = $this->local_prices['US'];
            return is_array($local) ? $local['price'] : $local;
        }

        return $this->price;
    }
}
