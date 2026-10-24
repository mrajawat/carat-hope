<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\ProductVariant;
use App\Models\Region;
use App\Models\User;
use App\Models\VariantPrice;
use App\Models\WishlistItem;
use App\Services\WishlistService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WishlistTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected User $otherUser;
    protected Region $india;
    protected Category $category;
    protected Product $ring;

    protected function setUp(): void
    {
        parent::setUp();

        $this->india = Region::create([
            'name' => 'India',
            'currency_code' => 'INR',
            'currency_symbol' => '₹',
            'is_default' => true,
        ]);

        $this->category = Category::create(['name' => 'Rings', 'slug' => 'rings', 'status' => 'active']);

        $this->ring = $this->makeProduct('Fixed Ring', 'CH-RNG-FIXED');
        ProductPrice::create(['product_id' => $this->ring->id, 'region_id' => $this->india->id, 'price' => 4500.00, 'compare_at_price' => 5000.00]);

        $this->user = User::factory()->create(['phone' => fake()->unique()->numerify('98########')]);
        $this->otherUser = User::factory()->create(['phone' => fake()->unique()->numerify('97########')]);
        Sanctum::actingAs($this->user);
    }

    protected function makeProduct(string $name, string $sku, array $overrides = []): Product
    {
        return Product::create(array_merge([
            'name' => $name,
            'sku' => $sku,
            'category_id' => $this->category->id,
            'price' => 5000.00,
            'stock_qty' => 5,
        ], $overrides));
    }

    protected function makeVariantProduct(): array
    {
        $product = $this->makeProduct('Varying Ring', 'CH-RNG-VARY', [
            'price' => 10000.00,
            'has_variants' => true,
            'prices_vary' => true,
            'quantities_vary' => true,
        ]);
        $small = ProductVariant::create(['product_id' => $product->id, 'sku' => 'V-S', 'weight_grams' => 2, 'stock_quantity' => 3]);
        $large = ProductVariant::create(['product_id' => $product->id, 'sku' => 'V-L', 'weight_grams' => 4, 'stock_quantity' => 0]);
        VariantPrice::create(['product_variant_id' => $small->id, 'region_id' => $this->india->id, 'price' => 9000.00]);
        VariantPrice::create(['product_variant_id' => $large->id, 'region_id' => $this->india->id, 'price' => 15000.00]);

        return [$product, $small, $large];
    }

    protected function saveFor(User $user, Product $product, ?ProductVariant $variant = null): WishlistItem
    {
        return WishlistItem::create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'product_variant_id' => $variant?->id,
        ]);
    }

    // ---- Authentication -------------------------------------------------

    public function test_every_wishlist_endpoint_requires_authentication(): void
    {
        $this->app['auth']->forgetGuards();
        $guest = $this->withHeaders(['Authorization' => '']);

        $guest->getJson('/api/public/wishlist')->assertUnauthorized();
        $guest->getJson('/api/public/wishlist/product-ids')->assertUnauthorized();
        $guest->postJson('/api/public/wishlist/items', ['product_id' => $this->ring->id])->assertUnauthorized();
        $guest->postJson('/api/public/wishlist/sync', ['items' => [['product_id' => $this->ring->id]]])->assertUnauthorized();
        $guest->deleteJson('/api/public/wishlist/items/1')->assertUnauthorized();
        $guest->deleteJson("/api/public/wishlist/products/{$this->ring->id}")->assertUnauthorized();
        $guest->deleteJson('/api/public/wishlist')->assertUnauthorized();

        $this->assertSame(0, WishlistItem::count());
    }

    // ---- Add ------------------------------------------------------------

    public function test_add_product_returns_201_with_regional_price(): void
    {
        $this->postJson('/api/public/wishlist/items', ['product_id' => $this->ring->id])
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Added to wishlist.')
            ->assertJsonPath('data.product_id', $this->ring->id)
            ->assertJsonPath('data.product_name', 'Fixed Ring')
            ->assertJsonPath('data.product_variant_id', null)
            ->assertJsonPath('data.price', 4500)
            ->assertJsonPath('data.compare_at_price', 5000)
            ->assertJsonPath('data.price_is_starting_from', false)
            ->assertJsonPath('data.is_available', true)
            ->assertJsonMissingPath('data.variant_key');

        $this->assertDatabaseHas('wishlist_items', ['user_id' => $this->user->id, 'product_id' => $this->ring->id]);
    }

    public function test_adding_the_same_product_twice_is_idempotent(): void
    {
        $id = $this->postJson('/api/public/wishlist/items', ['product_id' => $this->ring->id])
            ->assertCreated()
            ->json('data.id');

        $this->postJson('/api/public/wishlist/items', ['product_id' => $this->ring->id])
            ->assertOk()
            ->assertJsonPath('message', 'Already in your wishlist.')
            ->assertJsonPath('data.id', $id);

        $this->assertSame(1, WishlistItem::count());
    }

    public function test_add_validates_input(): void
    {
        $this->postJson('/api/public/wishlist/items', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('product_id');

        $this->postJson('/api/public/wishlist/items', ['product_id' => 'abc'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('product_id');

        $this->postJson('/api/public/wishlist/items', ['product_id' => 999999])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('product_id');
    }

    public function test_inactive_product_cannot_be_added(): void
    {
        $this->ring->update(['status' => 'inactive']);

        $this->postJson('/api/public/wishlist/items', ['product_id' => $this->ring->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('product_id');
    }

    public function test_variant_must_belong_to_the_product(): void
    {
        [$product, $small] = $this->makeVariantProduct();
        $foreign = ProductVariant::create(['product_id' => $this->ring->id, 'sku' => 'V-X', 'weight_grams' => 3, 'stock_quantity' => 2]);

        $this->postJson('/api/public/wishlist/items', ['product_id' => $product->id, 'product_variant_id' => $foreign->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('product_variant_id');

        $this->postJson('/api/public/wishlist/items', ['product_id' => $this->ring->id, 'product_variant_id' => $small->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('product_variant_id');
    }

    public function test_specific_variant_is_saved_and_priced(): void
    {
        [$product, $small] = $this->makeVariantProduct();

        $this->postJson('/api/public/wishlist/items', ['product_id' => $product->id, 'product_variant_id' => $small->id])
            ->assertCreated()
            ->assertJsonPath('data.product_variant_id', $small->id)
            ->assertJsonPath('data.variant_sku', 'V-S')
            ->assertJsonPath('data.price', 9000)
            ->assertJsonPath('data.price_is_starting_from', false)
            ->assertJsonPath('data.in_stock', true);
    }

    public function test_variant_product_without_a_variant_shows_starting_price(): void
    {
        [$product] = $this->makeVariantProduct();

        $this->postJson('/api/public/wishlist/items', ['product_id' => $product->id])
            ->assertCreated()
            ->assertJsonPath('data.product_variant_id', null)
            ->assertJsonPath('data.price', 9000)
            ->assertJsonPath('data.price_is_starting_from', true);
    }

    public function test_product_level_and_variant_entries_are_separate(): void
    {
        [$product, $small, $large] = $this->makeVariantProduct();

        $this->postJson('/api/public/wishlist/items', ['product_id' => $product->id])->assertCreated();
        $this->postJson('/api/public/wishlist/items', ['product_id' => $product->id, 'product_variant_id' => $small->id])->assertCreated();
        $this->postJson('/api/public/wishlist/items', ['product_id' => $product->id, 'product_variant_id' => $large->id])
            ->assertCreated()
            ->assertJsonPath('data.in_stock', false)
            ->assertJsonPath('data.is_available', false);

        $this->assertSame(3, WishlistItem::where('user_id', $this->user->id)->count());
    }

    public function test_full_wishlist_returns_422(): void
    {
        $now = now();
        $rows = collect(range(1, WishlistService::MAX_ITEMS))->map(fn ($i) => [
            'user_id' => $this->user->id,
            'product_id' => $this->makeProduct("Ring {$i}", "CH-BULK-{$i}")->id,
            'variant_key' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        WishlistItem::insert($rows->all());

        $this->postJson('/api/public/wishlist/items', ['product_id' => $this->ring->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('product_id');
    }

    // ---- List -----------------------------------------------------------

    public function test_empty_wishlist(): void
    {
        $this->getJson('/api/public/wishlist')
            ->assertOk()
            ->assertJsonPath('data.items', [])
            ->assertJsonPath('data.currency_code', 'INR')
            ->assertJsonPath('data.pagination.total', 0);
    }

    public function test_list_shows_only_own_items_newest_first(): void
    {
        $necklace = $this->makeProduct('Necklace', 'CH-NCK-1');

        $older = $this->saveFor($this->user, $this->ring);
        $older->forceFill(['created_at' => now()->subDay()])->save();
        $this->saveFor($this->user, $necklace);
        $this->saveFor($this->otherUser, $this->makeProduct('Bangle', 'CH-BNG-1'));

        $this->getJson('/api/public/wishlist')
            ->assertOk()
            ->assertJsonCount(2, 'data.items')
            ->assertJsonPath('data.items.0.product_name', 'Necklace')
            ->assertJsonPath('data.items.1.product_name', 'Fixed Ring')
            ->assertJsonPath('data.pagination.total', 2);
    }

    public function test_list_is_paginated(): void
    {
        foreach (range(1, 5) as $i) {
            $this->saveFor($this->user, $this->makeProduct("Ring {$i}", "CH-PG-{$i}"));
        }

        $this->getJson('/api/public/wishlist?per_page=2&page=3')
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.pagination.current_page', 3)
            ->assertJsonPath('data.pagination.per_page', 2)
            ->assertJsonPath('data.pagination.total', 5)
            ->assertJsonPath('data.pagination.last_page', 3);

        $this->getJson('/api/public/wishlist?per_page=51')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('per_page');
    }

    public function test_items_that_became_unavailable_stay_listed_but_flagged(): void
    {
        $this->saveFor($this->user, $this->ring);
        $this->ring->update(['status' => 'inactive']);

        $this->getJson('/api/public/wishlist')
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.is_available', false);
    }

    public function test_product_ids_lists_distinct_saved_products(): void
    {
        [$product, $small] = $this->makeVariantProduct();
        $this->saveFor($this->user, $this->ring);
        $this->saveFor($this->user, $product);
        $this->saveFor($this->user, $product, $small);
        $this->saveFor($this->otherUser, $this->makeProduct('Bangle', 'CH-BNG-1'));

        $response = $this->getJson('/api/public/wishlist/product-ids')
            ->assertOk()
            ->assertJsonPath('data.count', 2);

        $this->assertEqualsCanonicalizing([$this->ring->id, $product->id], $response->json('data.product_ids'));
    }

    // ---- Remove ---------------------------------------------------------

    public function test_remove_own_item(): void
    {
        $item = $this->saveFor($this->user, $this->ring);

        $this->deleteJson("/api/public/wishlist/items/{$item->id}")
            ->assertOk()
            ->assertJsonPath('message', 'Removed from wishlist.');

        $this->assertModelMissing($item);
    }

    public function test_cannot_remove_another_customers_item(): void
    {
        $theirs = $this->saveFor($this->otherUser, $this->ring);

        $this->deleteJson("/api/public/wishlist/items/{$theirs->id}")
            ->assertNotFound()
            ->assertJsonPath('success', false);

        $this->assertModelExists($theirs);
    }

    public function test_remove_by_product_removes_every_saved_variant(): void
    {
        [$product, $small] = $this->makeVariantProduct();
        $this->saveFor($this->user, $product);
        $this->saveFor($this->user, $product, $small);
        $keep = $this->saveFor($this->user, $this->ring);
        $theirs = $this->saveFor($this->otherUser, $product);

        $this->deleteJson("/api/public/wishlist/products/{$product->id}")
            ->assertOk()
            ->assertJsonPath('data.removed', 2);

        $this->assertModelExists($keep);
        $this->assertModelExists($theirs);

        $this->deleteJson("/api/public/wishlist/products/{$product->id}")->assertNotFound();
    }

    public function test_clear_only_clears_own_wishlist(): void
    {
        $this->saveFor($this->user, $this->ring);
        $this->saveFor($this->user, $this->makeProduct('Necklace', 'CH-NCK-1'));
        $theirs = $this->saveFor($this->otherUser, $this->ring);

        $this->deleteJson('/api/public/wishlist')
            ->assertOk()
            ->assertJsonPath('data.removed', 2);

        $this->assertSame(0, WishlistItem::where('user_id', $this->user->id)->count());
        $this->assertModelExists($theirs);
    }

    // ---- Sync -----------------------------------------------------------

    public function test_sync_merges_device_wishlist(): void
    {
        [$product, $small] = $this->makeVariantProduct();
        $this->saveFor($this->user, $this->ring);

        $this->postJson('/api/public/wishlist/sync', [
            'items' => [
                ['product_id' => $this->ring->id],
                ['product_id' => $product->id, 'product_variant_id' => $small->id],
                ['product_id' => 999999],
            ],
        ])
            ->assertOk()
            ->assertJsonPath('message', 'Wishlist synced.')
            ->assertJsonPath('data.added', 1)
            ->assertJsonPath('data.already_saved', 1)
            ->assertJsonPath('data.skipped', 1);

        $this->assertSame(2, WishlistItem::where('user_id', $this->user->id)->count());
    }

    public function test_sync_validates_payload(): void
    {
        $this->postJson('/api/public/wishlist/sync', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('items');

        $this->postJson('/api/public/wishlist/sync', ['items' => [['product_variant_id' => 1]]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('items.0.product_id');

        $tooMany = array_fill(0, WishlistService::MAX_SYNC_ITEMS + 1, ['product_id' => $this->ring->id]);
        $this->postJson('/api/public/wishlist/sync', ['items' => $tooMany])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('items');
    }
}
