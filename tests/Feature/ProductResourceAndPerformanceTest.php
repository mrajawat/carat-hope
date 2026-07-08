<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Region;
use App\Models\VariantPrice;
use App\Models\VariantAttributeValue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProductResourceAndPerformanceTest extends TestCase
{
    use RefreshDatabase;

    protected Admin $admin;
    protected Category $category;
    protected Region $region;
    protected Attribute $metalAttribute;
    protected AttributeValue $valueGold;
    protected AttributeValue $valueSilver;

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
        $this->region = Region::create([
            'name' => 'India',
            'currency_code' => 'INR',
            'currency_symbol' => '₹',
            'is_default' => true,
        ]);

        // 4. Create Attributes
        $this->metalAttribute = Attribute::create([
            'name' => 'Metal',
            'slug' => 'metal',
            'input_type' => 'select',
            'affects_price' => true,
        ]);

        $this->valueGold = AttributeValue::create([
            'attribute_id' => $this->metalAttribute->id,
            'value' => 'Gold',
            'price_modifier' => 5000.00,
        ]);

        $this->valueSilver = AttributeValue::create([
            'attribute_id' => $this->metalAttribute->id,
            'value' => 'Silver',
            'price_modifier' => 2000.00,
        ]);

        // Associate Attribute with Category
        $this->category->attributes()->attach($this->metalAttribute->id, ['is_required' => true]);
    }

    /**
     * Test listing endpoint returns flat price/sku/stock_qty for a simple product, with price_label null.
     */
    public function test_listing_endpoint_returns_simple_product_attributes(): void
    {
        Sanctum::actingAs($this->admin);

        Product::create([
            'name' => 'Simple Ring',
            'sku' => 'CH-SMPL-99',
            'category_id' => $this->category->id,
            'price' => 15000.00,
            'discount_price' => 12000.00,
            'stock_qty' => 45,
            'has_variants' => false,
        ]);

        $response = $this->getJson('/api/admin/products');

        $response->assertStatus(200);
        $response->assertJsonPath('data.0.sku', 'CH-SMPL-99');
        $response->assertJsonPath('data.0.price', '15000.00');
        $response->assertJsonPath('data.0.discount_price', '12000.00');
        $response->assertJsonPath('data.0.stock_qty', 45);
        $response->assertJsonPath('data.0.price_label', null);
    }

    /**
     * Test listing endpoint returns null sku, lowest price, and price_label: "Starting from".
     */
    public function test_listing_endpoint_returns_variant_product_computed_price_and_label(): void
    {
        Sanctum::actingAs($this->admin);

        $product = Product::create([
            'name' => 'Variant Ring',
            'category_id' => $this->category->id,
            'has_variants' => true,
            'prices_vary' => true,
            'quantities_vary' => true,
        ]);

        // Create variants with different prices
        $v1 = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'CH-VAR-GOLD',
            'weight_grams' => 2.5,
            'making_charges' => 500,
            'stock_quantity' => 10,
            'is_active' => true,
        ]);
        VariantPrice::create([
            'product_variant_id' => $v1->id,
            'region_id' => $this->region->id,
            'price' => 30000.00,
        ]);

        $v2 = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'CH-VAR-SLVR',
            'weight_grams' => 2.0,
            'making_charges' => 500,
            'stock_quantity' => 15,
            'is_active' => true,
        ]);
        VariantPrice::create([
            'product_variant_id' => $v2->id,
            'region_id' => $this->region->id,
            'price' => 20000.00, // Lowest Price
        ]);

        $response = $this->getJson('/api/admin/products');

        $response->assertStatus(200);
        $response->assertJsonPath('data.0.sku', null);
        $this->assertEquals(20000.00, $response->json('data.0.price')); // Lowest price
        $response->assertJsonPath('data.0.discount_price', null);
        $response->assertJsonPath('data.0.stock_qty', 25); // 10 + 15
        $response->assertJsonPath('data.0.price_label', 'Starting from');
    }

    /**
     * Test listing endpoint returns price: null for a variant product with 0 variants.
     */
    public function test_listing_endpoint_returns_null_price_for_variant_product_with_no_variants(): void
    {
        Sanctum::actingAs($this->admin);

        Product::create([
            'name' => 'Variant Ring No Children',
            'category_id' => $this->category->id,
            'has_variants' => true,
            'prices_vary' => true,
            'quantities_vary' => true,
        ]);

        $response = $this->getJson('/api/admin/products');

        $response->assertStatus(200);
        $response->assertJsonPath('data.0.sku', null);
        $response->assertJsonPath('data.0.price', null); // Assert starting price is null
        $response->assertJsonPath('data.0.stock_qty', 0);
    }

    /**
     * Test detail endpoint for a simple product nulls the attributes/variants keys.
     */
    public function test_detail_endpoint_for_simple_product_nulls_attributes_and_variants(): void
    {
        Sanctum::actingAs($this->admin);

        $product = Product::create([
            'name' => 'Simple Ring Details',
            'sku' => 'CH-SMPL-DTL',
            'category_id' => $this->category->id,
            'price' => 12000.00,
            'discount_price' => 10000.00,
            'stock_qty' => 50,
            'has_variants' => false,
        ]);

        $response = $this->getJson("/api/admin/products/{$product->id}");

        $response->assertStatus(200);
        $response->assertJsonPath('data.sku', 'CH-SMPL-DTL');
        $response->assertJsonPath('data.price', '12000.00');
        $response->assertJsonPath('data.discount_price', '10000.00');
        $response->assertJsonPath('data.stock_qty', 50);
        $response->assertJsonPath('data.attributes', null);
        $response->assertJsonPath('data.variants', null);
    }

    /**
     * Test detail endpoint for a variant product returns correct structure, attribute_value_ids, and region price.
     */
    public function test_detail_endpoint_for_variant_product_returns_correct_nested_structure(): void
    {
        Sanctum::actingAs($this->admin);

        $product = Product::create([
            'name' => 'Gold Ring Detail',
            'category_id' => $this->category->id,
            'has_variants' => true,
            'prices_vary' => true,
            'quantities_vary' => true,
        ]);

        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'CH-DET-GOLD',
            'weight_grams' => 3.0,
            'making_charges' => 500,
            'stock_quantity' => 12,
            'is_active' => true,
        ]);

        VariantPrice::create([
            'product_variant_id' => $variant->id,
            'region_id' => $this->region->id,
            'price' => 28000.00,
        ]);

        VariantAttributeValue::create([
            'product_variant_id' => $variant->id,
            'attribute_id' => $this->metalAttribute->id,
            'attribute_value_id' => $this->valueGold->id,
        ]);

        $response = $this->getJson("/api/admin/products/{$product->id}");

        $response->assertStatus(200);
        $response->assertJsonPath('data.attributes.0.name', 'Metal');
        $response->assertJsonPath('data.attributes.0.values.0.value', 'Gold');

        $response->assertJsonPath('data.variants.0.sku', 'CH-DET-GOLD');
        $response->assertJsonPath('data.variants.0.stock_quantity', 12);
        $response->assertJsonPath('data.variants.0.attribute_value_ids.0', $this->valueGold->id);
        $this->assertEquals(28000.00, $response->json('data.variants.0.price.amount'));
        $response->assertJsonPath('data.variants.0.price.currency_symbol', '₹');
    }

    /**
     * Test detail endpoint query count does not increase per variant (N+1 query check).
     */
    public function test_detail_endpoint_query_count_does_not_increase_per_variant(): void
    {
        Sanctum::actingAs($this->admin);

        // 1. Create a product with 1 variant
        $product1 = Product::create([
            'name' => 'Ring with One Variant',
            'category_id' => $this->category->id,
            'has_variants' => true,
            'prices_vary' => true,
            'quantities_vary' => true,
        ]);
        $v1 = ProductVariant::create(['product_id' => $product1->id, 'sku' => 'CH-1VAR-1', 'weight_grams' => 1.5, 'making_charges' => 500, 'is_active' => true]);
        VariantPrice::create(['product_variant_id' => $v1->id, 'region_id' => $this->region->id, 'price' => 100]);
        VariantAttributeValue::create(['product_variant_id' => $v1->id, 'attribute_id' => $this->metalAttribute->id, 'attribute_value_id' => $this->valueGold->id]);

        // Warm up cache/static vars so they don't skew the query log
        $this->getJson("/api/admin/products/{$product1->id}");

        // Measure query count for product 1 (1 variant)
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->getJson("/api/admin/products/{$product1->id}");
        $queryCount1 = count(DB::getQueryLog());
        DB::disableQueryLog();

        // 2. Create a product with 3 variants
        $product2 = Product::create([
            'name' => 'Ring with Three Variants',
            'category_id' => $this->category->id,
            'has_variants' => true,
            'prices_vary' => true,
            'quantities_vary' => true,
        ]);
        for ($i = 1; $i <= 3; $i++) {
            $v = ProductVariant::create(['product_id' => $product2->id, 'sku' => "CH-3VAR-{$i}", 'weight_grams' => 1.5, 'making_charges' => 500, 'is_active' => true]);
            VariantPrice::create(['product_variant_id' => $v->id, 'region_id' => $this->region->id, 'price' => 100 * $i]);
            VariantAttributeValue::create(['product_variant_id' => $v->id, 'attribute_id' => $this->metalAttribute->id, 'attribute_value_id' => $this->valueGold->id]);
        }

        // Measure query count for product 2 (3 variants)
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->getJson("/api/admin/products/{$product2->id}");
        $queryCount2 = count(DB::getQueryLog());
        DB::disableQueryLog();

        // Assert query count is constant (does not scale linearly with variant count)
        $this->assertEquals($queryCount1, $queryCount2, "Query count increased from {$queryCount1} to {$queryCount2}. N+1 query issue exists!");
    }
}
