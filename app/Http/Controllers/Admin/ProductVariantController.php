<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\GenerateVariantCombinationsRequest;
use App\Http\Requests\StoreProductVariantRequest;
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
            $combinations = $this->combinationService->generateCombinations($transformedAttributes);
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

        if (empty($validated['sku'])) {
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

        $validated = $request->validate([
            'sku' => 'sometimes|required|string|unique:product_variants,sku,' . $variant->id,
            'weight_grams' => 'sometimes|required|numeric|min:0',
            'making_charges' => 'sometimes|nullable|numeric|min:0',
            'base_price' => 'sometimes|nullable|numeric|min:0',
            'stock_quantity' => 'sometimes|required|integer|min:0',
            'variant_images' => 'sometimes|nullable|array',
            'is_active' => 'sometimes|boolean',
        ]);

        $variant->update($validated);

        return response()->json([
            'status' => true,
            'message' => 'Product variant updated successfully',
            'data' => $variant->load(['attributeValues.attribute', 'prices'])
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
