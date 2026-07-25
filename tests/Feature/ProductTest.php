<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProductTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    /**
     * Test that an admin can successfully add a product.
     */
    public function test_admin_can_add_product(): void
    {
        // 1. Create an Admin and Category
        $admin = Admin::create([
            'name' => 'Test Admin',
            'email' => 'admin@example.com',
            'password' => 'password123',
            'role' => 'admin'
        ]);

        $category = Category::create([
            'name' => 'Rings',
            'slug' => 'rings',
            'image' => 'https://example.com/rings.png',
            'status' => 'active'
        ]);

        // 2. Authenticate as Admin
        Sanctum::actingAs($admin);

        // 1x1 transparent red PNG base64
        $base64Image = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

        $payload = [
            'name' => 'Diamond Solitaire Ring',
            'sku' => 'RNG-SOL-001',
            'category_id' => $category->id,
            'price' => 75000.00,
            'discount_price' => 69999.00,
            'stock_qty' => 15,
            'description' => 'A beautiful diamond ring.',
            'local_prices' => [
                'US' => [
                    'price' => 950.00,
                    'discount_price' => 899.00
                ]
            ],
            'images' => [$base64Image]
        ];

        // 3. Make POST request
        $response = $this->postJson('/api/admin/products', $payload);

        // 4. Assert responses
        $response->assertStatus(201);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('message', 'Product created successfully');
        $response->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'id',
                'name',
                'sku',
                'category_id',
                'price',
                'discount_price',
                'local_prices',
                'stock_qty',
                'description',
                'product_images'
            ]
        ]);

        // 5. Assert database records
        $this->assertDatabaseHas('products', [
            'sku' => 'RNG-SOL-001',
            'name' => 'Diamond Solitaire Ring',
            'price' => 75000.00,
            'discount_price' => 69999.00,
            'stock_qty' => 15
        ]);

        $product = Product::where('sku', 'RNG-SOL-001')->first();
        $this->assertCount(1, $product->product_images);
    }

    /**
     * Test that guest users cannot add products.
     */
    public function test_guest_cannot_add_product(): void
    {
        $response = $this->postJson('/api/admin/products', []);
        $response->assertStatus(401);
    }

    /**
     * Test API validation rules for adding products.
     */
    public function test_add_product_validation(): void
    {
        $admin = Admin::create([
            'name' => 'Test Admin',
            'email' => 'admin@example.com',
            'password' => 'password123',
            'role' => 'admin'
        ]);

        Sanctum::actingAs($admin);

        // Test 1: Empty Payload
        $response = $this->postJson('/api/admin/products', []);
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['name', 'category_id', 'price', 'stock_qty', 'images']);

        // Test 2: Discount price greater than price
        $response = $this->postJson('/api/admin/products', [
            'name' => 'Invalid Discount Ring',
            'sku' => 'RNG-INV-001',
            'category_id' => 999, // Non-existent category
            'price' => 100,
            'discount_price' => 120, // discount_price > price
            'stock_qty' => 5,
            'images' => ['data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==']
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['discount_price', 'category_id']);
    }

    /**
     * Test adding and updating a product with a video.
     */
    public function test_admin_can_add_and_update_product_with_video(): void
    {
        $admin = Admin::create([
            'name' => 'Test Admin',
            'email' => 'admin@example.com',
            'password' => 'password123',
            'role' => 'admin'
        ]);

        $category = Category::create([
            'name' => 'Necklaces',
            'slug' => 'necklaces',
            'status' => 'active'
        ]);

        Sanctum::actingAs($admin);

        $base64Image = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';
        $base64Video = 'data:video/mp4;base64,AAAAIGZ0eXBtcDQyAAAAAG1wNDJpc29tYXZjMQAAAAhtZGF0';

        $payload = [
            'name' => 'Gold Chain',
            'sku' => 'GLD-CHN-001',
            'category_id' => $category->id,
            'price' => 50000.00,
            'stock_qty' => 10,
            'description' => 'A gold chain.',
            'video' => $base64Video,
            'images' => [$base64Image]
        ];

        // 1. Create product with video
        $response = $this->postJson('/api/admin/products', $payload);

        $response->assertStatus(201);
        $response->assertJsonPath('success', true);

        $product = Product::where('sku', 'GLD-CHN-001')->first();
        $videoRecord = $product->product_images()->where('type', 'video')->first();
        $this->assertNotNull($videoRecord);
        $this->assertStringContainsString('videos/', $videoRecord->image_path);

        // 2. Update product and clear video
        $updatePayload = [
            'name' => 'Gold Chain Updated',
            'sku' => 'GLD-CHN-001',
            'category_id' => $category->id,
            'price' => 55000.00,
            'stock_qty' => 8,
            'video' => null,
        ];

        $response = $this->putJson('/api/admin/products/' . $product->id, $updatePayload);

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);

        $product->refresh();
        $this->assertFalse($product->product_images()->where('type', 'video')->exists());
    }

    /**
     * Test adding a product with an uploaded video file.
     */
    public function test_admin_can_add_product_with_uploaded_file_video(): void
    {
        $admin = Admin::create([
            'name' => 'Test Admin',
            'email' => 'admin@example.com',
            'password' => 'password123',
            'role' => 'admin'
        ]);

        $category = Category::create([
            'name' => 'Bracelets',
            'slug' => 'bracelets',
            'status' => 'active'
        ]);

        Sanctum::actingAs($admin);

        $base64Image = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';
        $uploadedFile = \Illuminate\Http\UploadedFile::fake()->create('promo.mp4', 1000, 'video/mp4');

        $payload = [
            'name' => 'Silver Bracelet',
            'sku' => 'SLV-BRC-001',
            'category_id' => $category->id,
            'price' => 15000.00,
            'stock_qty' => 20,
            'video' => $uploadedFile,
            'images' => [$base64Image]
        ];

        $response = $this->postJson('/api/admin/products', $payload);

        $response->assertStatus(201);
        $response->assertJsonPath('success', true);

        $product = Product::where('sku', 'SLV-BRC-001')->first();
        $videoRecord = $product->product_images()->where('type', 'video')->first();
        $this->assertNotNull($videoRecord);
        $this->assertStringContainsString('videos/', $videoRecord->image_path);
    }

    /**
     * Test bulk toggling featured status of products.
     */
    public function test_admin_can_bulk_toggle_featured_products(): void
    {
        $admin = Admin::create([
            'name' => 'Test Admin',
            'email' => 'admin@example.com',
            'password' => 'password123',
            'role' => 'admin'
        ]);

        $category = Category::create([
            'name' => 'Watches',
            'slug' => 'watches',
            'status' => 'active'
        ]);

        $p1 = Product::create([
            'name' => 'Watch A',
            'sku' => 'WTC-A',
            'category_id' => $category->id,
            'price' => 1000.00,
            'stock_qty' => 10,
            'is_featured' => false,
            'status' => 'active'
        ]);

        $p2 = Product::create([
            'name' => 'Watch B',
            'sku' => 'WTC-B',
            'category_id' => $category->id,
            'price' => 2000.00,
            'stock_qty' => 5,
            'is_featured' => true,
            'status' => 'active'
        ]);

        Sanctum::actingAs($admin);

        // 1. Bulk toggle
        $response = $this->postJson('/api/admin/products/bulk-toggle-featured', [
            'product_ids' => [$p1->id, $p2->id]
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('message', 'Products featured status toggled successfully.');

        $this->assertTrue((bool) $p1->refresh()->is_featured);
        $this->assertFalse((bool) $p2->refresh()->is_featured);

        // 2. Toggle again
        $response = $this->postJson('/api/admin/products/bulk-toggle-featured', [
            'product_ids' => [$p1->id, $p2->id]
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);

        $this->assertFalse((bool) $p1->refresh()->is_featured);
        $this->assertTrue((bool) $p2->refresh()->is_featured);
    }

    /**
     * Test adding product with multiple images in one array where first image becomes main image (is_primary = true).
     */
    public function test_add_product_images_first_in_row_is_main_image(): void
    {
        $admin = Admin::create([
            'name' => 'Test Admin',
            'email' => 'admin_img@example.com',
            'password' => 'password123',
            'role' => 'admin'
        ]);

        $category = Category::create([
            'name' => 'Pendants',
            'slug' => 'pendants',
            'status' => 'active'
        ]);

        Sanctum::actingAs($admin);

        $img1 = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';
        $img2 = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9AFwADhgGAWjR9awAAAABJRU5ErkJggg==';
        $img3 = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPj/HwADBwIAM4A08AAAAABJRU5ErkJggg==';

        $payload = [
            'name' => 'Diamond Pendant',
            'sku' => 'PND-001',
            'category_id' => $category->id,
            'price' => 30000.00,
            'stock_qty' => 5,
            'images' => [$img1, $img2, $img3]
        ];

        $response = $this->postJson('/api/admin/products', $payload);

        $response->assertStatus(201);
        $product = Product::where('sku', 'PND-001')->first();
        $this->assertNotNull($product);

        $images = $product->product_images()->where('type', 'image')->get();
        $this->assertCount(3, $images);

        // First image in row must be primary
        $this->assertTrue((bool) $images[0]->is_primary);
        $this->assertFalse((bool) $images[1]->is_primary);
        $this->assertFalse((bool) $images[2]->is_primary);
    }

    /**
     * Test uploading both photo and video from the same images array.
     */
    public function test_add_product_mixed_photos_and_video_upload(): void
    {
        $admin = Admin::create([
            'name' => 'Test Admin',
            'email' => 'admin_mixed@example.com',
            'password' => 'password123',
            'role' => 'admin'
        ]);

        $category = Category::create([
            'name' => 'Bangles',
            'slug' => 'bangles',
            'status' => 'active'
        ]);

        Sanctum::actingAs($admin);

        $img1 = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';
        $video1 = 'data:video/mp4;base64,AAAAIGZ0eXBtcDQyAAAAAG1wNDJpc29tYXZjMQAAAAhtZGF0';
        $img2 = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9AFwADhgGAWjR9awAAAABJRU5ErkJggg==';

        $payload = [
            'name' => 'Gold Bangle Set',
            'sku' => 'BNG-001',
            'category_id' => $category->id,
            'price' => 45000.00,
            'stock_qty' => 4,
            'images' => [$img1, $video1, $img2]
        ];

        $response = $this->postJson('/api/admin/products', $payload);

        $response->assertStatus(201);
        $product = Product::where('sku', 'BNG-001')->first();
        $this->assertNotNull($product);

        $photos = $product->product_images()->where('type', 'image')->get();
        $videos = $product->product_images()->where('type', 'video')->get();

        $this->assertCount(2, $photos);
        $this->assertCount(1, $videos);
        $this->assertTrue((bool) $photos[0]->is_primary);
        $this->assertFalse((bool) $photos[1]->is_primary);
    }

    /**
     * Test upload validation fails if photos exceed limit of 20.
     */
    public function test_add_product_exceeding_photo_limit_fails(): void
    {
        $admin = Admin::create([
            'name' => 'Test Admin',
            'email' => 'admin_limit@example.com',
            'password' => 'password123',
            'role' => 'admin'
        ]);

        $category = Category::create([
            'name' => 'Anklets',
            'slug' => 'anklets',
            'status' => 'active'
        ]);

        Sanctum::actingAs($admin);

        $img = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';
        $images = array_fill(0, 21, $img); // 21 photos

        $payload = [
            'name' => 'Silver Anklet',
            'sku' => 'ANK-001',
            'category_id' => $category->id,
            'price' => 5000.00,
            'stock_qty' => 10,
            'images' => $images
        ];

        $response = $this->postJson('/api/admin/products', $payload);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['images']);
    }

    /**
     * Test adding product with Step 3 attributes and specifications (tags, materials, gold_purity, pendant_width with unit).
     */
    public function test_add_product_step3_attributes_and_specifications(): void
    {
        $admin = Admin::create([
            'name' => 'Test Admin',
            'email' => 'admin_step3@example.com',
            'password' => 'password123',
            'role' => 'admin'
        ]);

        $category = Category::create([
            'name' => 'Gold Necklaces',
            'slug' => 'gold-necklaces',
            'status' => 'active'
        ]);

        Sanctum::actingAs($admin);

        $img = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

        $payload = [
            'name' => 'Luxury Gold Pendant',
            'sku' => 'LXR-PND-001',
            'category_id' => $category->id,
            'price' => 50000.00,
            'stock_qty' => 5,
            'images' => [$img],
            'tags' => ['Pendant', 'Gold', 'Handmade'],
            'materials' => ['Gold', 'Diamond'],
            'gold_solidity' => ['Solid Gold'],
            'gold_purity' => ['18k'],
            'primary_colour' => 'Yellow Gold',
            'pendant_width' => ['value' => 18, 'unit' => 'Millimetres'],
            'pendant_height' => ['value' => 25, 'unit' => 'Millimetres'],
            'recycled' => true
        ];

        $response = $this->postJson('/api/admin/products', $payload);

        $response->assertStatus(201);
        $product = Product::where('sku', 'LXR-PND-001')->first();
        $this->assertNotNull($product);

        $this->assertEquals(['Pendant', 'Gold', 'Handmade'], $product->tags);
        $this->assertEquals(['Gold', 'Diamond'], $product->materials);
        $this->assertEquals(['18k'], $product->gold_purity);
        $this->assertEquals('Yellow Gold', $product->listing_attributes['primary_colour']);
        $this->assertEquals(18, $product->listing_attributes['pendant_width']['value']);
        $this->assertEquals('Millimetres', $product->listing_attributes['pendant_width']['unit']);
        $this->assertTrue($product->listing_attributes['recycled']);
    }

    /**
     * Test preview product API generates preview data without saving to the database.
     */
    public function test_preview_product_api_does_not_save_to_database(): void
    {
        $admin = Admin::create([
            'name' => 'Test Admin',
            'email' => 'admin_preview@example.com',
            'password' => 'password123',
            'role' => 'admin'
        ]);

        $category = Category::create([
            'name' => 'Preview Category',
            'slug' => 'preview-category',
            'status' => 'active'
        ]);

        Sanctum::actingAs($admin);

        $img = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

        $payload = [
            'name' => 'Preview Diamond Ring',
            'sku' => 'PRV-RNG-999',
            'category_id' => $category->id,
            'price' => 25000.00,
            'stock_qty' => 10,
            'images' => [$img],
            'tags' => ['Preview', 'Diamond'],
            'materials' => ['Platinum']
        ];

        $response = $this->postJson('/api/admin/products/preview', $payload);

        $response->assertStatus(200);
        $response->assertJsonPath('status', true);
        $response->assertJsonPath('data.product.name', 'Preview Diamond Ring');
        $response->assertJsonPath('data.is_preview', true);

        // Verify product was NOT saved in database
        $this->assertNull(Product::where('sku', 'PRV-RNG-999')->first());
    }
}

