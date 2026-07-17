<?php

namespace App\Services;

use App\Models\ProductVariant;
use App\Models\Region;
use App\Models\RegionTaxRule;
use App\Models\VariantPrice;

class VariantPricingService
{
    private static array $regionsCache = [];
    private static ?Region $defaultRegionCache = null;

    /**
     * Get price for a variant in a specific region, applying fallbacks if prices_vary is enabled.
     *
     * @param ProductVariant $variant
     * @param int $regionId
     * @return array
     */
    public function getPriceForRegion(ProductVariant $variant, int $regionId): array
    {
        if (!isset(self::$regionsCache[$regionId])) {
            $region = Region::find($regionId);
            if (!$region) {
                $region = self::$defaultRegionCache ??= Region::where('is_default', true)->first();
            }
            self::$regionsCache[$regionId] = $region;
        }
        $region = self::$regionsCache[$regionId] ?? self::$defaultRegionCache ??= Region::where('is_default', true)->first();

        $product = $variant->product;
        $price = 0.00;
        $compareAtPrice = null;

        if (!$product->prices_vary) {
            // pricing resolves from variant's base_price, falling back to parent product price
            if ($variant->base_price !== null && $variant->base_price > 0) {
                $price = $variant->base_price;
                $compareAtPrice = null;
            } else {
                $price = $product->discount_price ?? $product->price;
                $compareAtPrice = $product->discount_price ? $product->price : null;
            }
        } else {
            // Find regional price for this variant
            $variantPrice = $variant->relationLoaded('prices')
                ? $variant->prices->firstWhere('region_id', $region->id)
                : VariantPrice::where('product_variant_id', $variant->id)
                    ->where('region_id', $region->id)
                    ->first();

            if (!$variantPrice) {
                // Fallback to the default region's price
                $defaultRegion = self::$defaultRegionCache ??= Region::where('is_default', true)->first();
                if ($defaultRegion) {
                    $variantPrice = $variant->relationLoaded('prices')
                        ? $variant->prices->firstWhere('region_id', $defaultRegion->id)
                        : VariantPrice::where('product_variant_id', $variant->id)
                            ->where('region_id', $defaultRegion->id)
                            ->first();
                }
            }

            if ($variantPrice) {
                $price = $variantPrice->price;
                $compareAtPrice = $variantPrice->compare_at_price;
            } else {
                // If no regional price is set at all, use variant base_price or product's raw price columns
                if ($variant->base_price !== null && $variant->base_price > 0) {
                    $price = $variant->base_price;
                    $compareAtPrice = null;
                } else {
                    $rawPrice = $product->getRawOriginal('price');
                    $rawDiscountPrice = $product->getRawOriginal('discount_price');

                    $price = $rawDiscountPrice ?? $rawPrice;
                    $compareAtPrice = $rawDiscountPrice ? $rawPrice : null;
                }
            }
        }

        return [
            'price' => (float) $price,
            'compare_at_price' => $compareAtPrice !== null ? (float) $compareAtPrice : null,
            'currency_symbol' => $region->currency_symbol,
            'currency_code' => $region->currency_code,
        ];
    }

    /**
     * Get price details including tax breakdown for a variant in a specific region.
     *
     * @param ProductVariant $variant
     * @param int $regionId
     * @return array
     */
    public function getPriceWithTax(ProductVariant $variant, int $regionId): array
    {
        $priceData = $this->getPriceForRegion($variant, $regionId);
        $price = $priceData['price'];

        $taxRule = RegionTaxRule::where('region_id', $regionId)->first();

        if (!$taxRule) {
            // Fallback to default region tax rule if none defined for this region
            $defaultRegion = Region::where('is_default', true)->first();
            if ($defaultRegion && $defaultRegion->id !== $regionId) {
                $taxRule = RegionTaxRule::where('region_id', $defaultRegion->id)->first();
            }
        }

        $taxName = $taxRule ? $taxRule->tax_name : 'Tax';
        $taxPercentage = $taxRule ? (float) $taxRule->tax_percentage : 0.00;
        $inclusive = $taxRule ? (bool) $taxRule->inclusive : false;

        if ($taxPercentage > 0) {
            if ($inclusive) {
                // Tax is included: Subtotal = Price / (1 + (tax% / 100))
                $subtotal = $price / (1 + ($taxPercentage / 100));
                $taxAmount = $price - $subtotal;
                $total = $price;
            } else {
                // Tax is excluded: Subtotal = Price, Tax = Price * (tax% / 100), Total = Price + Tax
                $subtotal = $price;
                $taxAmount = $price * ($taxPercentage / 100);
                $total = $price + $taxAmount;
            }
        } else {
            $subtotal = $price;
            $taxAmount = 0.00;
            $total = $price;
        }

        return [
            'subtotal' => round($subtotal, 2),
            'tax_amount' => round($taxAmount, 2),
            'tax_name' => $taxName,
            'total' => round($total, 2),
            'currency_symbol' => $priceData['currency_symbol'],
            'currency_code' => $priceData['currency_code'],
            'compare_at_price' => $priceData['compare_at_price'],
        ];
    }

    /**
     * Get starting (lowest) price for a product.
     *
     * @param \App\Models\Product $product
     * @param int $regionId
     * @return float|null
     */
    public function getStartingPrice(\App\Models\Product $product, int $regionId): ?float
    {
        $variants = $product->relationLoaded('variants')
            ? $product->variants->where('is_active', true)
            : $product->variants()->where('is_active', true)->get();

        if ($variants->isEmpty()) {
            return null;
        }

        $minOriginalPrice = null;
        foreach ($variants as $variant) {
            $variant->setRelation('product', $product);
            $priceData = $this->getPriceForRegion($variant, $regionId);
            $origPrice = $priceData['compare_at_price'] ?? $priceData['price'];
            if ($minOriginalPrice === null || $origPrice < $minOriginalPrice) {
                $minOriginalPrice = $origPrice;
            }
        }

        return $minOriginalPrice !== null ? (float) $minOriginalPrice : null;
    }

    /**
     * Get starting (lowest) discount price for a product.
     *
     * @param \App\Models\Product $product
     * @param int $regionId
     * @return float|null
     */
    public function getStartingDiscountPrice(\App\Models\Product $product, int $regionId): ?float
    {
        $variants = $product->relationLoaded('variants')
            ? $product->variants->where('is_active', true)
            : $product->variants()->where('is_active', true)->get();

        if ($variants->isEmpty()) {
            return null;
        }

        $minDiscountPrice = null;
        $hasAnyDiscount = false;

        foreach ($variants as $variant) {
            $variant->setRelation('product', $product);
            $priceData = $this->getPriceForRegion($variant, $regionId);
            if ($priceData['compare_at_price'] !== null) {
                $hasAnyDiscount = true;
                $discPrice = $priceData['price'];
                if ($minDiscountPrice === null || $discPrice < $minDiscountPrice) {
                    $minDiscountPrice = $discPrice;
                }
            }
        }

        return $hasAnyDiscount && $minDiscountPrice !== null ? (float) $minDiscountPrice : null;
    }
}
