<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Helpers\ImageHelper;
use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $query = Product::with(['category', 'product_images'])->orderBy('created_at', 'desc');

        if ($request->get('paginate') === 'true') {
            $products = $query->paginate($request->get('per_page', 15));
        } else {
            $products = $query->get();
        }

        return response()->json([
            'success' => true,
            'data' => $products
        ]);
    }

    public function show($id)
    {
        $product = Product::with(['category', 'product_images'])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $product
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string',
            'sku' => 'required|string|unique:products,sku',
            'category_id' => 'required|exists:categories,id',
            'price' => 'required|numeric|min:0',
            'discount_price' => 'nullable|numeric|min:0|lt:price',
            'local_prices' => 'nullable|array',
            'stock_qty' => 'required|integer|min:0',
            'description' => 'nullable|string',
            'video' => 'nullable',
            'images' => 'required|array|min:1',
            'images.*' => 'required|string',
        ]);

        return DB::transaction(function () use ($request) {
            $product = Product::create([
                'name' => $request->name,
                'sku' => $request->sku,
                'category_id' => $request->category_id,
                'price' => $request->price,
                'discount_price' => $request->discount_price,
                'local_prices' => $request->local_prices,
                'stock_qty' => $request->stock_qty,
                'description' => $request->description,
                'is_featured' => false,
                'status' => 'active'
            ]);

            if ($request->has('video') && !empty($request->video)) {
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
                    'is_primary' => $index === 0 // Designate first image as primary
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Product created successfully',
                'data' => $product->load('product_images')
            ], 201);
        });
    }

    public function update(Request $request, $id)
    {
        $product = Product::findOrFail($id);

        $request->validate([
            'name' => 'required|string',
            'sku' => 'required|string|unique:products,sku,' . $id,
            'category_id' => 'required|exists:categories,id',
            'price' => 'required|numeric|min:0',
            'discount_price' => 'nullable|numeric|min:0|lt:price',
            'local_prices' => 'nullable|array',
            'stock_qty' => 'required|integer|min:0',
            'description' => 'nullable|string',
            'video' => 'nullable',
            'images' => 'nullable|array',
            'images.*' => 'required|string',
        ]);

        return DB::transaction(function () use ($request, $product) {
            $product->update([
                'name' => $request->name,
                'sku' => $request->sku,
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
