<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CustomOrderTest extends TestCase
{
    use RefreshDatabase;

    protected Category $rings;
    protected AttributeValue $gold;
    protected AttributeValue $silver;
    protected AttributeValue $karat18;
    protected AttributeValue $karat22;
    protected AttributeValue $diamond;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->rings = Category::create(['name' => 'Rings', 'slug' => 'rings', 'status' => 'active']);

        $metal = Attribute::create(['name' => 'Metal Type', 'slug' => 'metal-type', 'input_type' => 'select']);
        $this->gold = $metal->values()->create(['value' => 'Gold', 'sort_order' => 1]);
        $this->silver = $metal->values()->create(['value' => 'Silver', 'sort_order' => 2]);

        $karat = Attribute::create(['name' => 'Metal Karat', 'slug' => 'metal-karat', 'input_type' => 'select']);
        $this->karat18 = $karat->values()->create(['value' => '18K']);
        $this->karat22 = $karat->values()->create(['value' => '22K']);
        $this->gold->variants()->sync([
            $this->karat18->id => ['sort_order' => 0],
            $this->karat22->id => ['sort_order' => 1],
        ]);

        $gemstone = Attribute::create(['name' => 'Gemstone', 'slug' => 'gemstone', 'input_type' => 'select']);
        $this->diamond = $gemstone->values()->create(['value' => 'Diamond']);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'full_name' => 'Asha Verma',
            'email' => 'asha@example.com',
            'phone' => '9876543210',
            'country' => 'India',
            'type_of_jewellery_id' => $this->rings->id,
            'metal_type_id' => $this->gold->id,
            'metal_sub_type_id' => $this->karat18->id,
            'primary_gemstone_id' => $this->diamond->id,
            'occasion_name' => 'Anniversary',
            'description' => 'A solitaire ring with a thin twisted band.',
            'target_date' => now()->addMonth()->toDateString(),
            'engraving_text' => 'Forever A&R',
            'image_description' => 'Band like image 1, stone setting like image 2.',
        ], $overrides);
    }

    public function test_options_endpoint_lists_categories_metals_with_variants_and_gemstones(): void
    {
        Category::create(['name' => 'Hidden', 'slug' => 'hidden', 'status' => 'inactive']);

        $response = $this->getJson('/api/public/custom-orders/options')->assertOk();

        $this->assertSame(['Rings'], collect($response->json('data.jewellery_types'))->pluck('name')->all());
        $response->assertJsonStructure(['data' => ['jewellery_types' => [['id', 'parent_id', 'name', 'image']]]]);

        $metals = collect($response->json('data.metal_types'));
        $this->assertSame(['Gold', 'Silver'], $metals->pluck('name')->all());
        $this->assertSame(['18K', '22K'], collect($metals->firstWhere('name', 'Gold')['variants'])->pluck('name')->all());
        $this->assertSame([], $metals->firstWhere('name', 'Silver')['variants']);

        // Karat values are not metal types even though they are attribute values
        $this->assertNotContains('18K', $metals->pluck('name'));
        $this->assertSame(['Diamond'], collect($response->json('data.gemstones'))->pluck('name')->all());
    }

    public function test_metal_variants_endpoint(): void
    {
        $this->getJson("/api/public/custom-orders/metal-types/{$this->gold->id}/variants")
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.name', '18K');

        // A gemstone id is not a metal type
        $this->getJson("/api/public/custom-orders/metal-types/{$this->diamond->id}/variants")->assertNotFound();
    }

    public function test_guest_can_submit_with_uploaded_and_base64_images(): void
    {
        $base64 = 'data:image/png;base64,' . base64_encode(UploadedFile::fake()->image('b.png', 10, 10)->getContent());

        $response = $this->post('/api/public/custom-orders', $this->payload([
            'images' => [
                UploadedFile::fake()->image('a.jpg', 50, 50),
                $base64,
            ],
        ]), ['Accept' => 'application/json']);

        $response->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.type_of_jewellery.name', 'Rings')
            ->assertJsonPath('data.metal_type.value', 'Gold')
            ->assertJsonPath('data.metal_sub_type.value', '18K')
            ->assertJsonPath('data.primary_gemstone.value', 'Diamond')
            ->assertJsonPath('data.occasion_name', 'Anniversary')
            ->assertJsonMissingPath('data.admin_notes');

        $this->assertCount(2, $response->json('data.images'));
        $this->assertStringStartsWith('CO-', $response->json('data.reference_no'));
    }

    public function test_more_than_five_images_is_rejected(): void
    {
        $images = array_map(fn ($i) => UploadedFile::fake()->image("{$i}.jpg"), range(1, 6));

        $this->post('/api/public/custom-orders', $this->payload(['images' => $images]), ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('images');
    }

    public function test_plain_url_image_is_rejected(): void
    {
        $this->postJson('/api/public/custom-orders', $this->payload(['images' => ['https://example.com/x.png']]))
            ->assertUnprocessable();
    }

    public function test_variant_must_belong_to_selected_metal(): void
    {
        $this->postJson('/api/public/custom-orders', $this->payload([
            'metal_type_id' => $this->silver->id,
        ]))->assertUnprocessable()->assertJsonValidationErrors('metal_sub_type_id');
    }

    public function test_values_from_the_wrong_attribute_are_rejected(): void
    {
        $this->postJson('/api/public/custom-orders', $this->payload([
            'metal_type_id' => $this->diamond->id,
            'metal_sub_type_id' => null,
            'primary_gemstone_id' => $this->gold->id,
        ]))->assertUnprocessable()->assertJsonValidationErrors(['metal_type_id', 'primary_gemstone_id']);
    }

    public function test_inactive_category_is_rejected(): void
    {
        $this->rings->update(['status' => 'inactive']);

        $this->postJson('/api/public/custom-orders', $this->payload())
            ->assertUnprocessable()
            ->assertJsonValidationErrors('type_of_jewellery_id');
    }

    public function test_only_category_and_required_fields_are_needed(): void
    {
        $this->postJson('/api/public/custom-orders', $this->payload([
            'metal_type_id' => null,
            'metal_sub_type_id' => null,
            'primary_gemstone_id' => null,
            'occasion_name' => null,
        ]))->assertCreated();
    }

    public function test_customer_sees_own_requests(): void
    {
        $user = User::create([
            'name' => 'Asha',
            'phone' => '9876543210',
            'email' => 'asha@example.com',
            'status' => 'active',
        ]);

        $this->postJson('/api/public/custom-orders', $this->payload())->assertCreated();
        $this->postJson('/api/public/custom-orders', $this->payload(['email' => 'other@example.com']))->assertCreated();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/public/custom-orders')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.metal_type.value', 'Gold');
    }

    public function test_admin_can_manage_requests_and_metal_variants(): void
    {
        $admin = Admin::create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => bcrypt('secret'),
        ]);

        $id = $this->postJson('/api/public/custom-orders', $this->payload())->json('data.id');

        $this->actingAs($admin, 'sanctum');

        $this->getJson('/api/admin/custom-orders?per_page=10')->assertOk()->assertJsonPath('meta.total', 1);

        $this->patchJson("/api/admin/custom-orders/{$id}/status", ['status' => 'quoted', 'admin_notes' => 'Quoted 85k'])
            ->assertOk()
            ->assertJsonPath('data.status', 'quoted');

        // The same karat value can be a variant of several metals
        $this->putJson("/api/admin/attributes/values/{$this->silver->id}/variants", [
            'variant_value_ids' => [$this->karat22->id, $this->karat18->id],
        ])->assertOk()->assertJsonCount(2, 'data');

        $this->getJson("/api/public/custom-orders/metal-types/{$this->silver->id}/variants")
            ->assertOk()
            ->assertJsonPath('data.0.name', '22K');
        $this->getJson("/api/public/custom-orders/metal-types/{$this->gold->id}/variants")
            ->assertJsonCount(2, 'data');

        // A value cannot be its own variant
        $this->putJson("/api/admin/attributes/values/{$this->silver->id}/variants", [
            'variant_value_ids' => [$this->silver->id],
        ])->assertUnprocessable();

        $this->putJson("/api/admin/attributes/values/{$this->silver->id}/variants", ['variant_value_ids' => []])
            ->assertOk()
            ->assertJsonCount(0, 'data');

        // Deleting a category in use keeps the request
        $this->rings->delete();
        $this->assertDatabaseHas('custom_orders', ['id' => $id, 'type_of_jewellery_id' => null]);
    }
}
