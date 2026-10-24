<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Models\WishlistItem;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Validation\ValidationException;

/**
 * Rules for saving products to a customer's wishlist.
 *
 * A wishlist entry is a product, optionally narrowed to one variant. Adding an
 * entry that is already saved is a no-op (idempotent), so the client can fire
 * "add" from a heart icon without checking first. Shoppers who are not logged in
 * keep their list on the device and send it to sync() once they log in.
 */
class WishlistService
{
    /** Upper bound per customer, to keep reads cheap and stop abuse. */
    public const MAX_ITEMS = 200;

    /** Most entries a single sync call may carry. */
    public const MAX_SYNC_ITEMS = 100;

    /**
     * Save a product (or one of its variants) to the user's wishlist.
     *
     * @return array{0: WishlistItem, 1: bool} the entry and whether it was newly created
     *
     * @throws ValidationException when the product/variant cannot be saved or the wishlist is full
     */
    public function add(User $user, int $productId, ?int $variantId = null): array
    {
        $product = Product::find($productId);

        if (!$product) {
            throw ValidationException::withMessages(['product_id' => 'The selected product does not exist.']);
        }

        $variant = $this->resolveVariant($product, $variantId);

        return $this->store($user, $product, $variant);
    }

    /**
     * Merge a list kept on the device (e.g. by a guest) into the user's wishlist.
     * Entries that can no longer be saved - deleted or inactive products, variants
     * that do not belong to the product - are skipped rather than failing the whole
     * call, since a local list can easily be stale.
     *
     * @param array<int, array{product_id: int, product_variant_id?: int|null}> $entries
     * @return array{added: int, already_saved: int, skipped: int}
     */
    public function sync(User $user, array $entries): array
    {
        $result = ['added' => 0, 'already_saved' => 0, 'skipped' => 0];

        $products = Product::whereIn('id', collect($entries)->pluck('product_id')->unique())
            ->get()
            ->keyBy('id');

        foreach ($entries as $entry) {
            $product = $products->get((int) $entry['product_id']);

            try {
                if (!$product) {
                    throw ValidationException::withMessages(['product_id' => 'Unknown product.']);
                }

                $variant = $this->resolveVariant($product, $entry['product_variant_id'] ?? null);
                [, $created] = $this->store($user, $product, $variant);

                $result[$created ? 'added' : 'already_saved']++;
            } catch (ValidationException) {
                $result['skipped']++;
            }
        }

        return $result;
    }

    /**
     * @return array{0: WishlistItem, 1: bool}
     */
    protected function store(User $user, Product $product, ?ProductVariant $variant): array
    {
        $key = [
            'user_id' => $user->id,
            'product_id' => $product->id,
            'variant_key' => $variant?->id ?? 0,
        ];

        if ($existing = WishlistItem::where($key)->first()) {
            return [$existing, false];
        }

        if (WishlistItem::where('user_id', $user->id)->count() >= self::MAX_ITEMS) {
            throw ValidationException::withMessages([
                'product_id' => 'Your wishlist is full. Remove an item to save a new one (limit ' . self::MAX_ITEMS . ').',
            ]);
        }

        try {
            $item = WishlistItem::create([
                'user_id' => $user->id,
                'product_id' => $product->id,
                'product_variant_id' => $variant?->id,
            ]);

            return [$item, true];
        } catch (UniqueConstraintViolationException) {
            // A concurrent request saved the same entry first: treat it as already saved.
            return [WishlistItem::where($key)->firstOrFail(), false];
        }
    }

    /**
     * A wishlist entry does not need a variant even for variant products (the
     * shopper may not have picked a size yet), but a given variant must be an
     * active variant of that product.
     */
    protected function resolveVariant(Product $product, $variantId): ?ProductVariant
    {
        if ($product->status !== 'active') {
            throw ValidationException::withMessages([
                'product_id' => "Product {$product->name} is not available.",
            ]);
        }

        if (!$variantId) {
            return null;
        }

        if (!$product->has_variants) {
            throw ValidationException::withMessages([
                'product_variant_id' => 'This product has no variants.',
            ]);
        }

        $variant = $product->variants()->where('id', $variantId)->where('is_active', true)->first();

        if (!$variant) {
            throw ValidationException::withMessages([
                'product_variant_id' => 'The selected variant is not available for this product.',
            ]);
        }

        return $variant;
    }
}
