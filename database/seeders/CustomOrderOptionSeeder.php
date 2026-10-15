<?php

namespace Database\Seeders;

use App\Models\CustomOrderOption;
use Illuminate\Database\Seeder;

class CustomOrderOptionSeeder extends Seeder
{
    /**
     * Default dropdown values for the custom order form. Safe to re-run:
     * existing values are left untouched.
     */
    public function run(): void
    {
        $this->seedList('jewellery_type', ['Ring', 'Engagement Ring', 'Wedding Band', 'Necklace', 'Pendant', 'Earrings', 'Bracelet', 'Bangle', 'Anklet', 'Nose Pin', 'Other']);

        $metals = [
            'Gold' => ['24K Yellow Gold', '22K Yellow Gold', '18K Yellow Gold', '18K White Gold', '18K Rose Gold', '14K Yellow Gold', '14K White Gold', '14K Rose Gold', '9K Gold'],
            'Platinum' => ['Platinum 950', 'Platinum 900'],
            'Silver' => ['925 Sterling Silver', '999 Fine Silver', 'Rhodium Plated Silver', 'Gold Plated Silver'],
            'Palladium' => ['Palladium 950', 'Palladium 500'],
            'Titanium' => ['Grade 2 Titanium', 'Grade 5 Titanium'],
            'Other' => [],
        ];

        $sort = 1;
        foreach ($metals as $metalName => $subTypes) {
            $metal = CustomOrderOption::firstOrCreate(
                ['type' => 'metal_type', 'parent_id' => null, 'name' => $metalName],
                ['sort_order' => $sort++]
            );

            foreach ($subTypes as $i => $subName) {
                CustomOrderOption::firstOrCreate(
                    ['type' => 'metal_sub_type', 'parent_id' => $metal->id, 'name' => $subName],
                    ['sort_order' => $i + 1]
                );
            }
        }

        $this->seedList('gemstone', ['None', 'Diamond', 'Lab-Grown Diamond', 'Moissanite', 'Ruby', 'Emerald', 'Sapphire', 'Pearl', 'Amethyst', 'Topaz', 'Opal', 'Tanzanite', 'Aquamarine', 'Morganite', 'Garnet', 'Other']);

        $this->seedList('occasion', ['Engagement', 'Wedding', 'Anniversary', 'Birthday', 'Valentine\'s Day', 'Graduation', 'Gift', 'Self Purchase', 'Other']);
    }

    private function seedList(string $type, array $names): void
    {
        foreach ($names as $i => $name) {
            CustomOrderOption::firstOrCreate(
                ['type' => $type, 'parent_id' => null, 'name' => $name],
                ['sort_order' => $i + 1]
            );
        }
    }
}
