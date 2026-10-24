<?php

namespace Tests\Unit;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Models\WishlistItem;
use App\Services\WishlistService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class WishlistServiceTest extends TestCase
{
    use RefreshDatabase;

    protected WishlistService $service;
    protected User $user;
    protected Category $category;
    protected Product $ring;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new WishlistService();
        $this->user = User::factory()->create(['phone' => fake()->unique()->numerify('98########')]);
        $this->category = Category::create(['name' => 'Rings', 'slug' => 'rings', 'status' => 'active']);
        $this->ring = $this->makeProduct('Fixed Ring', 'CH-RNG-FIXED');
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

    public function test_add_creates_entry_and_reports_it_as_new(): void
    {
        [$item, $created] = $this->service->add($this->user, $this->ring->id);

        $this->assertTrue($created);
        $this->assertSame($this->user->id, $item->user_id);
        $this->assertSame($this->ring->id, $item->product_id);
        $this->assertNull($item->product_variant_id);
    }

    public function test_add_is_idempotent(): void
    {
        [$first] = $this->service->add($this->user, $this->ring->id);
        [$second, $created] = $this->service->add($this->user, $this->ring->id);

        $this->assertFalse($created);
        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, WishlistItem::count());
    }

    public function test_variant_key_mirrors_variant_id_with_zero_for_none(): void
    {
        $product = $this->makeProduct('Varying Ring', 'CH-RNG-VARY', ['has_variants' => true]);
        $variant = ProductVariant::create(['product_id' => $product->id, 'sku' => 'V-1', 'weight_grams' => 3, 'stock_quantity' => 2]);

        [$plain] = $this->service->add($this->user, $product->id);
        [$specific] = $this->service->add($this->user, $product->id, $variant->id);

        $this->assertSame(0, (int) DB::table('wishlist_items')->where('id', $plain->id)->value('variant_key'));
        $this->assertSame($variant->id, (int) DB::table('wishlist_items')->where('id', $specific->id)->value('variant_key'));
        $this->assertArrayNotHasKey('variant_key', $specific->toArray());
    }

    public function test_database_rejects_duplicate_product_level_entry(): void
    {
        // Guards against concurrent adds: NULL variant ids would not collide in a
        // plain unique index, so the index is on variant_key instead.
        WishlistItem::create(['user_id' => $this->user->id, 'product_id' => $this->ring->id]);

        $this->expectException(UniqueConstraintViolationException::class);
        WishlistItem::create(['user_id' => $this->user->id, 'product_id' => $this->ring->id]);
    }

    public function test_unknown_product_is_rejected(): void
    {
        $this->expectException(ValidationException::class);
        $this->service->add($this->user, 999999);
    }

    public function test_inactive_product_is_rejected(): void
    {
        $this->ring->update(['status' => 'inactive']);

        try {
            $this->service->add($this->user, $this->ring->id);
            $this->fail('Expected a validation error.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('product_id', $e->errors());
        }
    }

    public function test_variant_rules(): void
    {
        $product = $this->makeProduct('Varying Ring', 'CH-RNG-VARY', ['has_variants' => true]);
        $inactive = ProductVariant::create(['product_id' => $product->id, 'sku' => 'V-OFF', 'weight_grams' => 3, 'stock_quantity' => 2, 'is_active' => false]);
        $foreign = ProductVariant::create(['product_id' => $this->ring->id, 'sku' => 'V-X', 'weight_grams' => 3, 'stock_quantity' => 2]);

        $cases = [
            'variant on product without variants' => [$this->ring->id, $foreign->id],
            'variant of another product' => [$product->id, $foreign->id],
            'inactive variant' => [$product->id, $inactive->id],
        ];

        foreach ($cases as $label => [$productId, $variantId]) {
            try {
                $this->service->add($this->user, $productId, $variantId);
                $this->fail("Expected a validation error for: {$label}");
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('product_variant_id', $e->errors(), $label);
            }
        }

        $this->assertSame(0, WishlistItem::count());
    }

    public function test_full_wishlist_rejects_new_entries_but_not_existing_ones(): void
    {
        $products = collect(range(1, WishlistService::MAX_ITEMS))
            ->map(fn ($i) => $this->makeProduct("Ring {$i}", "CH-BULK-{$i}"));

        $now = now();
        WishlistItem::insert($products->map(fn (Product $p) => [
            'user_id' => $this->user->id,
            'product_id' => $p->id,
            'variant_key' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ])->all());

        // Re-adding something already saved is still fine.
        [, $created] = $this->service->add($this->user, $products->first()->id);
        $this->assertFalse($created);

        $this->expectException(ValidationException::class);
        $this->service->add($this->user, $this->ring->id);
    }

    public function test_sync_counts_added_existing_and_skipped_entries(): void
    {
        $necklace = $this->makeProduct('Necklace', 'CH-NCK-1');
        $inactive = $this->makeProduct('Retired', 'CH-OLD-1', ['status' => 'inactive']);
        $this->service->add($this->user, $this->ring->id);

        $result = $this->service->sync($this->user, [
            ['product_id' => $this->ring->id],                              // already saved
            ['product_id' => $necklace->id],                                // new
            ['product_id' => $necklace->id],                                // duplicate in payload
            ['product_id' => $inactive->id],                                // stale: inactive
            ['product_id' => 999999],                                       // stale: deleted
            ['product_id' => $this->ring->id, 'product_variant_id' => 1],   // stale: bad variant
        ]);

        $this->assertSame(['added' => 1, 'already_saved' => 2, 'skipped' => 3], $result);
        $this->assertEqualsCanonicalizing(
            [$this->ring->id, $necklace->id],
            WishlistItem::where('user_id', $this->user->id)->pluck('product_id')->all()
        );
    }

    public function test_entries_are_removed_with_their_product_or_user(): void
    {
        $other = User::factory()->create(['phone' => fake()->unique()->numerify('97########')]);
        $this->service->add($this->user, $this->ring->id);
        $this->service->add($other, $this->ring->id);

        $other->delete();
        $this->assertSame(1, WishlistItem::count());

        $this->ring->delete();
        $this->assertSame(0, WishlistItem::count());
    }
}
