<?php

namespace Database\Seeders;

use App\Models\Attribute;
use Illuminate\Database\Seeder;

class JewelryAttributeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Metal Type
        $metalType = Attribute::create([
            'name' => 'Metal Type',
            'slug' => 'metal-type',
            'input_type' => 'select',
            'unit' => null,
            'affects_price' => true,
        ]);
        $metalType->values()->createMany([
            ['value' => 'Yellow Gold', 'price_modifier' => 0.00, 'sort_order' => 1],
            ['value' => 'White Gold', 'price_modifier' => 500.00, 'sort_order' => 2],
            ['value' => 'Rose Gold', 'price_modifier' => 400.00, 'sort_order' => 3],
            ['value' => 'Platinum', 'price_modifier' => 5000.00, 'sort_order' => 4],
            ['value' => 'Sterling Silver', 'price_modifier' => -2000.00, 'sort_order' => 5],
        ]);

        // 2. Metal Karat
        $metalKarat = Attribute::create([
            'name' => 'Metal Karat',
            'slug' => 'metal-karat',
            'input_type' => 'select',
            'unit' => null,
            'affects_price' => true,
        ]);
        $metalKarat->values()->createMany([
            ['value' => '14K', 'price_modifier' => 0.00, 'sort_order' => 1],
            ['value' => '18K', 'price_modifier' => 8000.00, 'sort_order' => 2],
            ['value' => '22K', 'price_modifier' => 15000.00, 'sort_order' => 3],
        ]);

        // 3. Metal Color
        $metalColor = Attribute::create([
            'name' => 'Metal Color',
            'slug' => 'metal-color',
            'input_type' => 'select',
            'unit' => null,
            'affects_price' => false,
        ]);
        $metalColor->values()->createMany([
            ['value' => 'Yellow', 'price_modifier' => 0.00, 'sort_order' => 1],
            ['value' => 'White', 'price_modifier' => 0.00, 'sort_order' => 2],
            ['value' => 'Rose', 'price_modifier' => 0.00, 'sort_order' => 3],
        ]);

        // 4. Ring Size
        $ringSize = Attribute::create([
            'name' => 'Ring Size',
            'slug' => 'ring-size',
            'input_type' => 'number',
            'unit' => 'mm',
            'affects_price' => true,
        ]);
        $ringSize->values()->createMany([
            ['value' => '5', 'price_modifier' => 0.00, 'sort_order' => 1],
            ['value' => '6', 'price_modifier' => 0.00, 'sort_order' => 2],
            ['value' => '7', 'price_modifier' => 0.00, 'sort_order' => 3],
            ['value' => '8', 'price_modifier' => 200.00, 'sort_order' => 4],
            ['value' => '9', 'price_modifier' => 300.00, 'sort_order' => 5],
        ]);

        // 5. Chain Length
        $chainLength = Attribute::create([
            'name' => 'Chain Length',
            'slug' => 'chain-length',
            'input_type' => 'select',
            'unit' => 'inches',
            'affects_price' => true,
        ]);
        $chainLength->values()->createMany([
            ['value' => '16', 'price_modifier' => 0.00, 'sort_order' => 1],
            ['value' => '18', 'price_modifier' => 500.00, 'sort_order' => 2],
            ['value' => '20', 'price_modifier' => 1000.00, 'sort_order' => 3],
        ]);

        // 6. Gemstone
        $gemstone = Attribute::create([
            'name' => 'Gemstone',
            'slug' => 'gemstone',
            'input_type' => 'select',
            'unit' => null,
            'affects_price' => true,
        ]);
        $gemstone->values()->createMany([
            ['value' => 'Diamond', 'price_modifier' => 50000.00, 'sort_order' => 1],
            ['value' => 'Sapphire', 'price_modifier' => 15000.00, 'sort_order' => 2],
            ['value' => 'Emerald', 'price_modifier' => 20000.00, 'sort_order' => 3],
            ['value' => 'Ruby', 'price_modifier' => 18000.00, 'sort_order' => 4],
            ['value' => 'Pearl', 'price_modifier' => 2000.00, 'sort_order' => 5],
        ]);

        // 7. Stone Clarity
        $stoneClarity = Attribute::create([
            'name' => 'Stone Clarity',
            'slug' => 'stone-clarity',
            'input_type' => 'select',
            'unit' => null,
            'affects_price' => true,
        ]);
        $stoneClarity->values()->createMany([
            ['value' => 'SI', 'price_modifier' => 0.00, 'sort_order' => 1],
            ['value' => 'VS', 'price_modifier' => 5000.00, 'sort_order' => 2],
            ['value' => 'VVS', 'price_modifier' => 12000.00, 'sort_order' => 3],
            ['value' => 'IF', 'price_modifier' => 25000.00, 'sort_order' => 4],
        ]);
    }
}
