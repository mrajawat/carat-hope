<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\CustomOrder;
use App\Models\CustomOrderOption;
use App\Models\User;
use Database\Seeders\CustomOrderOptionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CustomOrderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->seed(CustomOrderOptionSeeder::class);
    }

    private function option(string $type, string $name): CustomOrderOption
    {
        return CustomOrderOption::where('type', $type)->where('name', $name)->firstOrFail();
    }

    private function payload(array $overrides = []): array
    {
        $gold = $this->option('metal_type', 'Gold');

        return array_merge([
            'full_name' => 'Asha Verma',
            'email' => 'asha@example.com',
            'phone' => '9876543210',
            'country' => 'India',
            'type_of_jewellery_id' => $this->option('jewellery_type', 'Ring')->id,
            'metal_type_id' => $gold->id,
            'metal_sub_type_id' => $this->option('metal_sub_type', '18K Rose Gold')->id,
            'primary_gemstone_id' => $this->option('gemstone', 'Diamond')->id,
            'occasion_id' => $this->option('occasion', 'Anniversary')->id,
            'description' => 'A solitaire ring with a thin twisted band.',
            'target_date' => now()->addMonth()->toDateString(),
            'engraving_text' => 'Forever A&R',
            'image_description' => 'Band like image 1, stone setting like image 2.',
        ], $overrides);
    }

    public function test_options_endpoint_groups_metal_sub_types_under_metal(): void
    {
        $response = $this->getJson('/api/public/custom-orders/options')->assertOk();

        $metals = collect($response->json('data.metal_types'));
        $gold = $metals->firstWhere('name', 'Gold');

        $this->assertNotNull($gold);
        $this->assertContains('18K Rose Gold', collect($gold['sub_types'])->pluck('name'));
        $this->assertNotEmpty($response->json('data.jewellery_types'));
        $this->assertNotEmpty($response->json('data.gemstones'));
        $this->assertNotEmpty($response->json('data.occasions'));
    }

    public function test_metal_sub_types_endpoint(): void
    {
        $platinum = $this->option('metal_type', 'Platinum');

        $this->getJson("/api/public/custom-orders/metal-types/{$platinum->id}/sub-types")
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->getJson('/api/public/custom-orders/options/metal_sub_type?parent_id=' . $platinum->id)
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->getJson('/api/public/custom-orders/options/unknown')->assertNotFound();
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
            ->assertJsonPath('data.metal_sub_type.name', '18K Rose Gold')
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

    public function test_sub_type_must_belong_to_selected_metal(): void
    {
        $this->postJson('/api/public/custom-orders', $this->payload([
            'metal_type_id' => $this->option('metal_type', 'Silver')->id,
        ]))->assertUnprocessable()->assertJsonValidationErrors('metal_sub_type_id');
    }

    public function test_inactive_option_is_rejected(): void
    {
        $ring = $this->option('jewellery_type', 'Ring');
        $ring->update(['status' => 'inactive']);

        $this->postJson('/api/public/custom-orders', $this->payload())
            ->assertUnprocessable()
            ->assertJsonValidationErrors('type_of_jewellery_id');
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
            ->assertJsonCount(1, 'data');
    }

    public function test_admin_can_manage_requests_and_options(): void
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

        // Options in use cannot be deleted
        $ring = $this->option('jewellery_type', 'Ring');
        $this->deleteJson("/api/admin/custom-order-options/{$ring->id}")->assertUnprocessable();

        $gold = $this->option('metal_type', 'Gold');
        $this->postJson('/api/admin/custom-order-options', [
            'type' => 'metal_sub_type',
            'parent_id' => $gold->id,
            'name' => '10K Yellow Gold',
        ])->assertCreated();

        // Duplicate name under same metal is rejected
        $this->postJson('/api/admin/custom-order-options', [
            'type' => 'metal_sub_type',
            'parent_id' => $gold->id,
            'name' => '10K Yellow Gold',
        ])->assertUnprocessable();

        $this->getJson("/api/public/custom-orders/metal-types/{$gold->id}/sub-types")
            ->assertOk()
            ->assertJsonFragment(['name' => '10K Yellow Gold']);
    }
}
