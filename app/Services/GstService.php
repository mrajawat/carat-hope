<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductGstRate;
use App\Models\Region;
use App\Models\RegionTaxRule;

class GstService
{
    /**
     * Region for a detected ISO country code, using the same mapping as
     * RegionDetectionService, falling back to the default region.
     */
    public function regionForCountry(string $countryCode): ?Region
    {
        $regionName = match (strtoupper($countryCode)) {
            'IN' => 'India',
            'US' => 'United States',
            default => 'Rest of World',
        };

        return Region::where('name', $regionName)->first()
            ?? Region::where('is_default', true)->first();
    }

    /**
     * GST % added on top of a product's price in a region: the product's own rate
     * for that region, else the region's tax rule. An inclusive region rule means
     * the price already contains the tax, so nothing is added.
     */
    public function rateFor(Product $product, ?Region $region): float
    {
        if (!$region) {
            return 0.00;
        }

        $productRate = ProductGstRate::where('product_id', $product->id)
            ->where('region_id', $region->id)
            ->value('gst_percentage');

        if ($productRate !== null) {
            return (float) $productRate;
        }

        $regionRule = RegionTaxRule::where('region_id', $region->id)->first();

        return $regionRule && !$regionRule->inclusive ? (float) $regionRule->tax_percentage : 0.00;
    }

    /**
     * Same as rateFor(), by region id. Uses the product's loaded gstRates when present.
     */
    public function rateForRegionId(Product $product, ?int $regionId): float
    {
        if (!$regionId) {
            return 0.00;
        }

        $productRate = $product->relationLoaded('gstRates')
            ? $product->gstRates->firstWhere('region_id', $regionId)?->gst_percentage
            : ProductGstRate::where('product_id', $product->id)->where('region_id', $regionId)->value('gst_percentage');

        if ($productRate !== null) {
            return (float) $productRate;
        }

        $regionRule = RegionTaxRule::where('region_id', $regionId)->first();

        return $regionRule && !$regionRule->inclusive ? (float) $regionRule->tax_percentage : 0.00;
    }

    /**
     * GST amount on a price at the given rate, rounded to 2 decimals.
     */
    public function amountFor(?float $price, float $rate): ?float
    {
        return $price === null ? null : round($price * $rate / 100, 2);
    }

    /**
     * Replace a product's GST rates with the given list.
     *
     * @param array $ratesInput List of ['region_id' => int, 'gst_percentage' => float]
     */
    public function syncProductRates(Product $product, array $ratesInput): void
    {
        $regionIds = [];

        foreach ($ratesInput as $rate) {
            if (!is_array($rate) || empty($rate['region_id']) || !isset($rate['gst_percentage'])) {
                continue;
            }

            ProductGstRate::updateOrCreate(
                ['product_id' => $product->id, 'region_id' => $rate['region_id']],
                ['gst_percentage' => $rate['gst_percentage']]
            );
            $regionIds[] = (int) $rate['region_id'];
        }

        $product->gstRates()->whereNotIn('region_id', $regionIds)->delete();
    }
}
