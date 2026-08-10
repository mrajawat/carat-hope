<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\GenerateVariantCombinationsRequest;
use App\Http\Requests\StoreProductVariantRequest;
use App\Http\Requests\BulkUpdateVariantsRequest;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\AttributeValue;
use App\Models\VariantAttributeValue;
use App\Models\VariantPrice;
use App\Services\VariantCombinationService;
use App\Services\SkuGeneratorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductVariantController extends Controller
{
    protected VariantCombinationService $combinationService;

    public function __construct(VariantCombinationService $combinationService)
    {
        $this->combinationService = $combinationService;
    }

    /**
     * List all variants for a product.
     */
    public function index($productId)
    {
        $product = Product::findOrFail($productId);
        $variants = $product->variants()->with(['attributeValues.attribute', 'prices.region'])->get();

        return response()->json([
            'status' => true,
            'message' => 'Product variants retrieved successfully',
            'data' => $variants
        ]);
    }

    /**
     * Generate combinations and bulk create variant stubs.
     */
    public function generateCombinations($productId, GenerateVariantCombinationsRequest $request)
    {
        $product = Product::findOrFail($productId);
        $attributesInput = $request->input('attributes');

        // Transform [{attribute_id: 1, attribute_value_ids: [3]}, ...] into {1: [3], 2: [5]}
        $transformedAttributes = [];
        foreach ($attributesInput as $attr) {
            $transformedAttributes[$attr['attribute_id']] = $attr['attribute_value_ids'];
        }

        try {
            $combinations = $this->combinationService->generateCombinations(
                $transformedAttributes,
                $product->max_variation_axes
            );
            $variants = $this->combinationService->createVariantsFromCombinations($product, $combinations);

            return response()->json([
                'status' => true,
                'message' => 'Product variants generated and created successfully',
                'data' => $variants
            ], 201);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage(),
                'data' => null
            ], 422);
        }
    }

    /**
     * Store a single product variant manually.
     */
    public function store(StoreProductVariantRequest $request)
    {
        $validated = $request->validated();
        $product = Product::findOrFail($validated['product_id']);

        if (!$product->skus_vary) {
            // All variants of this product share one SKU; ignore any per-variant value submitted
            if (empty($product->sku)) {
                $skuGenerator = app(SkuGeneratorService::class);
                $product->sku = $skuGenerator->generateForProduct($product);
                $product->save();
            }
            $validated['sku'] = $product->sku;
        } elseif (empty($validated['sku'])) {
            $skuGenerator = app(SkuGeneratorService::class);
            $attributeValueIds = array_values($validated['attributes']);
            $validated['sku'] = $skuGenerator->generate($product, $attributeValueIds);
        }

        $variant = DB::transaction(function () use ($validated, $product) {
            $variant = ProductVariant::create([
                'product_id' => $validated['product_id'],
                'sku' => $validated['sku'],
                'weight_grams' => $validated['weight_grams'],
                'making_charges' => $validated['making_charges'] ?? 0.00,
                'base_price' => $validated['base_price'] ?? ($product->prices_vary ? null : $product->price),
                'stock_quantity' => $validated['stock_quantity'] ?? 0,
                // Only stored when the listing says processing time varies
                'processing_days' => $product->processing_time_varies
                    ? ($validated['processing_days'] ?? null)
                    : null,
                'variant_images' => $validated['variant_images'] ?? null,
                'is_active' => true,
            ]);

            foreach ($validated['attributes'] as $valueId) {
                $value = AttributeValue::findOrFail($valueId);

                VariantAttributeValue::create([
                    'product_variant_id' => $variant->id,
                    'attribute_id' => $value->attribute_id,
                    'attribute_value_id' => $value->id,
                ]);
            }

            if (!empty($validated['prices']) && is_array($validated['prices'])) {
                foreach ($validated['prices'] as $priceData) {
                    VariantPrice::create([
                        'product_variant_id' => $variant->id,
                        'region_id' => $priceData['region_id'],
                        'price' => $priceData['price'],
                        'compare_at_price' => $priceData['compare_at_price'] ?? null,
                    ]);
                }
            }

            return $variant;
        });

        return response()->json([
            'status' => true,
            'message' => 'Product variant created successfully',
            'data' => $variant->load(['attributeValues.attribute', 'prices'])
        ], 201);
    }

    /**
     * Update an individual product variant.
     */
    public function update(Request $request, $id)
    {
        $variant = ProductVariant::findOrFail($id);

        if ($request->has('sku') && !$variant->product->skus_vary) {
            return response()->json([
                'status' => false,
                'message' => 'This product\'s variants share a single SKU. Update the SKU on the product itself instead of an individual variant.',
            ], 422);
        }

        $validated = $request->validate([
            'sku' => 'sometimes|required|string|unique:product_variants,sku,' . $variant->id,
            'weight_grams' => 'sometimes|required|numeric|min:0',
            'making_charges' => 'sometimes|nullable|numeric|min:0',
            'base_price' => 'sometimes|nullable|numeric|min:0',
            'stock_quantity' => 'sometimes|required|integer|min:0',
            'processing_days' => 'sometimes|nullable|integer|min:0',
            'variant_images' => 'sometimes|nullable|array',
            'is_active' => 'sometimes|boolean',
            'prices' => 'sometimes|nullable|array',
            'prices.*.region_id' => 'required|exists:regions,id',
            'prices.*.price' => 'required|numeric|min:0',
            'prices.*.compare_at_price' => 'nullable|numeric|min:0',
        ]);

        // Mirrors the shared-SKU guard: processing time is owned by the product's
        // processing profile unless the listing says it varies per variant.
        if (array_key_exists('processing_days', $validated) && !$variant->product->processing_time_varies) {
            return response()->json([
                'status' => false,
                'message' => 'This product uses a single processing profile. Change it on the product instead of an individual variant.',
            ], 422);
        }

        DB::transaction(function () use ($variant, $validated) {
            $variant->update(collect($validated)->except('prices')->toArray());

            if (isset($validated['prices']) && is_array($validated['prices'])) {
                foreach ($validated['prices'] as $priceData) {
                    VariantPrice::updateOrCreate(
                        [
                            'product_variant_id' => $variant->id,
                            'region_id' => $priceData['region_id'],
                        ],
                        [
                            'price' => $priceData['price'],
                            'compare_at_price' => $priceData['compare_at_price'] ?? null,
                        ]
                    );
                }
            }
        });

        return response()->json([
            'status' => true,
            'message' => 'Product variant updated successfully',
            'data' => $variant->load(['attributeValues.attribute', 'prices'])
        ]);
    }

    /**
     * Bulk update multiple product variants.
     */
    public function bulkUpdate(BulkUpdateVariantsRequest $request)
    {
        $validated = $request->validated();
        $updatedVariants = [];

        DB::transaction(function () use ($validated, &$updatedVariants) {
            foreach ($validated['variants'] as $item) {
                $variant = ProductVariant::findOrFail($item['id']);

                $updateData = [];
                if (array_key_exists('sku', $item) && $variant->product->skus_vary) {
                    $updateData['sku'] = $item['sku'];
                }
                if (array_key_exists('quantity', $item)) {
                    $updateData['stock_quantity'] = $item['quantity'];
                }
                if (array_key_exists('price', $item)) {
                    $updateData['base_price'] = $item['price'];
                }
                if (array_key_exists('processing_days', $item) && $variant->product->processing_time_varies) {
                    $updateData['processing_days'] = $item['processing_days'];
                }

                if (!empty($updateData)) {
                    $variant->update($updateData);
                }

                $updatedVariants[] = $variant->load(['attributeValues.attribute', 'prices']);
            }
        });

        return response()->json([
            'status' => true,
            'message' => 'Product variants updated successfully in bulk',
            'data' => $updatedVariants
        ]);
    }

    /**
     * Delete a product variant.
     */
    public function destroy($id)
    {
        $variant = ProductVariant::findOrFail($id);
        $variant->delete();

        return response()->json([
            'status' => true,
            'message' => 'Product variant deleted successfully',
            'data' => null
        ]);
    }

    /**
     * Bulk update regional pricing for a product variant.
     */
    public function bulkUpdatePricing(Request $request, $variantId)
    {
        $variant = ProductVariant::findOrFail($variantId);

        $request->validate([
            'prices' => 'required|array',
            'prices.*.region_id' => 'required|exists:regions,id',
            'prices.*.price' => 'required|numeric|min:0',
            'prices.*.compare_at_price' => 'nullable|numeric|min:0',
        ]);

        DB::transaction(function () use ($request, $variant) {
            foreach ($request->prices as $priceData) {
                VariantPrice::updateOrCreate(
                    [
                        'product_variant_id' => $variant->id,
                        'region_id' => $priceData['region_id'],
                    ],
                    [
                        'price' => $priceData['price'],
                        'compare_at_price' => $priceData['compare_at_price'] ?? null,
                    ]
                );
            }
        });

        return response()->json([
            'status' => true,
            'message' => 'Variant pricing updated successfully',
            'data' => $variant->load('prices.region')
        ]);
    }
}
