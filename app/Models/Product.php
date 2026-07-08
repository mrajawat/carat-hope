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
        'has_variants',
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
        'has_variants' => 'boolean',
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
        if ($this->has_variants) {
            return true;
        }
        return !empty($this->local_prices) && isset($this->local_prices[$countryCode]);
    }

    public function getLocalPrice($countryCode)
    {
        if ($this->has_variants) {
            $region = Region::where('currency_code', $countryCode)->first();
            if (!$region) {
                $region = Region::where('is_default', true)->first();
            }
            if ($region) {
                $discountPrice = app(\App\Services\VariantPricingService::class)->getStartingDiscountPrice($this, $region->id);
                if ($discountPrice !== null) {
                    return $discountPrice;
                }
                return app(\App\Services\VariantPricingService::class)->getStartingPrice($this, $region->id);
            }
            return null;
        }

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
        if ($this->has_variants) {
            $region = Region::where('currency_code', $countryCode)->first();
            if (!$region) {
                $region = Region::where('is_default', true)->first();
            }
            if ($region) {
                return app(\App\Services\VariantPricingService::class)->getStartingPrice($this, $region->id);
            }
            return null;
        }

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

    public function getPriceAttribute($value)
    {
        if ($this->has_variants) {
            $regionId = $this->getCurrentRegionId();
            return app(\App\Services\VariantPricingService::class)->getStartingPrice($this, $regionId);
        }
        return $value;
    }

    public function getDiscountPriceAttribute($value)
    {
        if ($this->has_variants) {
            $regionId = $this->getCurrentRegionId();
            return app(\App\Services\VariantPricingService::class)->getStartingDiscountPrice($this, $regionId);
        }
        return $value;
    }

    public function getStockQtyAttribute($value)
    {
        if ($this->has_variants) {
            if (!$this->quantities_vary) {
                return $this->total_stock;
            }
            return (int) $this->variants()->where('is_active', true)->sum('stock_quantity');
        }
        return $value;
    }

    public function getSkuAttribute($value)
    {
        if ($this->has_variants) {
            return null;
        }
        return $value;
    }

    protected function getCurrentRegionId(): int
    {
        try {
            $region = app(\App\Services\RegionDetectionService::class)->detect(request());
            return $region ? $region->id : 1;
        } catch (\Exception $e) {
            return 1;
        }
    }
}
