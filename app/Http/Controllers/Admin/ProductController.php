<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Helpers\ImageHelper;
use App\Models\Product;
use App\Models\ProductImage;
use App\Http\Requests\StoreProductRequest;
use App\Services\ProductService;
use App\Services\RegionDetectionService;
use App\Http\Resources\ProductListResource;
use App\Http\Resources\ProductDetailResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductController extends Controller
{
    public function index(Request $request, RegionDetectionService $regionDetectionService)
    {
        $region = $regionDetectionService->detect($request);
        ProductListResource::$regionId = $region->id;

        $query = Product::with([
            'category',
            'product_images',
            'variants' => fn ($q) => $q->where('is_active', true),
            'variants.prices' => fn ($q) => $q->where('region_id', $region->id)
        ])->orderBy('created_at', 'desc');

        if ($request->get('paginate') === 'true') {
            $products = $query->paginate($request->get('per_page', 15));
        } else {
            $products = $query->get();
        }

        return ProductListResource::collection($products)->additional([
            'status' => true,
            'success' => true,
            'message' => 'Products retrieved successfully',
        ]);
    }

    public function show($id, Request $request, RegionDetectionService $regionDetectionService)
    {
        $region = $regionDetectionService->detect($request);
        ProductDetailResource::$regionId = $region->id;

        $product = Product::with([
            'category',
            'product_images',
            'variants' => fn ($q) => $q->where('is_active', true),
            'variants.attributeValues',
            'variants.prices' => fn ($q) => $q->where('region_id', $region->id),
        ])->findOrFail($id);

        return (new ProductDetailResource($product))->additional([
            'status' => true,
            'success' => true,
            'message' => 'Product retrieved successfully',
        ]);
    }

    public function store(StoreProductRequest $request, ProductService $productService)
    {
        $product = $productService->store($request->validated());

        $data = $product->load('product_images')->toArray();
        if ($product->has_variants) {
            $data['next_step'] = "Add variants via POST /api/admin/products/{$product->id}/variants/generate-combinations";
        }

        return response()->json([
            'status' => true,
            'success' => true,
            'message' => 'Product created successfully',
            'data' => $data
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $product = Product::findOrFail($id);

        $request->validate([
            'name' => 'required|string',
            'sku' => 'nullable|string|unique:products,sku,' . $id,
            'category_id' => 'required|exists:categories,id',
            'price' => 'required|numeric|min:0',
            'discount_price' => 'nullable|numeric|min:0|lt:price',
            'local_prices' => 'nullable|array',
            'stock_qty' => 'required|integer|min:0',
            'description' => 'nullable|string',
            'video' => 'nullable',
            'images' => 'nullable|array',
            'images.*' => 'required',
        ]);

        return DB::transaction(function () use ($request, $product) {
            $sku = $request->sku;
            if (empty($sku)) {
                $skuGenerator = app(\App\Services\SkuGeneratorService::class);
                $sku = $skuGenerator->generateForProduct($product);
            }

            $product->update([
                'name' => $request->name,
                'sku' => $sku,
                'category_id' => $request->category_id,
                'price' => $request->price,
                'discount_price' => $request->discount_price,
                'local_prices' => $request->local_prices,
                'stock_qty' => $request->stock_qty,
                'description' => $request->description,
            ]);

            if ($request->has('video')) {
                // Delete existing videos
                $product->product_images()->where('type', 'video')->delete();

                if (!empty($request->video)) {
                    try {
                        $videoUrl = \App\Helpers\VideoHelper::upload($request->video, 'products/videos');
                    } catch (\Exception $e) {
                        throw new \Exception('Video upload failed: ' . $e->getMessage());
                    }

                    ProductImage::create([
                        'product_id' => $product->id,
                        'image_path' => $videoUrl,
                        'type' => 'video',
                        'is_primary' => false
                    ]);
                }
            }

            if ($request->has('images') && is_array($request->images)) {
                // Remove old product images records (only of type 'image')
                $product->product_images()->where('type', 'image')->delete();

                foreach ($request->images as $index => $imageData) {
                    try {
                        $imageUrl = ImageHelper::uploadBase64($imageData, 'products');
                    } catch (\Exception $e) {
                        throw new \Exception('Image upload failed: ' . $e->getMessage());
                    }

                    ProductImage::create([
                        'product_id' => $product->id,
                        'image_path' => $imageUrl,
                        'type' => 'image',
                        'is_primary' => $index === 0
                    ]);
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Product updated successfully',
                'data' => $product->load('product_images')
            ]);
        });
    }

    public function destroy($id)
    {
        $product = Product::findOrFail($id);
        $product->delete(); // Product images table has cascade on delete in DB migrations

        return response()->json([
            'success' => true,
            'message' => 'Product deleted successfully'
        ]);
    }

    public function toggleStatus($id)
    {
        $product = Product::findOrFail($id);
        $product->status = $product->status === 'active' ? 'inactive' : 'active';
        $product->save();

        return response()->json([
            'success' => true,
            'message' => 'Product status updated successfully',
            'data' => $product
        ]);
    }

    public function toggleFeatured($id)
    {
        $product = Product::findOrFail($id);
        $product->is_featured = !$product->is_featured;
        $product->save();

        return response()->json([
            'success' => true,
            'message' => 'Product featured status updated successfully',
            'data' => $product
        ]);
    }

    public function bulkToggleFeatured(Request $request)
    {
        $request->validate([
            'product_ids' => 'required|array',
            'product_ids.*' => 'required|exists:products,id',
        ]);

        foreach (Product::whereIn('id', $request->product_ids)->get() as $product) {
            $product->is_featured = !$product->is_featured;
            $product->save();
        }

        return response()->json([
            'success' => true,
            'message' => 'Products featured status toggled successfully.'
        ]);
    }
}
