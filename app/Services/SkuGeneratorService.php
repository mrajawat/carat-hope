<?php

namespace App\Services;

use App\Models\Product;
use App\Models\AttributeValue;
use App\Models\ProductVariant;

class SkuGeneratorService
{
    /**
     * Generate a unique SKU for a product variant based on selected attribute values.
     * Pattern: {BRAND_PREFIX}-{CATEGORY_CODE}-{PRODUCT_ID}-{ATTRIBUTE_SHORTCODES}
     *
     * @param Product $product
     * @param array $attributeValueIds
     * @return string
     */
    public function generate(Product $product, array $attributeValueIds): string
    {
        $brandPrefix = config('jewelry.brand_prefix', 'CH');

        $category = $product->category;
        $categoryCode = $category ? strtoupper($category->slug) : 'GEN';
        // Clean category code to be uppercase alphanumeric only
        $categoryCode = preg_replace('/[^A-Z0-9]/', '', $categoryCode);
        if (empty($categoryCode)) {
            $categoryCode = 'GEN';
        }

        $productId = $product->id;

        // Fetch attribute values and sort by attribute_id for a consistent SKU order
        $values = AttributeValue::whereIn('id', $attributeValueIds)
            ->orderBy('attribute_id', 'asc')
            ->orderBy('sort_order', 'asc')
            ->get();

        $shortcodes = [];
        foreach ($values as $value) {
            // Convert to uppercase, keep only alphanumeric characters
            $cleanVal = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $value->value));
            if (!empty($cleanVal)) {
                $shortcodes[] = $cleanVal;
            }
        }

        $shortcodeSuffix = implode('-', $shortcodes);

        $baseSku = $brandPrefix . '-' . $categoryCode . '-' . $productId;
        if (!empty($shortcodeSuffix)) {
            $baseSku .= '-' . $shortcodeSuffix;
        }

        $sku = $baseSku;
        $counter = 1;

        // Loop to guarantee SKU uniqueness across variants and parent products
        while (
            ProductVariant::where('sku', $sku)->exists() ||
            Product::where('sku', $sku)->exists()
        ) {
            $sku = $baseSku . '-' . $counter;
            $counter++;
        }

        return $sku;
    }

    /**
     * Generate a unique SKU for a simple (non-variant) product.
     * Pattern: {BRAND_PREFIX}-{CATEGORY_CODE}-{PRODUCT_ID}
     *
     * @param Product $product
     * @return string
     */
    public function generateForProduct(Product $product): string
    {
        $brandPrefix = config('jewelry.brand_prefix', 'CH');

        $category = $product->category;
        $categoryCode = $category ? strtoupper($category->slug) : 'GEN';
        $categoryCode = preg_replace('/[^A-Z0-9]/', '', $categoryCode);
        if (empty($categoryCode)) {
            $categoryCode = 'GEN';
        }

        $baseSku = $brandPrefix . '-' . $categoryCode . '-' . $product->id;
        $sku = $baseSku;
        $counter = 1;

        while (
            Product::where('sku', $sku)->where('id', '!=', $product->id)->exists() ||
            ProductVariant::where('sku', $sku)->exists()
        ) {
            $sku = $baseSku . '-' . $counter;
            $counter++;
        }

        return $sku;
    }
}
