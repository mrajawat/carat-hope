<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\VariantAttributeValue;
use App\Models\AttributeValue;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class VariantCombinationService
{
    /**
     * Generate cartesian product combinations from attributes.
     * Enforces the max 2 axes soft limit.
     *
     * @param array $selectedAttributeValueIds E.g., ['metal_karat' => [14, 18], 'ring_size' => [6, 7, 8]]
     * @return array
     * @throws \InvalidArgumentException
     */
    public function generateCombinations(array $selectedAttributeValueIds): array
    {
        if (count($selectedAttributeValueIds) > 2) {
            throw new \InvalidArgumentException("Maximum of 2 variation axes can be selected at once.");
        }

        if (empty($selectedAttributeValueIds)) {
            return [];
        }

        $results = [[]];

        foreach ($selectedAttributeValueIds as $key => $values) {
            $append = [];
            foreach ($results as $product) {
                foreach ($values as $value) {
                    $newProduct = $product;
                    $newProduct[$key] = $value;
                    $append[] = $newProduct;
                }
            }
            $results = $append;
        }

        return $results;
    }

    /**
     * Bulk create product variants from combinations inside a DB transaction.
     *
     * @param Product $product
     * @param array $combinations E.g., [ ['metal_karat' => 14, 'ring_size' => 6], ... ]
     * @return Collection
     */
    public function createVariantsFromCombinations(Product $product, array $combinations): Collection
    {
        return DB::transaction(function () use ($product, $combinations) {
            $createdVariants = collect();
            $skuGenerator = app(SkuGeneratorService::class);

            foreach ($combinations as $combination) {
                // Extract only the attribute value IDs (values of the associative array)
                $attributeValueIds = array_values($combination);

                // Filter out non-numeric values
                $attributeValueIds = array_filter($attributeValueIds, function ($val) {
                    return is_numeric($val) || is_int($val);
                });

                if (empty($attributeValueIds)) {
                    continue;
                }

                // Generate SKU
                $sku = $skuGenerator->generate($product, $attributeValueIds);

                // Create Product Variant record
                $variant = ProductVariant::create([
                    'product_id' => $product->id,
                    'sku' => $sku,
                    'weight_grams' => 0.000,
                    'making_charges' => 0.00,
                    'base_price' => $product->prices_vary ? null : $product->price,
                    'stock_quantity' => 0,
                    'is_active' => true,
                ]);

                // Query relevant attribute values to associate the correct attribute_id
                $attributeValues = AttributeValue::whereIn('id', $attributeValueIds)->get();

                foreach ($attributeValues as $val) {
                    VariantAttributeValue::create([
                        'product_variant_id' => $variant->id,
                        'attribute_id' => $val->attribute_id,
                        'attribute_value_id' => $val->id,
                    ]);
                }

                $createdVariants->push($variant->load(['attributeValues', 'prices']));
            }

            return $createdVariants;
        });
    }
}
