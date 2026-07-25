<?php

namespace App\Services;

use App\Helpers\ImageHelper;
use App\Helpers\VideoHelper;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\VariantAttributeValue;
use App\Models\VariantPrice;
use App\Models\Region;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProductService
{
    /**
     * Store a product with its images, videos, and variants all at once.
     *
     * @param array $data
     * @return Product
     * @throws \Exception
     */
    public function store(array $data): Product
    {
        return DB::transaction(function () use ($data) {
            $variantsInput = $data['variants'] ?? $data['variations'] ?? null;
            $hasVariants = filter_var(
                $data['has_variants'] ?? (!empty($variantsInput) || !empty($data['attributes'])),
                FILTER_VALIDATE_BOOLEAN
            );

            // Extract tags separately from attributes
            $tags = $data['tags'] ?? null;
            if (is_string($tags)) {
                $tags = array_map('trim', explode(',', $tags));
            }
            if (is_array($tags)) {
                $tags = array_slice($tags, 0, 13);
            }

            // Extract Step 3 attributes & specifications
            $parsedAttrs = $this->parseProductAttributes($data);

            // 1. Prepare product attributes based on variant flag
            $productData = [
                'name' => $data['name'],
                'category_id' => $data['category_id'],
                'description' => $data['description'] ?? null,
                'has_variants' => $hasVariants,
                'status' => $data['status'] ?? 'active',
                'is_featured' => $data['is_featured'] ?? false,
                'local_prices' => $data['local_prices'] ?? null,
                'prices_vary' => filter_var($data['prices_vary'] ?? true, FILTER_VALIDATE_BOOLEAN),
                'quantities_vary' => filter_var($data['quantities_vary'] ?? true, FILTER_VALIDATE_BOOLEAN),
                'skus_vary' => filter_var($data['skus_vary'] ?? true, FILTER_VALIDATE_BOOLEAN),
                'processing_time_varies' => filter_var($data['processing_time_varies'] ?? $data['processing_profiles_vary'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'max_variation_axes' => isset($data['max_variation_axes']) ? (int)$data['max_variation_axes'] : 2,
                'total_stock' => isset($data['total_stock']) ? (int)$data['total_stock'] : 0,
                'tags' => $tags,
                'materials' => $parsedAttrs['materials'],
                'gold_solidity' => $parsedAttrs['gold_solidity'],
                'gold_purity' => $parsedAttrs['gold_purity'],
                'listing_attributes' => $parsedAttrs['listing_attributes'],
                'is_global_pricing_enabled' => filter_var($data['is_global_pricing_enabled'] ?? $data['domestic_and_global_pricing'] ?? true, FILTER_VALIDATE_BOOLEAN),
                'allow_offers' => filter_var($data['allow_offers'] ?? $data['allow_buyer_offers'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'processing_profile' => $data['processing_profile'] ?? null,
                'delivery_option' => $data['delivery_option'] ?? null,
            ];

            if (!$hasVariants) {
                // Populate simple product pricing and stock fields
                $productData['sku'] = $data['sku'] ?? null;
                $productData['price'] = $data['price'] ?? null;
                $productData['discount_price'] = $data['discount_price'] ?? null;
                $productData['stock_qty'] = $data['stock_qty'] ?? 0;
            } else {
                // Variant product: leave simple product fields null
                $productData['sku'] = null;
                $productData['price'] = null;
                $productData['discount_price'] = null;
                $productData['stock_qty'] = null;
            }

            // 2. Create the product record
            $product = Product::create($productData);

            // 3. Auto-generate SKU for simple products if not provided
            if (!$hasVariants && empty($data['sku'])) {
                $skuGenerator = app(SkuGeneratorService::class);
                $product->sku = $skuGenerator->generateForProduct($product);
                $product->save();
            }

            // 4. Handle video upload
            if (!empty($data['video'])) {
                try {
                    $videoUrl = VideoHelper::upload($data['video'], 'products/videos');
                    ProductImage::create([
                        'product_id' => $product->id,
                        'image_path' => $videoUrl,
                        'type' => 'video',
                        'is_primary' => false,
                    ]);
                } catch (\Exception $e) {
                    throw new \Exception('Video upload failed: ' . $e->getMessage());
                }
            }

            // 5. Handle media upload (photos & videos uploaded from same place, first photo in array is main product image)
            if (!empty($data['images']) && is_array($data['images'])) {
                $photoIndex = 0;
                foreach ($data['images'] as $index => $itemData) {
                    if (VideoHelper::isVideoInput($itemData)) {
                        try {
                            $videoUrl = VideoHelper::upload($itemData, 'products/videos');
                            ProductImage::create([
                                'product_id' => $product->id,
                                'image_path' => $videoUrl,
                                'type' => 'video',
                                'is_primary' => false,
                            ]);
                        } catch (\Exception $e) {
                            throw new \Exception('Video upload failed: ' . $e->getMessage());
                        }
                    } else {
                        try {
                            $imageUrl = ImageHelper::uploadBase64($itemData, 'products');
                            ProductImage::create([
                                'product_id' => $product->id,
                                'image_path' => $imageUrl,
                                'type' => 'image',
                                'is_primary' => $photoIndex === 0, // First photo in array is main product image
                            ]);
                            $photoIndex++;
                        } catch (\Exception $e) {
                            throw new \Exception('Image upload failed: ' . $e->getMessage());
                        }
                    }
                }
            }

            // 6. Handle storing variants and their amounts at once
            if ($hasVariants || !empty($variantsInput) || !empty($data['attributes'])) {
                $this->storeOrUpdateVariants($product, $data);
            }

            return $product->load(['product_images', 'variants.attributeValues.attribute', 'variants.prices.region']);
        });
    }

    /**
     * Store or update variants, attribute values, and regional prices for a product.
     *
     * @param Product $product
     * @param array $data
     * @return void
     */
    public function storeOrUpdateVariants(Product $product, array $data): void
    {
        $variantsInput = $data['variants'] ?? $data['variations'] ?? null;

        if (!empty($variantsInput) && is_array($variantsInput)) {
            $skuGenerator = app(SkuGeneratorService::class);

            foreach ($variantsInput as $variantData) {
                if (!is_array($variantData)) {
                    continue;
                }

                $variantId = $variantData['id'] ?? null;

                // 1. Create or update variant record
                $variant = ProductVariant::updateOrCreate(
                    [
                        'id' => $variantId,
                        'product_id' => $product->id,
                    ],
                    [
                        'product_id' => $product->id,
                        'sku' => $variantData['sku'] ?? null,
                        'weight_grams' => $variantData['weight_grams'] ?? 0.000,
                        'making_charges' => $variantData['making_charges'] ?? 0.00,
                        'base_price' => $variantData['base_price'] ?? $variantData['price'] ?? null,
                        'stock_quantity' => $variantData['stock_quantity'] ?? $variantData['stock_qty'] ?? $variantData['quantity'] ?? 0,
                        'processing_days' => $variantData['processing_days'] ?? null,
                        'variant_images' => $variantData['variant_images'] ?? null,
                        'is_active' => filter_var($variantData['is_active'] ?? $variantData['visible'] ?? true, FILTER_VALIDATE_BOOLEAN),
                    ]
                );

                // 2. Associate attributes & attribute values
                $attributesInput = $variantData['attributes'] ?? $variantData['attribute_value_ids'] ?? $variantData['attribute_values'] ?? $variantData['options'] ?? null;
                $associatedAttrValueIds = $this->parseAndAssociateVariantAttributes($variant, $attributesInput);

                // Auto-generate SKU if empty
                if (empty($variant->sku)) {
                    $variant->sku = $skuGenerator->generate($product, $associatedAttrValueIds);
                    $variant->save();
                }

                // 3. Save regional prices / amounts
                $pricesInput = $variantData['prices'] ?? $variantData['regional_prices'] ?? null;
                $this->parseAndSaveVariantPrices($variant, $pricesInput);
            }
        } elseif (!empty($data['attributes']) && is_array($data['attributes'])) {
            $combinationService = app(VariantCombinationService::class);
            $transformedAttributes = [];
            foreach ($data['attributes'] as $attrKey => $attrVal) {
                if (is_array($attrVal) && isset($attrVal['attribute_id']) && isset($attrVal['attribute_value_ids'])) {
                    $transformedAttributes[$attrVal['attribute_id']] = $attrVal['attribute_value_ids'];
                } elseif (is_numeric($attrKey) && is_array($attrVal)) {
                    $transformedAttributes[$attrKey] = $attrVal;
                }
            }

            if (!empty($transformedAttributes)) {
                $combinations = $combinationService->generateCombinations($transformedAttributes);
                $combinationService->createVariantsFromCombinations($product, $combinations);
            }
        }
    }

    /**
     * Parse and associate attribute values with a product variant.
     *
     * @param ProductVariant $variant
     * @param mixed $attributesInput
     * @return array Array of associated attribute value IDs
     */
    protected function parseAndAssociateVariantAttributes(ProductVariant $variant, $attributesInput): array
    {
        VariantAttributeValue::where('product_variant_id', $variant->id)->delete();
        $associatedIds = [];

        if (empty($attributesInput)) {
            return $associatedIds;
        }

        if (is_array($attributesInput)) {
            foreach ($attributesInput as $key => $val) {
                $attrValRecord = null;

                if (is_numeric($val) || is_int($val)) {
                    $attrValRecord = AttributeValue::find((int)$val);
                } elseif (is_array($val)) {
                    if (isset($val['attribute_value_id'])) {
                        $attrValRecord = AttributeValue::find((int)$val['attribute_value_id']);
                    } elseif (isset($val['name']) && isset($val['value'])) {
                        $attribute = Attribute::firstOrCreate(
                            ['name' => $val['name']],
                            ['slug' => Str::slug($val['name']), 'input_type' => 'select', 'affects_price' => true]
                        );
                        $attrValRecord = AttributeValue::firstOrCreate(
                            ['attribute_id' => $attribute->id, 'value' => $val['value']],
                            ['price_modifier' => 0.00]
                        );
                    }
                } elseif (is_string($val)) {
                    $attrValRecord = AttributeValue::where('value', $val)->first();
                    if (!$attrValRecord && is_string($key) && !is_numeric($key)) {
                        $attribute = Attribute::firstOrCreate(
                            ['name' => $key],
                            ['slug' => Str::slug($key), 'input_type' => 'select', 'affects_price' => true]
                        );
                        $attrValRecord = AttributeValue::firstOrCreate(
                            ['attribute_id' => $attribute->id, 'value' => $val],
                            ['price_modifier' => 0.00]
                        );
                    }
                }

                if ($attrValRecord) {
                    VariantAttributeValue::create([
                        'product_variant_id' => $variant->id,
                        'attribute_id' => $attrValRecord->attribute_id,
                        'attribute_value_id' => $attrValRecord->id,
                    ]);
                    $associatedIds[] = $attrValRecord->id;
                }
            }
        }

        return $associatedIds;
    }

    /**
     * Parse and save regional prices / amounts for a product variant.
     *
     * @param ProductVariant $variant
     * @param mixed $pricesInput
     * @return void
     */
    protected function parseAndSaveVariantPrices(ProductVariant $variant, $pricesInput): void
    {
        if (empty($pricesInput)) {
            return;
        }

        $allRegions = Region::all();
        $defaultRegion = $allRegions->firstWhere('is_default', true) ?? $allRegions->first();

        if (is_array($pricesInput)) {
            foreach ($pricesInput as $key => $priceData) {
                $regionId = null;
                $priceVal = null;
                $compareAt = null;

                if (is_array($priceData)) {
                    $regionId = $priceData['region_id'] ?? null;
                    $priceVal = $priceData['price'] ?? null;
                    $compareAt = $priceData['compare_at_price'] ?? null;

                    if (!$regionId && isset($priceData['region_name'])) {
                        $matchedReg = $allRegions->first(fn($r) => strcasecmp($r->name, $priceData['region_name']) === 0);
                        $regionId = $matchedReg?->id;
                    }
                } elseif (is_numeric($priceData)) {
                    $priceVal = (float)$priceData;

                    if (is_numeric($key)) {
                        $regionId = (int)$key;
                    } else {
                        $keyClean = strtolower(trim(str_replace(['price in', 'price'], '', strtolower($key))));
                        $matchedReg = $allRegions->first(function ($r) use ($keyClean) {
                            return str_contains(strtolower($r->name), $keyClean) || str_contains(strtolower($r->currency_code), $keyClean);
                        });

                        if (!$matchedReg && (str_contains($keyClean, 'everywhere') || str_contains($keyClean, 'default'))) {
                            $matchedReg = $defaultRegion;
                        }

                        $regionId = $matchedReg?->id ?? $defaultRegion?->id;
                    }
                }

                if ($regionId && $priceVal !== null) {
                    VariantPrice::updateOrCreate(
                        [
                            'product_variant_id' => $variant->id,
                            'region_id' => $regionId,
                        ],
                        [
                            'price' => $priceVal,
                            'compare_at_price' => $compareAt,
                        ]
                    );
                }
            }
        }
    }

    /**
     * Dynamically parse non-variant product attributes and listing specifications.
     * Supports both Attribute/AttributeValue Master IDs (attribute_id, attribute_value_id/ids)
     * as well as string key-value pairs.
     */
    protected function parseProductAttributes(array $data): array
    {
        $listingAttributes = [];

        $inputAttrs = $data['attributes'] ?? $data['listing_attributes'] ?? $data['item_attributes'] ?? [];

        if (is_array($inputAttrs)) {
            if (isset($inputAttrs[0]) && is_array($inputAttrs[0])) {
                // List of attribute objects e.g. [{"attribute_id": 1, "attribute_value_ids": [5, 6]}] or [{"name": "Gold Purity", "values": ["18k"]}]
                foreach ($inputAttrs as $item) {
                    $attrKey = null;
                    $attrVal = null;
                    $attrId = $item['attribute_id'] ?? null;

                    if ($attrId) {
                        $attributeModel = Attribute::find($attrId);
                        if ($attributeModel) {
                            $attrKey = Str::slug($attributeModel->name, '_');
                        }
                    }

                    if (!$attrKey) {
                        $name = $item['name'] ?? $item['key'] ?? null;
                        if (!empty($name)) {
                            $attrKey = Str::slug($name, '_');
                        }
                    }

                    if (!empty($attrKey) && strtolower(trim($attrKey)) !== 'tags') {
                        if (isset($item['attribute_value_ids']) && is_array($item['attribute_value_ids'])) {
                            $attrVal = AttributeValue::whereIn('id', $item['attribute_value_ids'])->pluck('value')->toArray();
                        } elseif (isset($item['attribute_value_id'])) {
                            $valModel = AttributeValue::find($item['attribute_value_id']);
                            $attrVal = $valModel ? $valModel->value : null;
                        }

                        if ($attrVal === null) {
                            $attrVal = $item['values'] ?? $item['value'] ?? $item['options'] ?? null;
                        }

                        $listingAttributes[$attrKey] = [
                            'attribute_id' => $attrId,
                            'attribute_value_ids' => $item['attribute_value_ids'] ?? (isset($item['attribute_value_id']) ? [$item['attribute_value_id']] : null),
                            'value' => $attrVal
                        ];
                    }
                }
            } else {
                // Associative map e.g. {"1": [5, 6]} or {"gold_purity": ["18k"]}
                foreach ($inputAttrs as $key => $val) {
                    if (strtolower(trim((string)$key)) !== 'tags') {
                        if (is_numeric($key)) {
                            $attributeModel = Attribute::find((int)$key);
                            if ($attributeModel) {
                                $slugKey = Str::slug($attributeModel->name, '_');
                                $resolvedVal = $val;
                                $valIds = is_array($val) ? $val : [$val];
                                $dbVals = AttributeValue::whereIn('id', $valIds)->pluck('value')->toArray();
                                if (!empty($dbVals)) {
                                    $resolvedVal = (count($dbVals) === 1 && !is_array($val)) ? $dbVals[0] : $dbVals;
                                }
                                $listingAttributes[$slugKey] = [
                                    'attribute_id' => (int)$key,
                                    'attribute_value_ids' => $valIds,
                                    'value' => $resolvedVal
                                ];
                                continue;
                            }
                        }
                        $listingAttributes[$key] = $val;
                    }
                }
            }
        }

        // 2. Also capture any dynamic top-level request keys that are not core product columns
        $knownProductColumns = [
            'name', 'sku', 'category_id', 'price', 'discount_price', 'local_prices',
            'stock_qty', 'description', 'is_featured', 'status', 'prices_vary',
            'quantities_vary', 'skus_vary', 'processing_time_varies', 'processing_profiles_vary',
            'max_variation_axes', 'total_stock', 'has_variants', 'images', 'video',
            'variants', 'variations', 'attributes', 'listing_attributes', 'item_attributes',
            'tags', 'materials', 'gold_solidity', 'gold_purity',
            'is_global_pricing_enabled', 'domestic_and_global_pricing',
            'allow_offers', 'allow_buyer_offers', 'processing_profile', 'delivery_option'
        ];

        foreach ($data as $key => $value) {
            if (!in_array($key, $knownProductColumns) && !empty($value)) {
                $listingAttributes[$key] = $value;
            }
        }

        $extractVal = function ($attrKey) use ($listingAttributes, $data) {
            $item = $listingAttributes[$attrKey] ?? $data[$attrKey] ?? null;
            if (is_array($item) && isset($item['value'])) {
                return $item['value'];
            }
            return $item;
        };

        $materials = $extractVal('materials') ?? $extractVal('material');
        $goldSolidity = $extractVal('gold_solidity');
        $goldPurity = $extractVal('gold_purity');

        if (is_string($materials)) {
            $materials = array_map('trim', explode(',', $materials));
        }
        if (is_string($goldSolidity)) {
            $goldSolidity = array_map('trim', explode(',', $goldSolidity));
        }
        if (is_string($goldPurity)) {
            $goldPurity = array_map('trim', explode(',', $goldPurity));
        }

        return [
            'materials' => $materials,
            'gold_solidity' => $goldSolidity,
            'gold_purity' => $goldPurity,
            'listing_attributes' => !empty($listingAttributes) ? $listingAttributes : null,
        ];
    }

    /**
     * Generate an in-memory preview of a product without saving to the database.
     *
     * @param array $data
     * @return array
     */
    public function preview(array $data): array
    {
        $variantsInput = $data['variants'] ?? $data['variations'] ?? null;
        $hasVariants = filter_var(
            $data['has_variants'] ?? (!empty($variantsInput) || !empty($data['attributes'])),
            FILTER_VALIDATE_BOOLEAN
        );

        $parsedAttrs = $this->parseProductAttributes($data);

        $product = new Product();
        $product->id = 0;
        $product->name = $data['name'] ?? 'Untitled Product';
        $product->category_id = $data['category_id'] ?? null;
        $product->description = $data['description'] ?? null;
        $product->has_variants = $hasVariants;
        $product->status = $data['status'] ?? 'draft';
        $product->is_featured = filter_var($data['is_featured'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $product->local_prices = $data['local_prices'] ?? null;
        $product->prices_vary = filter_var($data['prices_vary'] ?? true, FILTER_VALIDATE_BOOLEAN);
        $product->quantities_vary = filter_var($data['quantities_vary'] ?? true, FILTER_VALIDATE_BOOLEAN);
        $product->skus_vary = filter_var($data['skus_vary'] ?? true, FILTER_VALIDATE_BOOLEAN);
        $product->processing_time_varies = filter_var($data['processing_time_varies'] ?? $data['processing_profiles_vary'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $product->max_variation_axes = isset($data['max_variation_axes']) ? (int)$data['max_variation_axes'] : 2;
        $product->total_stock = isset($data['total_stock']) ? (int)$data['total_stock'] : 0;
        $product->tags = $data['tags'] ?? null;
        $product->materials = $parsedAttrs['materials'];
        $product->gold_solidity = $parsedAttrs['gold_solidity'];
        $product->gold_purity = $parsedAttrs['gold_purity'];
        $product->listing_attributes = $parsedAttrs['listing_attributes'];
        $product->is_global_pricing_enabled = filter_var($data['is_global_pricing_enabled'] ?? $data['domestic_and_global_pricing'] ?? true, FILTER_VALIDATE_BOOLEAN);
        $product->allow_offers = filter_var($data['allow_offers'] ?? $data['allow_buyer_offers'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $product->processing_profile = $data['processing_profile'] ?? null;
        $product->delivery_option = $data['delivery_option'] ?? null;

        if (!$hasVariants) {
            $product->sku = $data['sku'] ?? 'PREVIEW-SKU';
            $product->price = isset($data['price']) ? (float)$data['price'] : 0.00;
            $product->discount_price = isset($data['discount_price']) ? (float)$data['discount_price'] : null;
            $product->stock_qty = isset($data['stock_qty']) ? (int)$data['stock_qty'] : 0;
        }

        // Preview images/videos
        $images = [];
        $video = null;
        if (!empty($data['images']) && is_array($data['images'])) {
            $photoIndex = 0;
            foreach ($data['images'] as $item) {
                if (VideoHelper::isVideoInput($item)) {
                    $video = is_string($item) ? $item : 'preview_video_path';
                } else {
                    $images[] = [
                        'id' => 0,
                        'image_path' => is_string($item) ? $item : 'preview_image_path',
                        'is_primary' => $photoIndex === 0,
                        'type' => 'image'
                    ];
                    $photoIndex++;
                }
            }
        }
        if (!empty($data['video'])) {
            $video = is_string($data['video']) ? $data['video'] : 'preview_video_path';
        }

        // Preview variants
        $variantsPreview = [];
        if ($hasVariants && !empty($variantsInput) && is_array($variantsInput)) {
            foreach ($variantsInput as $vIdx => $vData) {
                $variantItem = [
                    'id' => $vIdx + 1,
                    'sku' => $vData['sku'] ?? ("PREVIEW-VAR-" . ($vIdx + 1)),
                    'stock_quantity' => isset($vData['stock_quantity']) ? (int)$vData['stock_quantity'] : (isset($vData['stock_qty']) ? (int)$vData['stock_qty'] : 0),
                    'is_active' => isset($vData['is_active']) ? (bool)$vData['is_active'] : true,
                    'attributes' => $vData['attributes'] ?? $vData['attribute_values'] ?? $vData['options'] ?? [],
                    'prices' => $vData['prices'] ?? $vData['regional_prices'] ?? []
                ];
                $variantsPreview[] = $variantItem;
            }
        }

        return [
            'product' => $product->toArray(),
            'images' => $images,
            'video' => $video,
            'variants' => $variantsPreview,
            'is_preview' => true,
        ];
    }
}
