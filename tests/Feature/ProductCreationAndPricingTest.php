<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Region;
use App\Models\VariantPrice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProductCreationAndPricingTest extends TestCase
{
    use RefreshDatabase;

    protected Admin $admin;
    protected Category $category;
    protected Region $defaultRegion;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Create Admin
        $this->admin = Admin::create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => 'password123',
            'role' => 'admin',
        ]);

        // 2. Create Category
        $this->category = Category::create([
            'name' => 'Rings',
            'slug' => 'rings',
            'status' => 'active',
        ]);

        // 3. Create Default Region
        $this->defaultRegion = Region::create([
            'name' => 'India',
            'currency_code' => 'INR',
            'currency_symbol' => '₹',
            'is_default' => true,
        ]);
    }

    /**
     * Creating a simple product (has_variants = false) with a full payload succeeds
     * and stores price/sku/stock_qty correctly.
     */
    public function test_create_simple_product_success(): void
    {
        Sanctum::actingAs($this->admin);

        $payload = [
            'name' => 'Simple Diamond Ring',
            'sku' => 'CH-SMPL-01',
            'category_id' => $this->category->id,
            'price' => 25000.00,
            'discount_price' => 22000.00,
            'stock_qty' => 10,
            'description' => 'A beautiful simple ring.',
            'has_variants' => false,
            'images' => [
                'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='
            ]
        ];

        $response = $this->postJson('/api/admin/products', $payload);

        $response->assertStatus(201);
        $response->assertJsonPath('status', true);
        $response->assertJsonPath('data.has_variants', false);

        $product = Product::where('sku', 'CH-SMPL-01')->first();
        $this->assertNotNull($product);
        $this->assertEquals(25000.00, (float)$product->price);
        $this->assertEquals(10, $product->stock_qty);
    }

    /**
     * Creating a simple product without price fails validation with a clear error message.
     */
    public function test_create_simple_product_without_price_fails(): void
    {
        Sanctum::actingAs($this->admin);

        $payload = [
            'name' => 'Simple Diamond Ring',
            'sku' => 'CH-SMPL-02',
            'category_id' => $this->category->id,
            'stock_qty' => 10,
            'description' => 'A beautiful simple ring.',
            'has_variants' => false,
            'images' => [
                'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='
            ]
        ];

        $response = $this->postJson('/api/admin/products', $payload);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['price']);
        $response->assertJsonPath('errors.price.0', 'Price is required for simple products.');
    }

    /**
     * Creating a variant product (has_variants = true) without price/sku/stock_qty succeeds.
     */
    public function test_create_variant_product_success_without_simple_fields(): void
    {
        Sanctum::actingAs($this->admin);

        $payload = [
            'name' => 'Variant Diamond Ring',
            'category_id' => $this->category->id,
            'description' => 'A customizable ring.',
            'has_variants' => true,
            'images' => [
                'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='
            ]
        ];

        $response = $this->postJson('/api/admin/products', $payload);

        $response->assertStatus(201);
        $response->assertJsonPath('status', true);
        $response->assertJsonPath('data.has_variants', true);

        $product = Product::where('name', 'Variant Diamond Ring')->first();
        $this->assertNotNull($product);
        $this->assertNull($product->getRawOriginal('sku'));
        $this->assertNull($product->getRawOriginal('price'));
        $this->assertNull($product->getRawOriginal('stock_qty'));
    }

    /**
     * Creating a variant product with variants and amounts at once stores everything in one payload.
     */
    public function test_create_product_with_variants_and_amounts_at_once(): void
    {
        Sanctum::actingAs($this->admin);

        $payload = [
            'name' => 'Gemstone Ring All In One',
            'category_id' => $this->category->id,
            'description' => 'A ring with gemstone options.',
            'has_variants' => true,
            'prices_vary' => true,
            'quantities_vary' => true,
            'skus_vary' => true,
            'images' => [
                'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='
            ],
            'variants' => [
                [
                    'sku' => 'CH-GEM-AMETRINE',
                    'stock_quantity' => 10,
                    'is_active' => true,
                    'attributes' => [
                        ['name' => 'Gemstone', 'value' => 'Ametrine']
                    ],
                    'prices' => [
                        ['region_id' => $this->defaultRegion->id, 'price' => 35000.00]
                    ]
                ],
                [
                    'sku' => 'CH-GEM-AQUAMARINE',
                    'stock_quantity' => 15,
                    'is_active' => true,
                    'attributes' => [
                        ['name' => 'Gemstone', 'value' => 'Aquamarine']
                    ],
                    'prices' => [
                        ['region_id' => $this->defaultRegion->id, 'price' => 42000.00]
                    ]
                ]
            ]
        ];

        $response = $this->postJson('/api/admin/products', $payload);

        $response->assertStatus(201);
        $response->assertJsonPath('status', true);
        $response->assertJsonPath('data.has_variants', true);

        $product = Product::where('name', 'Gemstone Ring All In One')->first();
        $this->assertNotNull($product);

        $variants = $product->variants()->with(['attributeValues.attribute', 'prices'])->get();
        $this->assertCount(2, $variants);
        $this->assertEquals(10, $variants[0]->stock_quantity);
        $this->assertEquals(15, $variants[1]->stock_quantity);
        $this->assertEquals(35000.00, (float)$variants[0]->prices->first()->price);
        $this->assertEquals(42000.00, (float)$variants[1]->prices->first()->price);
    }

    /**
     * A variant product's price and stock_qty fields, when fetched via the show/index endpoint,
     * correctly reflect the computed values from its variants.
     */
    public function test_variant_product_computes_prices_and_stock(): void
    {
        Sanctum::actingAs($this->admin);

        // 1. Create a product with has_variants = true
        $product = Product::create([
            'name' => 'Calculated Variant Ring',
            'category_id' => $this->category->id,
            'has_variants' => true,
            'prices_vary' => true,
            'quantities_vary' => true,
        ]);

        // 2. Create 2 variants
        $var1 = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'CH-CALC-VAR1',
            'weight_grams' => 3.00,
            'stock_quantity' => 5,
        ]);

        $var2 = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'CH-CALC-VAR2',
            'weight_grams' => 3.50,
            'stock_quantity' => 12,
        ]);

        // 3. Create prices for India region (INR)
        VariantPrice::create([
            'product_variant_id' => $var1->id,
            'region_id' => $this->defaultRegion->id,
            'price' => 30000.00,
        ]);

        VariantPrice::create([
            'product_variant_id' => $var2->id,
            'region_id' => $this->defaultRegion->id,
            'price' => 35000.00,
        ]);

        // 4. Fetch via show API
        $response = $this->getJson("/api/admin/products/{$product->id}");

        $response->assertStatus(200);
        $response->assertJsonPath('data.sku', null);
        $response->assertJsonPath('data.price', 30000); // lowest variant price
        $response->assertJsonPath('data.stock_qty', 17); // sum of stock (5 + 12)
    }

    /**
     * A variant product with zero variants created yet returns a sensible default
     * (price: null, stock_qty: 0) rather than erroring.
     */
    public function test_variant_product_with_zero_variants_defaults(): void
    {
        Sanctum::actingAs($this->admin);

        $product = Product::create([
            'name' => 'Empty Variant Ring',
            'category_id' => $this->category->id,
            'has_variants' => true,
            'prices_vary' => true,
            'quantities_vary' => true,
        ]);

        $response = $this->getJson("/api/admin/products/{$product->id}");

        $response->assertStatus(200);
        $response->assertJsonPath('data.sku', null);
        $response->assertJsonPath('data.price', null);
        $response->assertJsonPath('data.stock_qty', 0);
    }
}
