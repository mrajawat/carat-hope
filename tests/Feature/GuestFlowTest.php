<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\Region;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GuestFlowTest extends TestCase
{
    use RefreshDatabase;

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
            'sku' => 'CH-RNG-GUEST',
            'category_id' => $category->id,
            'price' => 5000.00,
            'stock_qty' => 5,
        ]);
        ProductPrice::create(['product_id' => $this->ring->id, 'region_id' => $this->india->id, 'price' => 4500.00]);
    }

    protected function guestAdd(int $quantity = 1, ?string $token = null)
    {
        return $this->withHeaders($token ? ['X-Guest-Token' => $token] : [])
            ->postJson('/api/public/cart/items', ['product_id' => $this->ring->id, 'quantity' => $quantity]);
    }

    public function test_guest_without_token_sees_empty_cart(): void
    {
        $this->getJson('/api/public/cart')
            ->assertOk()
            ->assertJsonPath('data.items', [])
            ->assertJsonPath('data.is_guest', true)
            ->assertJsonMissingPath('data.guest_token');

        $this->assertDatabaseCount('carts', 0);
    }

    public function test_guest_first_add_issues_token_and_cart_is_reused(): void
    {
        $token = $this->guestAdd(1)
            ->assertCreated()
            ->assertJsonPath('data.is_guest', true)
            ->json('data.guest_token');

        $this->assertStringStartsWith('gc_', $token);
        $this->assertDatabaseHas('carts', ['user_id' => null, 'guest_token_hash' => hash('sha256', $token)]);

        $this->guestAdd(2, $token)
            ->assertCreated()
            ->assertJsonMissingPath('data.guest_token')
            ->assertJsonPath('data.items.0.quantity', 3);

        $this->withHeaders(['X-Guest-Token' => $token])->getJson('/api/public/cart')
            ->assertOk()
            ->assertJsonPath('data.items_count', 3);

        $this->assertDatabaseCount('carts', 1);
    }

    public function test_unknown_guest_token_gets_a_fresh_cart_on_add(): void
    {
        $newToken = $this->guestAdd(1, 'gc_pruned-or-forged')->assertCreated()->json('data.guest_token');

        $this->assertNotNull($newToken);
        $this->assertNotSame('gc_pruned-or-forged', $newToken);
    }

    public function test_guest_can_update_remove_and_clear(): void
    {
        $response = $this->guestAdd(1);
        $token = $response->json('data.guest_token');
        $itemId = $response->json('data.items.0.id');
        $headers = ['X-Guest-Token' => $token];

        $this->withHeaders($headers)->patchJson("/api/public/cart/items/{$itemId}", ['quantity' => 4])
            ->assertOk()->assertJsonPath('data.items.0.quantity', 4);

        $this->withHeaders($headers)->deleteJson("/api/public/cart/items/{$itemId}")
            ->assertOk()->assertJsonPath('data.items', []);

        $this->guestAdd(1, $token);
        $this->withHeaders($headers)->deleteJson('/api/public/cart')
            ->assertOk()->assertJsonPath('data.items', []);
    }

    public function test_guest_cannot_touch_another_carts_items(): void
    {
        $itemId = $this->guestAdd(1)->json('data.items.0.id');
        $otherToken = $this->guestAdd(1)->json('data.guest_token');

        $this->withHeaders(['X-Guest-Token' => $otherToken])
            ->patchJson("/api/public/cart/items/{$itemId}", ['quantity' => 2])
            ->assertNotFound();

        $this->deleteJson("/api/public/cart/items/{$itemId}")->assertNotFound();
    }

    public function test_login_with_guest_token_merges_guest_cart(): void
    {
        $user = User::factory()->create(['phone' => '9876543210', 'email' => 'buyer@example.com']);
        Cart::create(['user_id' => $user->id])->items()->create(['product_id' => $this->ring->id, 'quantity' => 1]);

        $guestToken = $this->guestAdd(2)->json('data.guest_token');

        $otp = $this->postJson('/api/public/login/send-otp', ['phone' => '9876543210'])->json('test_otp');
        $bearer = $this->postJson('/api/public/login/verify-otp', [
            'phone' => '9876543210',
            'otp' => (string) $otp,
            'guest_token' => $guestToken,
        ])->assertOk()->json('data.token');

        $this->withToken($bearer)->getJson('/api/public/cart')
            ->assertOk()
            ->assertJsonPath('data.is_guest', false)
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.quantity', 3);

        $this->assertDatabaseMissing('carts', ['guest_token_hash' => hash('sha256', $guestToken)]);
    }

    public function test_merge_endpoint_moves_new_lines_into_user_cart(): void
    {
        $guestToken = $this->guestAdd(2)->json('data.guest_token');

        Sanctum::actingAs(User::factory()->create(['phone' => '9000000001']));

        $this->postJson('/api/public/cart/merge', ['guest_token' => $guestToken])
            ->assertOk()
            ->assertJsonPath('message', 'Guest cart merged into your cart.')
            ->assertJsonPath('data.items.0.quantity', 2);

        $this->postJson('/api/public/cart/merge', ['guest_token' => $guestToken])
            ->assertOk()
            ->assertJsonPath('message', 'Nothing to merge.');
    }

    protected function guestCheckout(array $overrides = [])
    {
        return $this->withHeaders(['CF-IPCountry' => 'IN'])->postJson('/api/public/orders/checkout', array_merge([
            'customer_name' => 'Guest Buyer',
            'customer_email' => 'guest.buyer@example.com',
            'customer_phone' => '9111111111',
            'billing_address' => ['line1' => '1 MG Road', 'city' => 'Pune'],
            'payment_method' => 'cod',
            'items' => [['product_id' => $this->ring->id, 'quantity' => 1]],
        ], $overrides));
    }

    public function test_guest_checkout_returns_order_access_token(): void
    {
        $response = $this->guestCheckout()
            ->assertCreated()
            ->assertJsonPath('data.is_guest', true);

        $orderId = $response->json('data.order_id');
        $token = $response->json('data.order_access_token');
        $this->assertStringStartsWith('ot_', $token);

        $order = Order::find($orderId);
        $this->assertTrue($order->placed_as_guest);
        $this->assertSame(hash('sha256', $token), $order->access_token_hash);
        $this->assertTrue($order->user->is_guest);

        $this->withHeaders(['X-Order-Token' => $token])->getJson("/api/public/guest/orders/{$orderId}")
            ->assertOk()
            ->assertJsonPath('data.id', $orderId)
            ->assertJsonPath('data.order_items.0.product_name', 'Fixed Ring')
            ->assertJsonMissingPath('data.access_token_hash');

        $this->getJson("/api/public/guest/orders/{$orderId}?token={$token}")->assertOk();
    }

    public function test_guest_order_rejects_wrong_or_missing_token(): void
    {
        $orderId = $this->guestCheckout()->json('data.order_id');
        $otherToken = $this->guestCheckout(['customer_phone' => '9222222222', 'customer_email' => 'b@example.com'])
            ->json('data.order_access_token');

        $this->getJson("/api/public/guest/orders/{$orderId}")->assertNotFound();
        $this->withHeaders(['X-Order-Token' => 'ot_wrong'])->getJson("/api/public/guest/orders/{$orderId}")->assertNotFound();
        $this->withHeaders(['X-Order-Token' => $otherToken])->getJson("/api/public/guest/orders/{$orderId}")->assertNotFound();
    }

    public function test_logged_in_checkout_gets_no_order_access_token(): void
    {
        Sanctum::actingAs(User::factory()->create(['phone' => '9333333333']));

        $orderId = $this->guestCheckout()
            ->assertCreated()
            ->assertJsonPath('data.is_guest', false)
            ->assertJsonPath('data.order_access_token', null)
            ->json('data.order_id');

        $this->assertFalse(Order::find($orderId)->placed_as_guest);
    }

    public function test_register_on_guest_phone_points_to_claim_flow(): void
    {
        $this->guestCheckout()->assertCreated();

        $this->postJson('/api/public/register', ['phone' => '9111111111', 'name' => 'Guest Buyer'])
            ->assertStatus(409)
            ->assertJsonPath('code', 'guest_account_exists');

        $this->postJson('/api/public/register', ['phone' => '9444444444', 'name' => 'New', 'email' => 'guest.buyer@example.com'])
            ->assertStatus(409);
    }

    public function test_guest_claims_account_after_otp_login(): void
    {
        $this->guestCheckout()->assertCreated();

        $otp = $this->postJson('/api/public/guest/send-otp', ['phone' => '9111111111'])->json('test_otp');
        $bearer = $this->postJson('/api/public/guest/verify-otp', ['phone' => '9111111111', 'otp' => (string) $otp])
            ->assertOk()
            ->assertJsonPath('data.user.is_guest', true)
            ->json('data.token');

        $this->withToken($bearer)->getJson('/api/public/orders')->assertOk()->assertJsonCount(1, 'data');

        $this->withToken($bearer)->postJson('/api/public/guest/claim-account', [
            'name' => 'Real Name',
            'shipping_address' => '1 MG Road, Pune',
        ])
            ->assertOk()
            ->assertJsonPath('data.is_guest', false)
            ->assertJsonPath('data.name', 'Real Name')
            ->assertJsonPath('data.email', 'guest.buyer@example.com');

        $this->withToken($bearer)->postJson('/api/public/guest/claim-account', ['name' => 'Again'])
            ->assertStatus(409);
    }

    public function test_claim_account_with_new_email_resets_verification(): void
    {
        $user = User::factory()->create(['phone' => '9555555555', 'is_guest' => true, 'email_verified_at' => now()]);
        Sanctum::actingAs($user);

        $this->postJson('/api/public/guest/claim-account', ['name' => 'X', 'email' => 'new@example.com'])->assertOk();

        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_inactive_guest_cannot_log_in_with_otp(): void
    {
        User::create(['name' => 'Blocked', 'phone' => '9666666666', 'is_guest' => true, 'status' => 'inactive']);

        $otp = $this->postJson('/api/public/guest/send-otp', ['phone' => '9666666666'])->json('test_otp');

        $this->postJson('/api/public/guest/verify-otp', ['phone' => '9666666666', 'otp' => (string) $otp])
            ->assertForbidden();
    }

    public function test_prune_command_deletes_only_stale_guest_carts(): void
    {
        $user = User::factory()->create(['phone' => '9777777777']);
        $stale = Cart::create(['guest_token_hash' => hash('sha256', 'stale')]);
        $fresh = Cart::create(['guest_token_hash' => hash('sha256', 'fresh')]);
        $oldUserCart = Cart::create(['user_id' => $user->id]);
        Cart::whereIn('id', [$stale->id, $oldUserCart->id])->update(['updated_at' => now()->subDays(31)]);

        $this->artisan('carts:prune-guest')->assertSuccessful();

        $this->assertDatabaseMissing('carts', ['id' => $stale->id]);
        $this->assertDatabaseHas('carts', ['id' => $fresh->id]);
        $this->assertDatabaseHas('carts', ['id' => $oldUserCart->id]);
    }
}
