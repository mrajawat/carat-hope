<?php

namespace App\Http\Resources;

use App\Services\RegionDetectionService;
use App\Services\VariantPricingService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductListResource extends JsonResource
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
        $primaryImage = $this->product_images->firstWhere('is_primary', true) ?? $this->product_images->first();

        $data = [
            'id' => $this->id,
            'name' => $this->name,
            'has_variants' => (bool)$this->has_variants,
            'category_id' => $this->category_id,
            'category' => $this->whenLoaded('category'),
            'description' => $this->description,
            'is_featured' => (bool)$this->is_featured,
            'status' => $this->status,
            'images' => $primaryImage?->image_path,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];

        // Conditional review fields for storefront
        if (isset($this->reviews_count)) {
            $data['reviews_count'] = (int)$this->reviews_count;
        }
        if (isset($this->avg_rating)) {
            $data['avg_rating'] = $this->avg_rating !== null ? (float)$this->avg_rating : null;
        }

        if (!$this->has_variants) {
            $data['sku'] = $this->sku;
            $data['price'] = $this->price;
            $data['discount_price'] = $this->discount_price;
            $data['stock_qty'] = $this->stock_qty;
            $data['price_label'] = null;
        } else {
            $data['sku'] = null;
            $data['price'] = $pricingService->getStartingPrice($this->resource, $regionId);
            $data['discount_price'] = null;

            if (!$this->quantities_vary) {
                $data['stock_qty'] = $this->total_stock;
            } else {
                $variants = $this->relationLoaded('variants')
                    ? $this->variants->where('is_active', true)
                    : $this->variants()->where('is_active', true)->get();
                $data['stock_qty'] = (int) $variants->sum('stock_quantity');
            }

            $data['price_label'] = 'Starting from';
        }

        return $data;
    }
}
