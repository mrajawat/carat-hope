<?php

namespace App\Http\Resources;

use App\Services\RegionDetectionService;
use App\Services\VariantPricingService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductDetailResource extends JsonResource
{
    public static ?int $regionId = null;

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $regionId = self::$regionId 
            ?? $this->additional['meta']['region_id'] 
            ?? app(RegionDetectionService::class)->detect($request)->id;

        $pricingService = app(VariantPricingService::class);

        $images = $this->product_images->where('type', 'image')->pluck('image_path')->values()->toArray();
        $video = $this->product_images->where('type', 'video')->first()?->image_path;

        $data = [
            'id' => $this->id,
            'name' => $this->name,
            'has_variants' => (bool)$this->has_variants,
            'processing_time_varies' => (bool)$this->processing_time_varies,
            'category_id' => $this->category_id,
            'category' => $this->whenLoaded('category'),
            'description' => $this->description,
            'is_featured' => (bool)$this->is_featured,
            'status' => $this->status,
            'images' => $images,
            'video' => $video,
            'tags' => $this->tags,
            // Labels for display, plus the master value ids the labels resolve to
            'materials' => $this->materials,
            'materials_ids' => data_get($this->listing_attributes, 'materials.attribute_value_ids', []),
            'gold_solidity' => $this->gold_solidity,
            'gold_solidity_ids' => data_get($this->listing_attributes, 'gold_solidity.attribute_value_ids', []),
            'gold_purity' => $this->gold_purity,
            'gold_purity_ids' => data_get($this->listing_attributes, 'gold_purity.attribute_value_ids', []),
            'listing_attributes' => $this->listing_attributes,
            'is_global_pricing_enabled' => (bool)($this->is_global_pricing_enabled ?? true),
            'allow_offers' => (bool)($this->allow_offers ?? false),
            'max_offer_discount_percent' => $this->max_offer_discount_percent,
            'custom_options' => $this->whenLoaded('customOptions'),
            'max_offer_discount_percent' => $this->max_offer_discount_percent,
            'custom_options' => $this->whenLoaded('customOptions'),
            'processing_profile_id' => $this->processing_profile_id,
            'processing_profile' => $this->whenLoaded('processingProfile'),
            'shipping_profile_id' => $this->shipping_profile_id,
            'shipping_profile' => $this->whenLoaded('shippingProfile'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];

        if (!$this->has_variants) {
            $priceData = $pricingService->getProductPriceForRegion($this->resource, $regionId);

            $productPrices = $this->relationLoaded('prices')
                ? $this->prices
                : $this->prices()->with('region')->get();

            $regionalPrices = [];
            foreach ($productPrices as $pPrice) {
                $regionalPrices[] = [
                    'region_id' => $pPrice->region_id,
                    'price' => (float) $pPrice->price,
                    'compare_at_price' => $pPrice->compare_at_price ? (float) $pPrice->compare_at_price : null,
                    'currency_code' => $pPrice->region->currency_code ?? null,
                    'currency_symbol' => $pPrice->region->currency_symbol ?? null,
                ];
            }

            $data['sku'] = $this->sku;
            $data['price'] = $priceData['compare_at_price'] ?? $priceData['price'];
            $data['discount_price'] = $priceData['compare_at_price'] !== null ? $priceData['price'] : null;
            $data['stock_qty'] = $this->stock_qty;
            $data['prices'] = $regionalPrices;
            $data['attributes'] = null;
            $data['variants'] = null;
        } else {
            $data['sku'] = null;
            $data['price'] = $pricingService->getStartingPrice($this->resource, $regionId);
            $data['discount_price'] = null;

            if (!$this->quantities_vary) {
                $data['stock_qty'] = $this->total_stock;
            } else {
                $variantsCollection = $this->relationLoaded('variants')
                    ? $this->variants->where('is_active', true)
                    : $this->variants()->where('is_active', true)->get();
                $data['stock_qty'] = (int) $variantsCollection->sum('stock_quantity');
            }

            // Variants are resolved first so the selector axes can be derived from them
            $activeVariants = $this->relationLoaded('variants')
                ? $this->variants->where('is_active', true)
                : $this->variants()->where('is_active', true)->get();

            // Convert to Eloquent Collection if it is a Support Collection to allow loadMissing
            if (!($activeVariants instanceof \Illuminate\Database\Eloquent\Collection)) {
                $activeVariants = new \Illuminate\Database\Eloquent\Collection($activeVariants->all());
            }
            $activeVariants->loadMissing(['prices.region', 'attributeValues']);

            // Selector axes: only the attributes this product actually varies by, and
            // within each, only the values some variant uses. Deriving these from the
            // variants (rather than from every attribute mapped to the category) keeps
            // the storefront from rendering selectors that match no variant.
            $usedValueIds = [];
            foreach ($activeVariants as $variant) {
                foreach ($variant->attributeValues as $attributeValue) {
                    $usedValueIds[] = $attributeValue->id;
                }
            }

            $attributesData = [];
            if (!empty($usedValueIds)) {
                $usedValues = \App\Models\AttributeValue::with('attribute')
                    ->whereIn('id', array_unique($usedValueIds))
                    ->orderBy('sort_order')
                    ->orderBy('value')
                    ->get();

                foreach ($usedValues->groupBy('attribute_id') as $groupedValues) {
                    $attribute = $groupedValues->first()->attribute;

                    if (!$attribute) {
                        continue;
                    }

                    $attributesData[] = [
                        'id' => $attribute->id,
                        'name' => $attribute->name,
                        'input_type' => $attribute->input_type,
                        'unit' => $attribute->unit,
                        'values' => $groupedValues->map(fn ($val) => [
                            'id' => $val->id,
                            'value' => $val->value,
                        ])->values()->all(),
                    ];
                }
            }
            $data['attributes'] = $attributesData;

            // Variants
            $variantsData = [];

            foreach ($activeVariants as $variant) {
                $variant->setRelation('product', $this->resource);
                $priceInfo = $pricingService->getPriceForRegion($variant, $regionId);

                $attributeValueIds = $variant->attributeValues->pluck('id')->toArray();
                $attributeValueIds = array_map('intval', $attributeValueIds);

                $linkedPhotos = [];
                foreach ($this->product_images as $image) {
                    if ($image->type === 'image' && $image->variant_option_id && in_array((int)$image->variant_option_id, $attributeValueIds)) {
                        $linkedPhotos[] = $image->image_path;
                    }
                }

                $regionalPrices = [];
                foreach ($variant->prices as $vPrice) {
                    $regionalPrices[] = [
                        'region_id' => $vPrice->region_id,
                        'price' => (float)$vPrice->price,
                        'compare_at_price' => $vPrice->compare_at_price ? (float)$vPrice->compare_at_price : null,
                        'currency_code' => $vPrice->region->currency_code ?? null,
                        'currency_symbol' => $vPrice->region->currency_symbol ?? null,
                    ];
                }

                // Per-variant processing time only applies when the listing says it
                // varies; otherwise every variant inherits the product's profile.
                $processingDays = $this->processing_time_varies
                    ? $variant->processing_days
                    : $this->processingProfile?->max_days;

                $variantsData[] = [
                    'id' => $variant->id,
                    'sku' => $variant->sku,
                    'stock_quantity' => $variant->stock_quantity,
                    'processing_days' => $processingDays,
                    'attribute_value_ids' => $attributeValueIds,
                    'price' => [
                        'amount' => $priceInfo['price'],
                        'currency_symbol' => $priceInfo['currency_symbol'],
                    ],
                    'prices' => $regionalPrices,
                    'linked_photos' => $linkedPhotos,
                    'variant_images' => $variant->variant_images ?? [],
                ];
            }
            $data['variants'] = $variantsData;
        }

        return $data;
    }
}
