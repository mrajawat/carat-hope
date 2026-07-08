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
            'category_id' => $this->category_id,
            'category' => $this->whenLoaded('category'),
            'description' => $this->description,
            'is_featured' => (bool)$this->is_featured,
            'status' => $this->status,
            'images' => $images,
            'video' => $video,
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

            foreach ($activeVariants as $variant) {
                $variant->setRelation('product', $this->resource);
                $priceInfo = $pricingService->getPriceForRegion($variant, $regionId);

                if ($variant->relationLoaded('attributeValues')) {
                    $attributeValueIds = $variant->attributeValues->pluck('id')->toArray();
                } else {
                    $attributeValueIds = $variant->variantAttributeValues()->pluck('attribute_value_id')->toArray();
                }

                $variantsData[] = [
                    'id' => $variant->id,
                    'sku' => $variant->sku,
                    'stock_quantity' => $variant->stock_quantity,
                    'attribute_value_ids' => array_map('intval', $attributeValueIds),
                    'price' => [
                        'amount' => $priceInfo['price'],
                        'currency_symbol' => $priceInfo['currency_symbol'],
                    ],
                    'variant_images' => $variant->variant_images ?? [],
                ];
            }
            $data['variants'] = $variantsData;
        }

        return $data;
    }
}
