<?php

namespace App\Http\Resources;

use App\Services\AttributeService;
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
            'materials' => $this->materials,
            'gold_solidity' => $this->gold_solidity,
            'gold_purity' => $this->gold_purity,
            'listing_attributes' => $this->listing_attributes,
            'is_global_pricing_enabled' => (bool)($this->is_global_pricing_enabled ?? true),
            'allow_offers' => (bool)($this->allow_offers ?? false),
            'processing_profile' => $this->processing_profile,
            'delivery_option' => $this->delivery_option,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];

        if (!$this->has_variants) {
            $data['sku'] = $this->sku;
            $data['price'] = $this->price;
            $data['discount_price'] = $this->discount_price;
            $data['stock_qty'] = $this->stock_qty;
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

            // Attributes
            $attributeService = app(AttributeService::class);
            $categoryAttributes = $attributeService->getAttributesForCategory($this->category_id);
            
            $attributesData = [];
            foreach ($categoryAttributes as $attr) {
                $values = [];
                foreach ($attr->values as $val) {
                    $values[] = [
                        'id' => $val->id,
                        'value' => $val->value,
                    ];
                }
                $attributesData[] = [
                    'id' => $attr->id,
                    'name' => $attr->name,
                    'values' => $values,
                ];
            }
            $data['attributes'] = $attributesData;

            // Variants
            $variantsData = [];
            $activeVariants = $this->relationLoaded('variants')
                ? $this->variants->where('is_active', true)
                : $this->variants()->where('is_active', true)->get();

            // Convert to Eloquent Collection if it is a Support Collection to allow loadMissing
            if (!($activeVariants instanceof \Illuminate\Database\Eloquent\Collection)) {
                $activeVariants = new \Illuminate\Database\Eloquent\Collection($activeVariants->all());
            }
            $activeVariants->loadMissing(['prices.region', 'attributeValues']);

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

                $variantsData[] = [
                    'id' => $variant->id,
                    'sku' => $variant->sku,
                    'stock_quantity' => $variant->stock_quantity,
                    'processing_days' => $variant->processing_days,
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
