<?php

namespace Database\Seeders;

use App\Models\Region;
use App\Models\ShippingZone;
use App\Models\ShippingMethod;
use App\Models\ShippingThreshold;
use App\Models\DeliveryEstimate;
use App\Enums\CarrierType;
use Illuminate\Database\Seeder;

class ShippingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Fetch regions
        $indiaRegion = Region::where('name', 'India')->first();
        $usRegion = Region::where('name', 'United States')->first();
        $rowRegion = Region::where('name', 'Rest of World')->first();

        // 2. Create Shipping Zones
        // Zone 1: Domestic India
        $indiaZone = ShippingZone::create([
            'region_id' => $indiaRegion?->id,
            'name' => 'Domestic India',
            'country_codes' => ['IN'],
            'is_active' => true,
        ]);

        // Zone 2: North America
        $naZone = ShippingZone::create([
            'region_id' => $usRegion?->id,
            'name' => 'North America',
            'country_codes' => ['US', 'CA', 'MX'],
            'is_active' => true,
        ]);

        // Zone 3: International EU / Rest of World
        $rowZone = ShippingZone::create([
            'region_id' => $rowRegion?->id,
            'name' => 'International EU / Rest of World',
            'country_codes' => ['GB', 'DE', 'FR', 'IT', 'ES', 'NL', 'AU', 'JP'],
            'is_active' => true,
        ]);

        // 3. Create Shipping Methods
        // Methods for Domestic India
        ShippingMethod::create([
            'shipping_zone_id' => $indiaZone->id,
            'name' => 'Standard Delivery',
            'carrier_type' => CarrierType::MANUAL,
            'carrier_name' => 'Delhivery',
            'base_rate' => 150.00,
            'insurance_percentage' => null,
            'requires_signature' => false,
            'min_transit_days' => 3,
            'max_transit_days' => 5,
            'processing_days' => 1,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        ShippingMethod::create([
            'shipping_zone_id' => $indiaZone->id,
            'name' => 'Express Air',
            'carrier_type' => CarrierType::MANUAL,
            'carrier_name' => 'BlueDart',
            'base_rate' => 350.00,
            'insurance_percentage' => null,
            'requires_signature' => true,
            'min_transit_days' => 1,
            'max_transit_days' => 2,
            'processing_days' => 1,
            'is_active' => true,
            'sort_order' => 2,
        ]);

        ShippingMethod::create([
            'shipping_zone_id' => $indiaZone->id,
            'name' => 'Insured Premium Delivery',
            'carrier_type' => CarrierType::MANUAL,
            'carrier_name' => 'DTDC Secure',
            'base_rate' => 500.00,
            'insurance_percentage' => 1.50, // 1.5% insurance
            'requires_signature' => true,
            'min_transit_days' => 2,
            'max_transit_days' => 4,
            'processing_days' => 1,
            'is_active' => true,
            'sort_order' => 3,
        ]);

        // Methods for North America
        ShippingMethod::create([
            'shipping_zone_id' => $naZone->id,
            'name' => 'USPS First Class',
            'carrier_type' => CarrierType::MANUAL,
            'carrier_name' => 'USPS',
            'base_rate' => 15.00,
            'insurance_percentage' => null,
            'requires_signature' => false,
            'min_transit_days' => 5,
            'max_transit_days' => 7,
            'processing_days' => 2,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        ShippingMethod::create([
            'shipping_zone_id' => $naZone->id,
            'name' => 'FedEx Priority',
            'carrier_type' => CarrierType::MANUAL,
            'carrier_name' => 'FedEx',
            'base_rate' => 45.00,
            'insurance_percentage' => null,
            'requires_signature' => true,
            'min_transit_days' => 2,
            'max_transit_days' => 3,
            'processing_days' => 1,
            'is_active' => true,
            'sort_order' => 2,
        ]);

        ShippingMethod::create([
            'shipping_zone_id' => $naZone->id,
            'name' => 'Insured Premium Direct',
            'carrier_type' => CarrierType::MANUAL,
            'carrier_name' => 'DHL Express',
            'base_rate' => 60.00,
            'insurance_percentage' => 2.00, // 2% insurance
            'requires_signature' => true,
            'min_transit_days' => 2,
            'max_transit_days' => 4,
            'processing_days' => 1,
            'is_active' => true,
            'sort_order' => 3,
        ]);

        // Methods for International Rest of World
        ShippingMethod::create([
            'shipping_zone_id' => $rowZone->id,
            'name' => 'Standard International',
            'carrier_type' => CarrierType::MANUAL,
            'carrier_name' => 'DHL Global',
            'base_rate' => 25.00,
            'insurance_percentage' => null,
            'requires_signature' => false,
            'min_transit_days' => 7,
            'max_transit_days' => 12,
            'processing_days' => 2,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        ShippingMethod::create([
            'shipping_zone_id' => $rowZone->id,
            'name' => 'Express International Insured',
            'carrier_type' => CarrierType::MANUAL,
            'carrier_name' => 'DHL Express',
            'base_rate' => 75.00,
            'insurance_percentage' => 2.50, // 2.5% insurance
            'requires_signature' => true,
            'min_transit_days' => 3,
            'max_transit_days' => 5,
            'processing_days' => 1,
            'is_active' => true,
            'sort_order' => 2,
        ]);

        // 4. Create Shipping Thresholds (Free Shipping Rules)
        ShippingThreshold::create([
            'shipping_zone_id' => $indiaZone->id,
            'min_order_value' => 5000.00,
            'currency' => 'INR',
        ]);

        ShippingThreshold::create([
            'shipping_zone_id' => $naZone->id,
            'min_order_value' => 150.00,
            'currency' => 'USD',
        ]);

        ShippingThreshold::create([
            'shipping_zone_id' => $rowZone->id,
            'min_order_value' => 300.00,
            'currency' => 'USD',
        ]);

        // 5. Create Delivery Estimates
        // Pincode prefixes for major Indian metros
        // Mumbai: 400xxx
        DeliveryEstimate::create([
            'shipping_zone_id' => $indiaZone->id,
            'pincode_prefix' => '400',
            'min_days' => 1,
            'max_days' => 2,
            'cutoff_time' => '15:00:00',
        ]);

        // Delhi: 110xxx
        DeliveryEstimate::create([
            'shipping_zone_id' => $indiaZone->id,
            'pincode_prefix' => '110',
            'min_days' => 2,
            'max_days' => 3,
            'cutoff_time' => '14:00:00',
        ]);

        // Jaipur: 302xxx
        DeliveryEstimate::create([
            'shipping_zone_id' => $indiaZone->id,
            'pincode_prefix' => '302',
            'min_days' => 2,
            'max_days' => 3,
            'cutoff_time' => '14:00:00',
        ]);

        // Fallback for India zone
        DeliveryEstimate::create([
            'shipping_zone_id' => $indiaZone->id,
            'pincode_prefix' => null,
            'min_days' => 3,
            'max_days' => 5,
            'cutoff_time' => '13:00:00',
        ]);

        // North America zone fallback
        DeliveryEstimate::create([
            'shipping_zone_id' => $naZone->id,
            'pincode_prefix' => null,
            'min_days' => 3,
            'max_days' => 6,
            'cutoff_time' => '12:00:00',
        ]);

        // RoW zone fallback
        DeliveryEstimate::create([
            'shipping_zone_id' => $rowZone->id,
            'pincode_prefix' => null,
            'min_days' => 5,
            'max_days' => 10,
            'cutoff_time' => '11:00:00',
        ]);
    }
}
