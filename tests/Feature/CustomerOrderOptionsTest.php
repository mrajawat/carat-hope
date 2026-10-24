<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CustomerOrderOptionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_options_are_public()
    {
        $response = $this->getJson('/api/public/orders/options');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => [
                'order_statuses' => [['value', 'label']],
                'payment_statuses' => [['value', 'label']],
                'payment_methods' => [['value', 'label']],
                'shipment_statuses' => [['value', 'label']],
                'sort_options' => [['value', 'label']],
            ]])
            ->assertJsonFragment(['value' => 'cod', 'label' => 'Cash on Delivery'])
            ->assertJsonFragment(['value' => 'out_for_delivery', 'label' => 'Out For Delivery']);
    }

    public function test_customer_orders_can_be_filtered_and_sorted()
    {
        $user = User::factory()->create();
        $make = fn ($status, $total) => Order::create([
            'user_id' => $user->id,
            'total_amount' => $total,
            'currency' => 'INR',
            'payment_status' => 'unpaid',
            'order_status' => $status,
        ]);
        $make('pending', 100);
        $make('delivered', 500);
        $make('delivered', 300);

        Sanctum::actingAs($user);

        $this->getJson('/api/public/orders?status=delivered&sort=amount_low')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.total_amount', '300.00')
            ->assertJsonPath('data.1.total_amount', '500.00');

        $this->getJson('/api/public/orders?status=bogus')->assertStatus(422);
    }
}
