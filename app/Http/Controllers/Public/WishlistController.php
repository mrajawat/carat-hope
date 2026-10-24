<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Region;
use App\Models\WishlistItem;
use App\Services\RegionDetectionService;
use App\Services\VariantPricingService;
use App\Services\WishlistService;
use Illuminate\Http\Request;

/**
 * The logged-in customer's wishlist. Every query is scoped to the authenticated
 * user, so one customer can never read or remove another's entries.
 *
 * Guests keep a wishlist on the device and push it with POST /wishlist/sync
 * after logging in.
 */
class WishlistController extends Controller
{
    public function __construct(protected WishlistService $wishlist)
    {
    }

    /**
     * Paginated wishlist, newest first, priced for the shopper's region.
     */
    public function index(Request $request)
    {
        $request->validate([
            'per_page' => 'nullable|integer|min:1|max:50',
            'page' => 'nullable|integer|min:1',
        ]);

        $region = app(RegionDetectionService::class)->detect($request);

        $page = WishlistItem::where('user_id', $request->user()->id)
            ->with([
                'product.product_images',
                'product.prices',
                'product.variants',
                'variant.attributeValues.attribute',
            ])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate((int) $request->input('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => [
                'items' => collect($page->items())->map(fn (WishlistItem $item) => $this->present($item, $region))->values(),
                'currency_code' => $region->currency_code,
                'currency_symbol' => $region->currency_symbol,
                'pagination' => [
                    'current_page' => $page->currentPage(),
                    'per_page' => $page->perPage(),
                    'total' => $page->total(),
                    'last_page' => $page->lastPage(),
                ],
            ],
        ]);
    }

    /**
     * Lightweight list of saved product ids, for highlighting heart icons on
     * listing pages without loading the full wishlist.
     */
    public function productIds(Request $request)
    {
        $ids = WishlistItem::where('user_id', $request->user()->id)
            ->distinct()
            ->orderBy('product_id')
            ->pluck('product_id');

        return response()->json([
            'success' => true,
            'data' => [
                'product_ids' => $ids,
                'count' => $ids->count(),
            ],
        ]);
    }

    /**
     * Save a product. Idempotent: 201 when newly saved, 200 when it already was.
     */
    public function store(Request $request)
    {
        $request->validate([
            'product_id' => 'required|integer',
            'product_variant_id' => 'nullable|integer',
        ]);

        [$item, $created] = $this->wishlist->add(
            $request->user(),
            (int) $request->product_id,
            $request->filled('product_variant_id') ? (int) $request->product_variant_id : null
        );

        $item->load(['product.product_images', 'product.prices', 'product.variants', 'variant.attributeValues.attribute']);
        $region = app(RegionDetectionService::class)->detect($request);

        return response()->json([
            'success' => true,
            'message' => $created ? 'Added to wishlist.' : 'Already in your wishlist.',
            'data' => $this->present($item, $region),
        ], $created ? 201 : 200);
    }

    public function destroy(Request $request, $id)
    {
        $deleted = WishlistItem::where('user_id', $request->user()->id)
            ->where('id', $id)
            ->delete();

        if (!$deleted) {
            return response()->json([
                'success' => false,
                'message' => 'Wishlist item not found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Removed from wishlist.',
        ]);
    }

    /**
     * Remove a product (every saved variant of it) - the "un-heart" action on a
     * listing or product page, where the client knows the product but not the entry id.
     */
    public function destroyProduct(Request $request, $productId)
    {
        $deleted = WishlistItem::where('user_id', $request->user()->id)
            ->where('product_id', $productId)
            ->delete();

        if (!$deleted) {
            return response()->json([
                'success' => false,
                'message' => 'This product is not in your wishlist.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Removed from wishlist.',
            'data' => ['removed' => $deleted],
        ]);
    }

    public function clear(Request $request)
    {
        $deleted = WishlistItem::where('user_id', $request->user()->id)->delete();

        return response()->json([
            'success' => true,
            'message' => 'Wishlist cleared.',
            'data' => ['removed' => $deleted],
        ]);
    }

    /**
     * Merge a device-side wishlist (kept while browsing as a guest) into the
     * account. Stale entries are skipped and counted, not rejected.
     */
    public function sync(Request $request)
    {
        $request->validate([
            'items' => 'required|array|min:1|max:' . WishlistService::MAX_SYNC_ITEMS,
            'items.*.product_id' => 'required|integer',
            'items.*.product_variant_id' => 'nullable|integer',
        ]);

        $result = $this->wishlist->sync($request->user(), $request->input('items'));

        return response()->json([
            'success' => true,
            'message' => $result['added'] > 0 ? 'Wishlist synced.' : 'Nothing new to add.',
            'data' => $result,
        ]);
    }

    protected function present(WishlistItem $item, Region $region): array
    {
        $pricing = app(VariantPricingService::class);
        $product = $item->product;
        $variant = $item->variant;
        $priceIsFrom = false;

        if ($variant) {
            $variant->setRelation('product', $product);
            $priceData = $pricing->getPriceForRegion($variant, $region->id);
        } elseif ($product->has_variants && ($cheapest = $this->cheapestVariantPrice($product, $region))) {
            // No variant picked yet: show the "from" price of the cheapest active variant.
            $priceData = $cheapest;
            $priceIsFrom = true;
        } else {
            $priceData = $pricing->getProductPriceForRegion($product, $region->id);
        }

        $stock = $variant && $product->quantities_vary
            ? (int) $variant->stock_quantity
            : (int) $product->stock_qty;
        $isActive = $product->status === 'active' && (!$variant || $variant->is_active);

        $variantImage = $variant?->variant_images[0] ?? null;
        $image = (is_string($variantImage) ? $variantImage : null)
            ?? ($product->product_images->firstWhere('is_primary', true) ?? $product->product_images->first())?->image_path;

        return [
            'id' => $item->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_image' => $image,
            'product_variant_id' => $variant?->id,
            'variant_sku' => $variant?->sku,
            'variant_attributes' => $variant
                ? $variant->attributeValues->map(fn ($value) => [
                    'attribute' => $value->attribute?->name,
                    'value' => $value->value,
                ])->values()
                : [],
            'price' => (float) $priceData['price'],
            'compare_at_price' => $priceData['compare_at_price'],
            'price_is_starting_from' => $priceIsFrom,
            'in_stock' => $stock > 0,
            'is_available' => $isActive && $stock > 0,
            'added_at' => $item->created_at?->toIso8601String(),
        ];
    }

    protected function cheapestVariantPrice(Product $product, Region $region): ?array
    {
        $pricing = app(VariantPricingService::class);

        return $product->variants
            ->where('is_active', true)
            ->map(function (ProductVariant $variant) use ($product, $pricing, $region) {
                $variant->setRelation('product', $product);
                return $pricing->getPriceForRegion($variant, $region->id);
            })
            ->sortBy('price')
            ->first();
    }
}
