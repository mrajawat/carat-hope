<?php

namespace Database\Seeders;

use App\Models\ProcessingProfile;
use App\Models\ShippingProfile;
use Illuminate\Database\Seeder;

/**
 * Seeds the reusable profiles behind the "Delivery, processing, and returns"
 * section: how long an item takes to prepare, and where/how it ships from.
 *
 * Idempotent - re-running updates rather than duplicating.
 */
class ProfileSeeder extends Seeder
{
    public function run(): void
    {
        $processing = [
            ['name' => 'Ready to dispatch', 'min_days' => 1, 'max_days' => 2, 'is_default' => true],
            ['name' => 'Made to order', 'min_days' => 3, 'max_days' => 5, 'is_default' => false],
            ['name' => 'Custom / bespoke', 'min_days' => 7, 'max_days' => 14, 'is_default' => false],
        ];

        foreach ($processing as $profile) {
            ProcessingProfile::updateOrCreate(
                ['name' => $profile['name']],
                $profile + ['is_active' => true]
            );
        }

        $shipping = [
            [
                'name' => 'Free shipping',
                'origin_pincode' => '302001',
                'origin_country_code' => 'IN',
                'is_default' => true,
            ],
            [
                'name' => 'Standard shipping',
                'origin_pincode' => '302001',
                'origin_country_code' => 'IN',
                'is_default' => false,
            ],
        ];

        foreach ($shipping as $profile) {
            ShippingProfile::updateOrCreate(
                ['name' => $profile['name']],
                $profile + ['is_active' => true]
            );
        }
    }
}
