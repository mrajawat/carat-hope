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
use Illuminate\Validation\ValidationException;

class ProductController extends Controller
{
    private const VARIANT_PRICE_REQUIRED_MESSAGE = 'Each variant requires at least one regional price.';


    public function index(Request $request, RegionDetectionService $regionDetectionService)
    {
        $region = $regionDetectionService->detect($request);
        ProductListResource::$regionId = $region->id;

        $query = Product::with([
            'category',
            'product_images',
            'variants' => fn ($q) => $q->where('is_active', true),
            'variants.prices' => fn ($q) => $q->where('region_id', $region->id),
            'prices' => fn ($q) => $q->where('region_id', $region->id),
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
            'prices.region',
            'processingProfile',
            'shippingProfile',
            'customOptions',
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

        return response()->json([
            'status' => true,
            'success' => true,
            'message' => 'Product created successfully',
            'data' => $product
        ], 201);
    }

    public function preview(StoreProductRequest $request, ProductService $productService)
    {
        $previewData = $productService->preview($request->validated());

        return response()->json([
            'status' => true,
            'success' => true,
            'message' => 'Product preview generated successfully',
            'data' => $previewData
        ]);
    }

    public function update(Request $request, $id, ProductService $productService)
    {
        $product = Product::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string',
            'sku' => 'nullable|string|unique:products,sku,' . $id,
            'category_id' => 'required|exists:categories,id',
            'local_prices' => 'nullable|array',
            'stock_qty' => 'nullable|integer|min:0',
            'description' => 'nullable|string',
            'video' => 'nullable',
            'images' => 'nullable|array|max:22',
            'images.*' => 'required',
            'has_variants' => 'nullable|boolean',
            'prices_vary' => 'nullable|boolean',
            'quantities_vary' => 'nullable|boolean',
            'skus_vary' => 'nullable|boolean',
            'processing_time_varies' => 'nullable|boolean',
            'processing_profiles_vary' => 'nullable|boolean',
            'max_variation_axes' => 'nullable|integer|min:1|max:' . (int) config('jewelry.max_variation_axes', 2),
            'processing_profile_id' => 'nullable|exists:processing_profiles,id',
            'shipping_profile_id' => 'nullable|exists:shipping_profiles,id',
            'max_offer_discount_percent' => 'nullable|integer|min:1|max:100',
            'attributes' => 'nullable|array',
            'variants' => 'nullable|array',
            'variations' => 'nullable|array',
            'prices' => 'nullable|array|min:1',
            'prices.*.region_id' => 'required_with:prices|exists:regions,id',
            'prices.*.price' => 'required_with:prices|numeric|min:0',
            'prices.*.compare_at_price' => 'nullable|numeric|min:0',
            'variants.*.prices' => 'nullable|array|min:1',
            'variants.*.prices.*.region_id' => 'required_with:variants.*.prices|exists:regions,id',
            'variants.*.prices.*.price' => 'required_with:variants.*.prices|numeric|min:0',
            'variations.*.prices' => 'nullable|array|min:1',
            'variations.*.prices.*.region_id' => 'required_with:variations.*.prices|exists:regions,id',
            'variations.*.prices.*.price' => 'required_with:variations.*.prices|numeric|min:0',
        ], [
            'prices.min' => 'At least one regional price is required.',
            'variants.*.prices.min' => 'Each variant requires at least one regional price.',
            'variations.*.prices.min' => 'Each variant requires at least one regional price.',
        ]);

        $hasVariants = filter_var(
            $validated['has_variants'] ?? ($product->has_variants || !empty($validated['variants']) || !empty($validated['variations']) || !empty($validated['attributes'])),
            FILTER_VALIDATE_BOOLEAN
        );

        $this->validateRegionalPricingRequirement($request, $product, $hasVariants);

        return DB::transaction(function () use ($request, $validated, $product, $productService, $hasVariants) {

            $skusVary = filter_var($request->skus_vary ?? true, FILTER_VALIDATE_BOOLEAN);

            // Simple products, and variant products where every variant shares one SKU,
            // need a resolved SKU here; variant products with skus_vary=true generate
            // per-variant SKUs later in storeOrUpdateVariants().
            $sku = $request->sku;
            if ((!$hasVariants || !$skusVary) && empty($sku)) {
                $skuGenerator = app(\App\Services\SkuGeneratorService::class);
                $sku = $skuGenerator->generateForProduct($product);
            }

            $tags = $request->has('tags') ? $request->tags : $product->tags;
            if (is_string($tags)) {
                $tags = array_map('trim', explode(',', $tags));
            }

            $materials = $request->has('materials') ? $request->materials : $product->materials;
            if (is_string($materials)) {
                $materials = array_map('trim', explode(',', $materials));
            }

            $goldSolidity = $request->has('gold_solidity') ? $request->gold_solidity : $product->gold_solidity;
            if (is_string($goldSolidity)) {
                $goldSolidity = array_map('trim', explode(',', $goldSolidity));
            }

            $goldPurity = $request->has('gold_purity') ? $request->gold_purity : $product->gold_purity;
            if (is_string($goldPurity)) {
                $goldPurity = array_map('trim', explode(',', $goldPurity));
            }

            $listingAttributes = $product->listing_attributes ?? [];
            if ($request->has('listing_attributes') || $request->has('item_attributes')) {
                $inputAttrs = $request->listing_attributes ?? $request->item_attributes;
                if (is_array($inputAttrs)) {
                    $listingAttributes = array_merge($listingAttributes, $inputAttrs);
                }
            }

            $specKeys = [
                'primary_colour', 'primary_color', 'secondary_colour', 'secondary_color',
                'pendant_width', 'pendant_height', 'necklace_length',
                'recycled', 'is_recycled', 'spinner', 'is_spinner',
                'gem_colour', 'gem_color', 'stone_source', 'shape', 'cut_type',
                'sustainability', 'style', 'occasion', 'celebration', 'recipient', 'theme'
            ];

            foreach ($specKeys as $key) {
                if ($request->has($key)) {
                    $listingAttributes[$key] = $request->input($key);
                }
            }

            // Derive the legacy flat price/discount_price columns from the mandatory
            // regional prices (raw columns act only as a legacy fallback)
            $derivedPricing = $hasVariants
                ? ['price' => null, 'discount_price' => null]
                : $productService->getDefaultRegionPricing($validated['prices'] ?? []);

            $product->update([
                'name' => $request->name,
                'sku' => ($hasVariants && $skusVary) ? null : $sku,
                'category_id' => $request->category_id,
                'price' => $derivedPricing['price'],
                'discount_price' => $derivedPricing['discount_price'],
                'local_prices' => $request->local_prices,
                'stock_qty' => $hasVariants ? null : $request->stock_qty,
                'description' => $request->description,
                'has_variants' => $hasVariants,
                'prices_vary' => filter_var($request->prices_vary ?? true, FILTER_VALIDATE_BOOLEAN),
                'quantities_vary' => filter_var($request->quantities_vary ?? true, FILTER_VALIDATE_BOOLEAN),
                'max_variation_axes' => $request->has('max_variation_axes')
                    ? (int) $request->max_variation_axes
                    : $product->max_variation_axes,
                'skus_vary' => $skusVary,
                'processing_time_varies' => filter_var($request->processing_time_varies ?? $request->processing_profiles_vary ?? false, FILTER_VALIDATE_BOOLEAN),
                'tags' => $tags,
                'materials' => $materials,
                'gold_solidity' => $goldSolidity,
                'gold_purity' => $goldPurity,
                'listing_attributes' => !empty($listingAttributes) ? $listingAttributes : null,
                'is_global_pricing_enabled' => $request->has('is_global_pricing_enabled') || $request->has('domestic_and_global_pricing')
                    ? filter_var($request->is_global_pricing_enabled ?? $request->domestic_and_global_pricing, FILTER_VALIDATE_BOOLEAN)
                    : ($product->is_global_pricing_enabled ?? true),
                'max_offer_discount_percent' => $request->has('max_offer_discount_percent')
                    ? $request->max_offer_discount_percent
                    : $product->max_offer_discount_percent,
                'allow_offers' => $request->has('allow_offers') || $request->has('allow_buyer_offers')
                    ? filter_var($request->allow_offers ?? $request->allow_buyer_offers, FILTER_VALIDATE_BOOLEAN)
                    : ($product->allow_offers ?? false),
                'processing_profile_id' => $request->has('processing_profile_id')
                    ? $request->processing_profile_id
                    : $product->processing_profile_id,
                'shipping_profile_id' => $request->has('shipping_profile_id')
                    ? $request->shipping_profile_id
                    : $product->shipping_profile_id,
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
                // Remove old product images and videos uploaded via images array
                $product->product_images()->delete();

                $photoIndex = 0;
                foreach ($request->images as $index => $itemData) {
                    if (\App\Helpers\VideoHelper::isVideoInput($itemData)) {
                        try {
                            $videoUrl = \App\Helpers\VideoHelper::upload($itemData, 'products/videos');
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
                                'is_primary' => $photoIndex === 0 // First photo in array is main product image
                            ]);
                            $photoIndex++;
                        } catch (\Exception $e) {
                            throw new \Exception('Image upload failed: ' . $e->getMessage());
                        }
                    }
                }
            }

            if ($hasVariants || $request->has('variants') || $request->has('variations') || $request->has('attributes')) {
                $productService->storeOrUpdateVariants($product, $request->all());
            }

            // Switching processing time back to a single profile clears the
            // per-variant values, so they cannot resurface if it is re-enabled.
            if (!$product->processing_time_varies) {
                $product->variants()->whereNotNull('processing_days')->update(['processing_days' => null]);
            }

            if ((!$hasVariants || !$product->prices_vary) && $request->has('prices')) {
                $productService->saveProductPrices($product, $request->prices);
            }

            return response()->json([
                'success' => true,
                'message' => 'Product updated successfully',
                'data' => $product->load([
                    'product_images',
                    'variants.attributeValues.attribute',
                    'variants.prices.region',
                    'prices.region',
                    'processingProfile',
                    'shippingProfile',
                ])
            ]);
        });
    }

    /**
     * Regional pricing is always mandatory, but *where* it must be provided depends
     * on prices_vary: a simple product, or a variant product where prices don't vary,
     * needs exactly one shared 'prices' array; a variant product where prices do vary
     * needs a 'prices' array on every individual variant.
     */
    private function validateRegionalPricingRequirement(Request $request, Product $product, bool $hasVariants): void
    {
        $pricesVary = $request->has('prices_vary')
            ? filter_var($request->prices_vary, FILTER_VALIDATE_BOOLEAN)
            : ($product->prices_vary ?? true);

        if (!$hasVariants || !$pricesVary) {
            if (empty($request->prices) || !is_array($request->prices)) {
                throw ValidationException::withMessages([
                    'prices' => 'At least one regional price is required.',
                ]);
            }
            return;
        }

        $variantsKey = 'variants';
        if (!$request->has('variants') && $request->has('variations')) {
            $variantsKey = 'variations';
        } elseif (!$request->has('variants') && !$request->has('variations')) {
            return;
        }

        $errors = [];
        foreach ((array) $request->input($variantsKey, []) as $idx => $variant) {
            if (empty($variant['prices']) || !is_array($variant['prices'])) {
                $errors["{$variantsKey}.{$idx}.prices"] = self::VARIANT_PRICE_REQUIRED_MESSAGE;
            }
        }

        if (!empty($errors)) {
            throw ValidationException::withMessages($errors);
        }
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
