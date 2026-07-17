<?php

namespace App\Services;

use App\Helpers\ImageHelper;
use App\Helpers\VideoHelper;
use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Support\Facades\DB;
use App\Services\SkuGeneratorService;

class ProductService
{
    /**
     * Store a product with its images and videos.
     *
     * @param array $data
     * @return Product
     * @throws \Exception
     */
    public function store(array $data): Product
    {
        return DB::transaction(function () use ($data) {
            $hasVariants = filter_var($data['has_variants'] ?? false, FILTER_VALIDATE_BOOLEAN);

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
                'processing_time_varies' => filter_var($data['processing_time_varies'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'max_variation_axes' => isset($data['max_variation_axes']) ? (int)$data['max_variation_axes'] : 2,
                'total_stock' => isset($data['total_stock']) ? (int)$data['total_stock'] : 0,
            ];

            if (!$hasVariants) {
                // Populate simple product pricing and stock fields
                $productData['sku'] = $data['sku'] ?? null;
                $productData['price'] = $data['price'];
                $productData['discount_price'] = $data['discount_price'] ?? null;
                $productData['stock_qty'] = $data['stock_qty'];
            } else {
                // Variant product: leave null
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

            // 5. Handle images upload
            if (!empty($data['images']) && is_array($data['images'])) {
                foreach ($data['images'] as $index => $imageData) {
                    try {
                        $imageUrl = ImageHelper::uploadBase64($imageData, 'products');
                        ProductImage::create([
                            'product_id' => $product->id,
                            'image_path' => $imageUrl,
                            'type' => 'image',
                            'is_primary' => $index === 0, // First image is primary
                        ]);
                    } catch (\Exception $e) {
                        throw new \Exception('Image upload failed: ' . $e->getMessage());
                    }
                }
            }

            return $product;
        });
    }
}
