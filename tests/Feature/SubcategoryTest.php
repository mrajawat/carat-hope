<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SubcategoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    /**
     * Helper to create and authenticate an admin.
     */
    protected function authenticateAdmin(): Admin
    {
        $admin = Admin::create([
            'name' => 'Test Admin',
            'email' => 'admin@example.com',
            'password' => bcrypt('password123'),
            'role' => 'admin'
        ]);

        Sanctum::actingAs($admin);

        return $admin;
    }

    /**
     * Test admin can create a subcategory via POST /api/admin/subcategories.
     */
    public function test_admin_can_create_subcategory_with_parent_id(): void
    {
        $this->authenticateAdmin();

        $parentCategory = Category::create([
            'name' => 'Electronics',
            'slug' => 'electronics',
            'status' => 'active'
        ]);

        $base64Image = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

        $payload = [
            'name' => 'Laptops',
            'parent_id' => $parentCategory->id,
            'description' => 'Various types of laptops',
            'image' => $base64Image
        ];

        $response = $this->postJson('/api/admin/subcategories', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Subcategory created successfully')
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id',
                    'name',
                    'slug',
                    'image',
                    'description',
                    'parent_id',
                    'status',
                    'parent' => [
                        'id',
                        'name',
                        'slug'
                    ]
                ]
            ]);

        $this->assertDatabaseHas('categories', [
            'name' => 'Laptops',
            'parent_id' => $parentCategory->id,
            'description' => 'Various types of laptops',
            'status' => 'active'
        ]);
    }

    /**
     * Test admin can create a subcategory via POST /api/admin/categories/{category_id}/subcategories.
     */
    public function test_admin_can_create_subcategory_nested_route(): void
    {
        $this->authenticateAdmin();

        $parentCategory = Category::create([
            'name' => 'Fashion',
            'slug' => 'fashion',
            'status' => 'active'
        ]);

        $payload = [
            'name' => 'Shoes',
            'description' => 'Trending shoes'
        ];

        $response = $this->postJson("/api/admin/categories/{$parentCategory->id}/subcategories", $payload);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Subcategory created successfully')
            ->assertJsonPath('data.parent_id', $parentCategory->id);

        $this->assertDatabaseHas('categories', [
            'name' => 'Shoes',
            'parent_id' => $parentCategory->id,
            'description' => 'Trending shoes'
        ]);
    }

    /**
     * Test subcategory validation rules.
     */
    public function test_create_subcategory_validation_rules(): void
    {
        $this->authenticateAdmin();

        // 1. Missing name and parent_id
        $response = $this->postJson('/api/admin/subcategories', []);
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'parent_id']);

        // 2. Non-existent parent_id
        $response = $this->postJson('/api/admin/subcategories', [
            'name' => 'Subcategory',
            'parent_id' => 999
        ]);
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['parent_id']);

        // 3. Parent category is already a subcategory (prevent deep nesting)
        $parent = Category::create(['name' => 'Root Category', 'slug' => 'root-category']);
        $child = Category::create(['name' => 'Child Category', 'slug' => 'child-category', 'parent_id' => $parent->id]);

        $response = $this->postJson('/api/admin/subcategories', [
            'name' => 'Grandchild Category',
            'parent_id' => $child->id
        ]);
        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'A subcategory cannot be assigned as a parent of another subcategory.');
    }

    /**
     * Test listing all subcategories.
     */
    public function test_admin_can_list_all_subcategories(): void
    {
        $this->authenticateAdmin();

        $root = Category::create(['name' => 'Root', 'slug' => 'root']);
        Category::create(['name' => 'Sub 1', 'slug' => 'sub-1', 'parent_id' => $root->id]);
        Category::create(['name' => 'Sub 2', 'slug' => 'sub-2', 'parent_id' => $root->id]);

        $response = $this->getJson('/api/admin/subcategories');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data');
    }

    /**
     * Test listing subcategories by specific category.
     */
    public function test_admin_can_list_subcategories_by_category(): void
    {
        $this->authenticateAdmin();

        $root1 = Category::create(['name' => 'Root 1', 'slug' => 'root-1']);
        $root2 = Category::create(['name' => 'Root 2', 'slug' => 'root-2']);

        Category::create(['name' => 'Sub 1-1', 'slug' => 'sub-1-1', 'parent_id' => $root1->id]);
        Category::create(['name' => 'Sub 2-1', 'slug' => 'sub-2-1', 'parent_id' => $root2->id]);

        $response = $this->getJson("/api/admin/categories/{$root1->id}/subcategories");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Sub 1-1');
    }

    /**
     * Test admin can view specific subcategory.
     */
    public function test_admin_can_view_subcategory_details(): void
    {
        $this->authenticateAdmin();

        $root = Category::create(['name' => 'Root', 'slug' => 'root']);
        $sub = Category::create(['name' => 'Sub', 'slug' => 'sub', 'parent_id' => $root->id]);

        $response = $this->getJson("/api/admin/subcategories/{$sub->id}");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Sub')
            ->assertJsonPath('data.parent.name', 'Root');
    }

    /**
     * Test admin can update subcategory.
     */
    public function test_admin_can_update_subcategory(): void
    {
        $this->authenticateAdmin();

        $root = Category::create(['name' => 'Root', 'slug' => 'root']);
        $newRoot = Category::create(['name' => 'New Root', 'slug' => 'new-root']);
        $sub = Category::create(['name' => 'Sub', 'slug' => 'sub', 'parent_id' => $root->id]);

        $payload = [
            'name' => 'Updated Sub',
            'parent_id' => $newRoot->id,
            'description' => 'Updated desc'
        ];

        $response = $this->putJson("/api/admin/subcategories/{$sub->id}", $payload);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Subcategory updated successfully')
            ->assertJsonPath('data.name', 'Updated Sub')
            ->assertJsonPath('data.parent_id', $newRoot->id);

        $this->assertDatabaseHas('categories', [
            'id' => $sub->id,
            'name' => 'Updated Sub',
            'parent_id' => $newRoot->id
        ]);
    }

    /**
     * Test admin can delete subcategory.
     */
    public function test_admin_can_delete_subcategory(): void
    {
        $this->authenticateAdmin();

        $root = Category::create(['name' => 'Root', 'slug' => 'root']);
        $sub = Category::create(['name' => 'Sub', 'slug' => 'sub', 'parent_id' => $root->id]);

        $response = $this->deleteJson("/api/admin/subcategories/{$sub->id}");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Subcategory deleted successfully');

        $this->assertDatabaseMissing('categories', [
            'id' => $sub->id
        ]);
    }

    /**
     * Test admin can toggle status.
     */
    public function test_admin_can_toggle_status_of_subcategory(): void
    {
        $this->authenticateAdmin();

        $root = Category::create(['name' => 'Root', 'slug' => 'root']);
        $sub = Category::create(['name' => 'Sub', 'slug' => 'sub', 'parent_id' => $root->id, 'status' => 'active']);

        $response = $this->patchJson("/api/admin/subcategories/{$sub->id}/toggle-status");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Subcategory status updated successfully')
            ->assertJsonPath('data.status', 'inactive');

        $this->assertDatabaseHas('categories', [
            'id' => $sub->id,
            'status' => 'inactive'
        ]);
    }

    /**
     * Test guest cannot access subcategory routes.
     */
    public function test_guest_cannot_access_subcategory_endpoints(): void
    {
        $response = $this->getJson('/api/admin/subcategories');
        $response->assertStatus(401);

        $response = $this->postJson('/api/admin/subcategories', []);
        $response->assertStatus(401);
    }
}
