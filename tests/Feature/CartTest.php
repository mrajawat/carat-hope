<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductCustomOption;
use App\Models\ProductPrice;
use App\Models\ProductVariant;
use App\Models\Region;
use App\Models\User;
use App\Models\VariantPrice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CartTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Region $india;
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

        $category = Category::create(['name' => 'Rings', 'slug' => 'rings', 'status' => 'active']);

        $this->ring = Product::create([
            'name' => 'Fixed Ring',
            'sku' => 'CH-RNG-FIXED',
            'category_id' => $category->id,
            'price' => 5000.00,
            'stock_qty' => 5,
        ]);
        ProductPrice::create(['product_id' => $this->ring->id, 'region_id' => $this->india->id, 'price' => 4500.00, 'compare_at_price' => 5000.00]);

        $this->user = User::factory()->create(['phone' => fake()->unique()->numerify('98########')]);
        Sanctum::actingAs($this->user);
    }

    public function test_cart_requires_authentication(): void
    {
        $this->app['auth']->forgetGuards();
        $this->withHeaders(['Authorization' => ''])->getJson('/api/public/cart')->assertUnauthorized();
    }

    public function test_empty_cart(): void
    {
        $this->getJson('/api/public/cart')
            ->assertOk()
            ->assertJsonPath('data.items', [])
            ->assertJsonPath('data.items_count', 0)
            ->assertJsonPath('data.currency_code', 'INR');
    }

    public function test_add_item_and_price_it_for_region(): void
    {
        $this->postJson('/api/public/cart/items', ['product_id' => $this->ring->id, 'quantity' => 2])
            ->assertCreated()
            ->assertJsonPath('data.items.0.product_name', 'Fixed Ring')
            ->assertJsonPath('data.items.0.quantity', 2)
            ->assertJsonPath('data.items.0.unit_price', 4500)
            ->assertJsonPath('data.items.0.line_total', 9000)
            ->assertJsonPath('data.subtotal', 9000);
    }

    public function test_adding_same_item_again_merges_quantity(): void
    {
        $this->postJson('/api/public/cart/items', ['product_id' => $this->ring->id, 'quantity' => 1])->assertCreated();
        $this->postJson('/api/public/cart/items', ['product_id' => $this->ring->id, 'quantity' => 2])
            ->assertCreated()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.quantity', 3);
    }

    public function test_cannot_add_more_than_stock(): void
    {
        $this->postJson('/api/public/cart/items', ['product_id' => $this->ring->id, 'quantity' => 4])->assertCreated();
        $this->postJson('/api/public/cart/items', ['product_id' => $this->ring->id, 'quantity' => 2])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('quantity');
    }

    public function test_inactive_product_cannot_be_added(): void
    {
        $this->ring->update(['status' => 'inactive']);

        $this->postJson('/api/public/cart/items', ['product_id' => $this->ring->id, 'quantity' => 1])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('product_id');
    }

    public function test_variant_product_requires_a_valid_variant(): void
    {
        $product = Product::create([
            'name' => 'Varying Ring',
            'sku' => 'CH-RNG-VARY',
            'category_id' => $this->ring->category_id,
            'price' => 10000.00,
            'has_variants' => true,
            'prices_vary' => true,
            'quantities_vary' => true,
        ]);
        $variant = ProductVariant::create(['product_id' => $product->id, 'sku' => 'V-1', 'weight_grams' => 3, 'stock_quantity' => 2]);
        VariantPrice::create(['product_variant_id' => $variant->id, 'region_id' => $this->india->id, 'price' => 12000.00]);
        $otherVariant = ProductVariant::create(['product_id' => $this->ring->id, 'sku' => 'V-X', 'weight_grams' => 3, 'stock_quantity' => 2]);

        $this->postJson('/api/public/cart/items', ['product_id' => $product->id, 'quantity' => 1])
            ->assertJsonValidationErrors('product_variant_id');

        $this->postJson('/api/public/cart/items', ['product_id' => $product->id, 'product_variant_id' => $otherVariant->id, 'quantity' => 1])
            ->assertJsonValidationErrors('product_variant_id');

        $this->postJson('/api/public/cart/items', ['product_id' => $product->id, 'product_variant_id' => $variant->id, 'quantity' => 3])
            ->assertJsonValidationErrors('quantity');

        $this->postJson('/api/public/cart/items', ['product_id' => $product->id, 'product_variant_id' => $variant->id, 'quantity' => 2])
            ->assertCreated()
            ->assertJsonPath('data.items.0.product_variant_id', $variant->id)
            ->assertJsonPath('data.items.0.unit_price', 12000)
            ->assertJsonPath('data.subtotal', 24000);
    }

    public function test_custom_options_are_validated_and_split_lines(): void
    {
        $engraving = ProductCustomOption::create([
            'product_id' => $this->ring->id, 'label' => 'Engraving', 'type' => 'text', 'is_required' => true, 'max_length' => 10,
        ]);
        $box = ProductCustomOption::create([
            'product_id' => $this->ring->id, 'label' => 'Box', 'type' => 'dropdown', 'choices' => ['Red', 'Blue'],
        ]);

        $this->postJson('/api/public/cart/items', ['product_id' => $this->ring->id, 'quantity' => 1])
            ->assertJsonValidationErrors("custom_options.{$engraving->id}");

        $this->postJson('/api/public/cart/items', [
            'product_id' => $this->ring->id, 'quantity' => 1,
            'custom_options' => [$engraving->id => 'This is far too long', $box->id => 'Green'],
        ])->assertJsonValidationErrors(["custom_options.{$engraving->id}", "custom_options.{$box->id}"]);

        $this->postJson('/api/public/cart/items', [
            'product_id' => $this->ring->id, 'quantity' => 1,
            'custom_options' => [$engraving->id => 'A&R', $box->id => 'Red'],
        ])->assertCreated()
            ->assertJsonPath('data.items.0.custom_options.0.label', 'Engraving')
            ->assertJsonPath('data.items.0.custom_options.0.value', 'A&R');

        // Different engraving is a separate line.
        $this->postJson('/api/public/cart/items', [
            'product_id' => $this->ring->id, 'quantity' => 1,
            'custom_options' => [$engraving->id => 'S&M'],
        ])->assertCreated()->assertJsonCount(2, 'data.items');
    }

    public function test_update_remove_and_clear(): void
    {
        $itemId = $this->postJson('/api/public/cart/items', ['product_id' => $this->ring->id, 'quantity' => 1])
            ->json('data.items.0.id');

        $this->patchJson("/api/public/cart/items/{$itemId}", ['quantity' => 4])
            ->assertOk()
            ->assertJsonPath('data.items.0.quantity', 4);

        $this->patchJson("/api/public/cart/items/{$itemId}", ['quantity' => 6])
            ->assertJsonValidationErrors('quantity');

        $this->deleteJson("/api/public/cart/items/{$itemId}")
            ->assertOk()
            ->assertJsonCount(0, 'data.items');

        $this->postJson('/api/public/cart/items', ['product_id' => $this->ring->id, 'quantity' => 1]);
        $this->deleteJson('/api/public/cart')
            ->assertOk()
            ->assertJsonCount(0, 'data.items');
    }

    public function test_cannot_touch_another_customers_cart_item(): void
    {
        $itemId = $this->postJson('/api/public/cart/items', ['product_id' => $this->ring->id, 'quantity' => 1])
            ->json('data.items.0.id');

        Sanctum::actingAs(User::factory()->create(['phone' => fake()->unique()->numerify('98########')]));

        $this->patchJson("/api/public/cart/items/{$itemId}", ['quantity' => 2])->assertNotFound();
        $this->deleteJson("/api/public/cart/items/{$itemId}")->assertNotFound();
        $this->getJson('/api/public/cart')->assertJsonCount(0, 'data.items');
    }
}
