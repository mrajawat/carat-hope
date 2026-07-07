<?php

namespace Database\Seeders;

use App\Models\Region;
use Illuminate\Database\Seeder;

class RegionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Region::create([
            'name' => 'India',
            'currency_code' => 'INR',
            'currency_symbol' => '₹',
            'is_default' => true,
        ]);

        Region::create([
            'name' => 'United States',
            'currency_code' => 'USD',
            'currency_symbol' => '$',
            'is_default' => false,
        ]);

        Region::create([
            'name' => 'Rest of World',
            'currency_code' => 'USD',
            'currency_symbol' => '$',
            'is_default' => false,
        ]);
    }
}
