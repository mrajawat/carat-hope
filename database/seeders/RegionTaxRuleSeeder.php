<?php

namespace Database\Seeders;

use App\Models\Region;
use App\Models\RegionTaxRule;
use Illuminate\Database\Seeder;

class RegionTaxRuleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $india = Region::where('name', 'India')->first();

        if ($india) {
            RegionTaxRule::create([
                'region_id' => $india->id,
                'tax_name' => 'GST',
                'tax_percentage' => 3.00,
                'inclusive' => false,
            ]);
        }
    }
}
