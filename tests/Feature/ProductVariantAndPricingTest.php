<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Region;
use App\Models\RegionTaxRule;
use App\Models\VariantPrice;
use App\Services\VariantCombinationService;
use App\Services\VariantPricingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProductVariantAndPricingTest extends TestCase
{
    use RefreshDatabase;

    protected Admin $admin;
    protected Category $category;
    protected Product $productVary;
    protected Product $productNoVary;
    protected Region $india;
    protected Region $usa;
    protected Attribute $metalKarat;
    protected Attribute $ringSize;

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

        // 3. Create Products
        $this->productVary = Product::create([
            'name' => 'Varying Ring',
            'sku' => 'CH-RNG-VARY',
            'category_id' => $this->category->id,
            'price' => 10000.00,
            'discount_price' => 9000.00,
            'stock_qty' => 50,
            'prices_vary' => true,
            'quantities_vary' => true,
            'skus_vary' => true,
            'max_variation_axes' => 2,
        ]);

        $this->productNoVary = Product::create([
            'name' => 'Fixed Ring',
            'sku' => 'CH-RNG-FIXED',
            'category_id' => $this->category->id,
            'price' => 5000.00,
            'discount_price' => null,
            'stock_qty' => 30,
            'prices_vary' => false,
            'quantities_vary' => false,
            'skus_vary' => false,
            'max_variation_axes' => 2,
        ]);

        // 4. Create Regions
        $this->india = Region::create([
            'name' => 'India',
            'currency_code' => 'INR',
            'currency_symbol' => '₹',
            'is_default' => true,
        ]);

        $this->usa = Region::create([
            'name' => 'United States',
            'currency_code' => 'USD',
            'currency_symbol' => '$',
            'is_default' => false,
        ]);

        // 5. Create Tax Rule
        RegionTaxRule::create([
            'region_id' => $this->india->id,
            'tax_name' => 'GST',
            'tax_percentage' => 3.00,
            'inclusive' => false,
        ]);

        // 6. Create Attributes & Values
        $this->metalKarat = Attribute::create([
            'name' => 'Metal Karat',
            'slug' => 'metal-karat',
            'input_type' => 'select',
            'affects_price' => true,
        ]);
        $this->metalKarat->values()->createMany([
            ['value' => '18K', 'price_modifier' => 5000.00, 'sort_order' => 1],
            ['value' => '22K', 'price_modifier' => 10000.00, 'sort_order' => 2],
        ]);

        $this->ringSize = Attribute::create([
            'name' => 'Ring Size',
            'slug' => 'ring-size',
            'input_type' => 'number',
            'affects_price' => true,
        ]);
        $this->ringSize->values()->createMany([
            ['value' => '6', 'price_modifier' => 0.00, 'sort_order' => 1],
            ['value' => '7', 'price_modifier' => 0.00, 'sort_order' => 2],
        ]);

        // Map Attributes to Category
        $this->category->attributes()->syncWithoutDetaching([
            $this->metalKarat->id => ['is_required' => true],
            $this->ringSize->id => ['is_required' => true],
        ]);
    }

    /**
     * Test Combination Generation count and pairing.
     */
    public function test_combination_generation(): void
    {
        $service = app(VariantCombinationService::class);

        $selected = [
            'metal-karat' => $this->metalKarat->values()->pluck('id')->toArray(), // [18K, 22K]
            'ring-size' => $this->ringSize->values()->pluck('id')->toArray(),    // [6, 7]
        ];

        // Generation must return 4 combinations
        $combinations = $service->generateCombinations($selected);
        $this->assertCount(4, $combinations);

        // Enforce the max 2 axes soft limit rule
        $this->expectException(\InvalidArgumentException::class);
        $selectedThree = array_merge($selected, ['extra' => [1, 2]]);
        $service->generateCombinations($selectedThree);
    }

    /**
     * Test SKU Uniqueness and Bulk Generation.
     */
    public function test_sku_uniqueness_under_bulk_generation(): void
    {
        $service = app(VariantCombinationService::class);

        $selected = [
            'metal-karat' => $this->metalKarat->values()->pluck('id')->toArray(),
            'ring-size' => $this->ringSize->values()->pluck('id')->toArray(),
        ];

        $combinations = $service->generateCombinations($selected);

        // Bulk create
        $variants = $service->createVariantsFromCombinations($this->productVary, $combinations);
        $this->assertCount(4, $variants);

        $skus = $variants->pluck('sku')->toArray();
        $this->assertEquals(count($skus), count(array_unique($skus)), 'SKUs must be unique');

        // Check if collision suffixes are working by triggering second bulk creation
        $moreVariants = $service->createVariantsFromCombinations($this->productVary, $combinations);
        $allSkus = $variants->merge($moreVariants)->pluck('sku')->toArray();
        $this->assertEquals(count($allSkus), count(array_unique($allSkus)), 'Collided SKUs must have uniqueness suffix');
    }

    /**
     * Test Price Resolution Across Toggle States.
     */
    public function test_price_resolution_when_prices_vary_is_false(): void
    {
        $pricingService = app(VariantPricingService::class);

        // Create a variant on a product where prices_vary is false
        $variant = ProductVariant::create([
            'product_id' => $this->productNoVary->id,
            'sku' => 'CH-FIXED-VAR1',
            'weight_grams' => 2.500,
            'base_price' => 4500.00,
            'stock_quantity' => 10,
        ]);

        // Resolves to base price for India (INR)
        $priceIndia = $pricingService->getPriceForRegion($variant, $this->india->id);
        $this->assertEquals(4500.00, $priceIndia['price']);
        $this->assertEquals('₹', $priceIndia['currency_symbol']);

        // Resolves to base price for USA (USD)
        $priceUsa = $pricingService->getPriceForRegion($variant, $this->usa->id);
        $this->assertEquals(4500.00, $priceUsa['price']);
        $this->assertEquals('$', $priceUsa['currency_symbol']);
    }

    public function test_price_resolution_when_prices_vary_is_true(): void
    {
        $pricingService = app(VariantPricingService::class);

        // Create a variant on a product where prices_vary is true
        $variant = ProductVariant::create([
            'product_id' => $this->productVary->id,
            'sku' => 'CH-VARY-VAR1',
            'weight_grams' => 3.500,
            'stock_quantity' => 15,
        ]);

        // 1. Create a price row for India (INR)
        VariantPrice::create([
            'product_variant_id' => $variant->id,
            'region_id' => $this->india->id,
            'price' => 12000.00,
            'compare_at_price' => 14000.00,
        ]);

        // Resolves exact price for India
        $priceIndia = $pricingService->getPriceForRegion($variant, $this->india->id);
        $this->assertEquals(12000.00, $priceIndia['price']);
        $this->assertEquals(14000.00, $priceIndia['compare_at_price']);
        $this->assertEquals('INR', $priceIndia['currency_code']);

        // 2. Since no price is defined for USA, it falls back to the default region (India)
        $priceUsa = $pricingService->getPriceForRegion($variant, $this->usa->id);
        $this->assertEquals(12000.00, $priceUsa['price']);
        $this->assertEquals('$', $priceUsa['currency_symbol']);
    }

    /**
     * Test Region Tax inclusive/exclusive Calculations.
     */
    public function test_tax_calculation(): void
    {
        $pricingService = app(VariantPricingService::class);

        // Create variant
        $variant = ProductVariant::create([
            'product_id' => $this->productNoVary->id,
            'sku' => 'CH-TAX-VAR1',
            'weight_grams' => 2.000,
            'base_price' => 100.00,
        ]);

        // India GST is 3.00% exclusive
        $taxDetails = $pricingService->getPriceWithTax($variant, $this->india->id);
        $this->assertEquals(100.00, $taxDetails['subtotal']);
        $this->assertEquals(3.00, $taxDetails['tax_amount']);
        $this->assertEquals(103.00, $taxDetails['total']);
        $this->assertEquals('GST', $taxDetails['tax_name']);

        // Update GST to be inclusive
        $indiaTax = RegionTaxRule::where('region_id', $this->india->id)->first();
        $indiaTax->update(['inclusive' => true]);

        // Under inclusive tax: Total is 100.00. Subtotal = 100 / 1.03 = 97.09. Tax = 2.91
        $taxDetailsInclusive = $pricingService->getPriceWithTax($variant, $this->india->id);
        $this->assertEquals(97.09, $taxDetailsInclusive['subtotal']);
        $this->assertEquals(2.91, $taxDetailsInclusive['tax_amount']);
        $this->assertEquals(100.00, $taxDetailsInclusive['total']);
    }

    /**
     * Test API endpoints behavior.
     */
    public function test_api_generate_combinations(): void
    {
        Sanctum::actingAs($this->admin);

        $payload = [
            'attributes' => [
                'metal-karat' => $this->metalKarat->values()->pluck('id')->toArray(),
                'ring-size' => $this->ringSize->values()->pluck('id')->toArray(),
            ]
        ];

        $response = $this->postJson("/api/admin/products/{$this->productVary->id}/variants/generate-combinations", $payload);

        $response->assertStatus(201);
        $response->assertJsonPath('status', true);
        $response->assertJsonCount(4, 'data');
    }

    public function test_api_get_frontend_product_price(): void
    {
        // Setup variant and price
        $variant = ProductVariant::create([
            'product_id' => $this->productVary->id,
            'sku' => 'CH-FRONT-VAR1',
            'weight_grams' => 3.000,
            'base_price' => 12000.00,
        ]);

        $val18k = $this->metalKarat->values()->first();
        $val6 = $this->ringSize->values()->first();

        $variant->attributeValues()->attach($val18k->id, ['attribute_id' => $this->metalKarat->id]);
        $variant->attributeValues()->attach($val6->id, ['attribute_id' => $this->ringSize->id]);

        // Request resolved price via API with selected attribute values
        $response = $this->getJson("/api/products/{$this->productVary->id}/price?attribute_value_ids={$val18k->id},{$val6->id}");

        $response->assertStatus(200);
        $response->assertJsonPath('status', true);
        $response->assertJsonPath('data.variant.sku', 'CH-FRONT-VAR1');
        $response->assertJsonStructure([
            'status',
            'message',
            'data' => [
                'product_id',
                'product_name',
                'variant' => ['id', 'sku', 'weight_grams', 'stock_quantity'],
                'region' => ['id', 'name', 'currency_code', 'currency_symbol'],
                'pricing' => ['subtotal', 'tax_amount', 'tax_name', 'total']
            ]
        ]);
    }
}
