<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\RegionDetectionService;
use App\Services\VariantPricingService;
use Illuminate\Http\Request;

class ProductPriceController extends Controller
{
    protected RegionDetectionService $regionDetectionService;
    protected VariantPricingService $pricingService;

    public function __construct(
        RegionDetectionService $regionDetectionService,
        VariantPricingService $pricingService
    ) {
        $this->regionDetectionService = $regionDetectionService;
        $this->pricingService = $pricingService;
    }

    /**
     * Get price and tax breakdown for a product based on region and selected attribute value IDs.
     */
    public function getPrice(Product $product, Request $request)
    {
        // 1. Detect region
        $region = $this->regionDetectionService->detect($request);

        // 2. Resolve variant
        $variant = null;

        // Try direct variant ID lookup
        $variantId = $request->input('variant_id');
        if ($variantId) {
            $variant = $product->variants()->where('id', $variantId)->first();
        }

        // Try attribute combinations lookup
        if (!$variant) {
            $attributeValueIds = $request->input('attribute_value_ids');
            if (is_string($attributeValueIds)) {
                $attributeValueIds = array_filter(explode(',', $attributeValueIds));
            }

            if (!empty($attributeValueIds) && is_array($attributeValueIds)) {
                $variant = $product->variants()
                    ->where(function ($query) use ($attributeValueIds) {
                        foreach ($attributeValueIds as $valueId) {
                            $query->whereHas('attributeValues', function ($q) use ($valueId) {
                                $q->where('attribute_values.id', $valueId);
                            });
                        }
                    })
                    ->first();
            }
        }

        // Fallback to first active variant
        if (!$variant) {
            $variant = $product->variants()->where('is_active', true)->first();
        }

        if (!$variant) {
            return response()->json([
                'status' => false,
                'message' => 'No active variant found for this product.',
                'data' => null
            ], 404);
        }

        // 3. Resolve price details including tax
        $priceDetails = $this->pricingService->getPriceWithTax($variant, $region->id);

        return response()->json([
            'status' => true,
            'message' => 'Price resolved successfully',
            'data' => [
                'product_id' => $product->id,
                'product_name' => $product->name,
                'variant' => [
                    'id' => $variant->id,
                    'sku' => $variant->sku,
                    'weight_grams' => $variant->weight_grams,
                    'stock_quantity' => $variant->stock_quantity,
                    'is_active' => $variant->is_active,
                ],
                'region' => [
                    'id' => $region->id,
                    'name' => $region->name,
                    'currency_code' => $region->currency_code,
                    'currency_symbol' => $region->currency_symbol,
                ],
                'pricing' => $priceDetails
            ]
        ]);
    }
}
