<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Order;
use App\Models\Region;
use App\Models\ShippingZone;
use App\Models\ShippingMethod;
use App\Models\ShippingThreshold;
use App\Models\DeliveryEstimate;
use App\Models\Shipment;
use App\Models\User;
use App\Enums\CarrierType;
use App\Enums\ShipmentStatus;
use App\Services\ShippingZoneService;
use App\Services\ShippingRateService;
use App\Services\DeliveryEstimateService;
use App\Services\ShipmentService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ShippingDeliveryTest extends TestCase
{
    use RefreshDatabase;

    protected Region $indiaRegion;
    protected Region $usRegion;
    protected ShippingZone $indiaZone;
    protected ShippingZone $usZone;

    protected function setUp(): void
    {
        parent::setUp();

        // Create standard regions
        $this->indiaRegion = Region::create([
            'name' => 'India',
            'currency_code' => 'INR',
            'currency_symbol' => '₹',
            'is_default' => true,
        ]);

        $this->usRegion = Region::create([
            'name' => 'United States',
            'currency_code' => 'USD',
            'currency_symbol' => '$',
            'is_default' => false,
        ]);

        // Create standard shipping zones
        $this->indiaZone = ShippingZone::create([
            'region_id' => $this->indiaRegion->id,
            'name' => 'Domestic India',
            'country_codes' => ['IN'],
            'is_active' => true,
        ]);

        $this->usZone = ShippingZone::create([
            'region_id' => $this->usRegion->id,
            'name' => 'North America',
            'country_codes' => ['US', 'CA'],
            'is_active' => true,
        ]);
    }

    /**
     * Test Shipping Zone Resolution.
     */
    public function test_shipping_zone_resolution(): void
    {
        $zoneService = app(ShippingZoneService::class);

        // Resolve India
        $resolved = $zoneService->resolveZoneForAddress('IN', '400001');
        $this->assertEquals($this->indiaZone->id, $resolved->id);

        // Resolve US
        $resolvedUs = $zoneService->resolveZoneForAddress('US', '10001');
        $this->assertEquals($this->usZone->id, $resolvedUs->id);

        // Expect Exception for unknown region
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        $zoneService->resolveZoneForAddress('XY', '12345');
    }

    /**
     * Test Shipping Rate Calculation including Insurance and Thresholds.
     */
    public function test_shipping_rate_calculation_and_free_shipping(): void
    {
        $rateService = app(ShippingRateService::class);

        // Create Method for India
        $method = ShippingMethod::create([
            'shipping_zone_id' => $this->indiaZone->id,
            'name' => 'Express Air',
            'carrier_type' => CarrierType::MANUAL,
            'carrier_name' => 'BlueDart',
            'base_rate' => 300.00,
            'insurance_percentage' => 2.00,
            'requires_signature' => true,
            'min_transit_days' => 1,
            'max_transit_days' => 2,
            'processing_days' => 1,
            'is_active' => true,
        ]);

        // Create Free Shipping Threshold above 5000 INR
        ShippingThreshold::create([
            'shipping_zone_id' => $this->indiaZone->id,
            'min_order_value' => 5000.00,
            'currency' => 'INR',
        ]);

        // Calculate for order under threshold (1000 INR)
        $cost1 = $rateService->calculateCost($method, 1000.00, 'INR');
        $this->assertEquals(300.00, $cost1['shipping_cost']);
        $this->assertEquals(20.00, $cost1['insurance_cost']); // 2% of 1000
        $this->assertEquals(320.00, $cost1['total']);

        // Calculate for order over threshold (6000 INR)
        $cost2 = $rateService->calculateCost($method, 6000.00, 'INR');
        $this->assertEquals(0.00, $cost2['shipping_cost']); // Free shipping
        $this->assertEquals(120.00, $cost2['insurance_cost']); // 2% of 6000
        $this->assertEquals(120.00, $cost2['total']);
    }

    /**
     * Test High Value Insurance Filter.
     */
    public function test_high_value_insurance_filter(): void
    {
        $rateService = app(ShippingRateService::class);

        // Set config
        config(['shipping.high_value_thresholds.INR' => 50000.00]);

        // Create standard method (no signature, no insurance)
        $standard = ShippingMethod::create([
            'shipping_zone_id' => $this->indiaZone->id,
            'name' => 'Standard',
            'carrier_type' => CarrierType::MANUAL,
            'base_rate' => 100.00,
            'insurance_percentage' => null,
            'requires_signature' => false,
            'min_transit_days' => 3,
            'max_transit_days' => 5,
            'processing_days' => 1,
            'is_active' => true,
        ]);

        // Create premium method (requires signature, has insurance)
        $premium = ShippingMethod::create([
            'shipping_zone_id' => $this->indiaZone->id,
            'name' => 'Premium Secured',
            'carrier_type' => CarrierType::MANUAL,
            'base_rate' => 400.00,
            'insurance_percentage' => 1.50,
            'requires_signature' => true,
            'min_transit_days' => 2,
            'max_transit_days' => 3,
            'processing_days' => 1,
            'is_active' => true,
        ]);

        // For small order (1000 INR), both are eligible
        $eligibleSmall = $rateService->getEligibleMethods($this->indiaZone, 1000.00, 'INR');
        $this->assertCount(2, $eligibleSmall);

        // For large order (60000 INR), only premium is eligible
        $eligibleLarge = $rateService->getEligibleMethods($this->indiaZone, 60000.00, 'INR');
        $this->assertCount(1, $eligibleLarge);
        $this->assertEquals('Premium Secured', $eligibleLarge->first()->name);
    }

    /**
     * Test Delivery Estimate Calculations.
     */
    public function test_delivery_estimates_excluding_sundays_and_cutoff(): void
    {
        $estimateService = app(DeliveryEstimateService::class);

        // Seed estimates for India zone
        DeliveryEstimate::create([
            'shipping_zone_id' => $this->indiaZone->id,
            'pincode_prefix' => '400',
            'min_days' => 1,
            'max_days' => 2,
            'cutoff_time' => '14:00:00',
        ]);

        DeliveryEstimate::create([
            'shipping_zone_id' => $this->indiaZone->id,
            'pincode_prefix' => null,
            'min_days' => 3,
            'max_days' => 5,
            'cutoff_time' => '12:00:00',
        ]);

        // Case 1: Friday 10:00 AM (before cutoff) - Pincode 400001
        $fridayMorning = Carbon::parse('2026-06-05 10:00:00'); // Friday
        $est1 = $estimateService->estimate($this->indiaZone, '400001', $fridayMorning, 1);
        
        $this->assertEquals('2026-06-08', $est1['min_date']); // Monday (Sunday skipped)
        $this->assertEquals('2026-06-09', $est1['max_date']); // Tuesday

        // Case 2: Friday 3:00 PM (after cutoff) - Pincode 400001
        $fridayAfternoon = Carbon::parse('2026-06-05 15:00:00'); // Friday
        $est2 = $estimateService->estimate($this->indiaZone, '400001', $fridayAfternoon, 1);

        $this->assertEquals('2026-06-09', $est2['min_date']); // Tuesday
        $this->assertEquals('2026-06-10', $est2['max_date']); // Wednesday
    }

    /**
     * Test Shipment Status Transitions Validation.
     */
    public function test_shipment_status_transitions(): void
    {
        $shipmentService = app(ShipmentService::class);

        $user = User::create([
            'name' => 'Test Customer',
            'email' => 'customer@example.com',
            'phone' => '+919999999999',
            'password' => bcrypt('password123') ?? 'password',
        ]);

        $order = Order::create([
            'user_id' => $user->id,
            'customer_name' => 'Rahul Sharma',
            'customer_email' => 'rahul@example.com',
            'customer_phone' => '+919876543210',
            'total_amount' => 1000.00,
            'payment_status' => 'paid',
            'order_status' => 'processing',
            'shipping_address' => ['country_code' => 'IN', 'pincode' => '400001'],
        ]);

        $method = ShippingMethod::create([
            'shipping_zone_id' => $this->indiaZone->id,
            'name' => 'Standard',
            'carrier_type' => CarrierType::MANUAL,
            'base_rate' => 100.00,
            'min_transit_days' => 3,
            'max_transit_days' => 5,
            'processing_days' => 1,
            'is_active' => true,
        ]);

        $shipment = $shipmentService->createShipment($order, $method);
        $this->assertEquals(ShipmentStatus::PENDING->value, $shipment->status->value);

        // Valid: pending -> processing
        $shipment = $shipmentService->updateStatus($shipment, 'processing');
        $this->assertEquals(ShipmentStatus::PROCESSING->value, $shipment->status->value);

        // Valid: processing -> dispatched
        $shipment = $shipmentService->updateStatus($shipment, 'dispatched');
        $this->assertEquals(ShipmentStatus::DISPATCHED->value, $shipment->status->value);
        $this->assertNotNull($shipment->dispatched_at);

        // Invalid: dispatched -> processing (backwards)
        $this->expectException(\InvalidArgumentException::class);
        $shipmentService->updateStatus($shipment, 'processing');
    }

    /**
     * Test Shipping Estimates API Endpoint.
     */
    public function test_api_get_shipping_estimates(): void
    {
        ShippingMethod::create([
            'shipping_zone_id' => $this->indiaZone->id,
            'name' => 'Standard',
            'carrier_type' => CarrierType::MANUAL,
            'base_rate' => 100.00,
            'min_transit_days' => 3,
            'max_transit_days' => 5,
            'processing_days' => 1,
            'is_active' => true,
        ]);

        DeliveryEstimate::create([
            'shipping_zone_id' => $this->indiaZone->id,
            'pincode_prefix' => null,
            'min_days' => 3,
            'max_days' => 5,
            'cutoff_time' => '13:00:00',
        ]);

        $payload = [
            'country_code' => 'IN',
            'pincode' => '400001',
            'order_value' => 2000.00,
            'currency' => 'INR',
        ];

        $response = $this->postJson('/api/public/shipping/estimate', $payload);

        $response->assertStatus(200);
        $response->assertJsonPath('status', true);
        $response->assertJsonStructure([
            'status',
            'message',
            'data' => [
                '*' => [
                    'id',
                    'name',
                    'carrier_type',
                    'carrier_name',
                    'requires_signature',
                    'cost' => [
                        'shipping_cost',
                        'insurance_cost',
                        'total',
                    ],
                    'estimated_delivery' => [
                        'min_date',
                        'max_date',
                        'min_days',
                        'max_days',
                    ],
                ]
            ]
        ]);
    }

    /**
     * Test Admin Actions - Attach Tracking and Update Status.
     */
    public function test_admin_actions_api(): void
    {
        $admin = Admin::create([
            'name' => 'Super Admin',
            'email' => 'admin@caratehope.com',
            'password' => 'password123',
            'role' => 'super_admin',
        ]);

        Sanctum::actingAs($admin);

        $user = User::create([
            'name' => 'Test Customer',
            'email' => 'customer@example.com',
            'phone' => '+919999999999',
            'password' => bcrypt('password123') ?? 'password',
        ]);

        $order = Order::create([
            'user_id' => $user->id,
            'customer_name' => 'Rahul Sharma',
            'customer_email' => 'rahul@example.com',
            'customer_phone' => '+919876543210',
            'total_amount' => 1000.00,
            'payment_status' => 'paid',
            'order_status' => 'processing',
            'shipping_address' => ['country_code' => 'IN', 'pincode' => '400001'],
        ]);

        $method = ShippingMethod::create([
            'shipping_zone_id' => $this->indiaZone->id,
            'name' => 'Standard',
            'carrier_type' => CarrierType::MANUAL,
            'base_rate' => 100.00,
            'min_transit_days' => 3,
            'max_transit_days' => 5,
            'processing_days' => 1,
            'is_active' => true,
        ]);

        $shipment = app(ShipmentService::class)->createShipment($order, $method);

        // Test attaching tracking number
        $trackPayload = [
            'tracking_number' => 'AWB12345678',
            'carrier_name' => 'Delhivery',
        ];

        $response = $this->postJson("/api/admin/shipments/{$shipment->id}/tracking-number", $trackPayload);
        $response->assertStatus(200);
        $response->assertJsonPath('status', true);
        $this->assertDatabaseHas('shipments', [
            'id' => $shipment->id,
            'tracking_number' => 'AWB12345678',
            'status' => ShipmentStatus::DISPATCHED->value,
        ]);

        // Test updating status to in_transit
        $statusPayload = [
            'status' => 'in_transit',
            'location' => 'Mumbai HUB',
            'description' => 'Shipment departed from Mumbai HUB.',
        ];

        $response = $this->postJson("/api/admin/shipments/{$shipment->id}/status", $statusPayload);
        $response->assertStatus(200);
        $response->assertJsonPath('status', true);
        $this->assertDatabaseHas('shipments', [
            'id' => $shipment->id,
            'status' => ShipmentStatus::IN_TRANSIT->value,
        ]);
        $this->assertDatabaseHas('shipment_tracking_events', [
            'shipment_id' => $shipment->id,
            'status' => ShipmentStatus::IN_TRANSIT->value,
            'location' => 'Mumbai HUB',
        ]);
    }
}
