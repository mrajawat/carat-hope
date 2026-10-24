<?php

namespace Database\Seeders;

use App\Models\Attribute;
use Illuminate\Database\Seeder;

class JewelryAttributeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Safe to re-run: attributes are matched by slug and values by name, so
     * existing rows (and any admin edits to them) are kept.
     */
    public function run(): void
    {
        // 1. Metal Type
        $metalType = $this->seedAttribute([
            'name' => 'Metal Type',
            'slug' => 'metal-type',
            'input_type' => 'select',
            'unit' => null,
            'affects_price' => true,
        ], [
            ['value' => 'Yellow Gold', 'price_modifier' => 0.00, 'sort_order' => 1],
            ['value' => 'White Gold', 'price_modifier' => 500.00, 'sort_order' => 2],
            ['value' => 'Rose Gold', 'price_modifier' => 400.00, 'sort_order' => 3],
            ['value' => 'Platinum', 'price_modifier' => 5000.00, 'sort_order' => 4],
            ['value' => 'Sterling Silver', 'price_modifier' => -2000.00, 'sort_order' => 5],
        ]);

        // 2. Metal Karat
        $metalKarat = $this->seedAttribute([
            'name' => 'Metal Karat',
            'slug' => 'metal-karat',
            'input_type' => 'select',
            'unit' => null,
            'affects_price' => true,
        ], [
            ['value' => '14K', 'price_modifier' => 0.00, 'sort_order' => 1],
            ['value' => '18K', 'price_modifier' => 8000.00, 'sort_order' => 2],
            ['value' => '22K', 'price_modifier' => 15000.00, 'sort_order' => 3],
        ]);

        // Gold metals offer the karats as variants on the custom order form
        $karats = $metalKarat->values()->orderBy('sort_order')->pluck('id')
            ->mapWithKeys(fn ($id, $index) => [$id => ['sort_order' => $index]])
            ->all();
        $metalType->values()
            ->whereIn('value', ['Yellow Gold', 'White Gold', 'Rose Gold'])
            ->get()
            ->each(fn ($gold) => $gold->variants()->sync($karats));

        // 3. Metal Color
        $this->seedAttribute([
            'name' => 'Metal Color',
            'slug' => 'metal-color',
            'input_type' => 'select',
            'unit' => null,
            'affects_price' => false,
        ], [
            ['value' => 'Yellow', 'price_modifier' => 0.00, 'sort_order' => 1],
            ['value' => 'White', 'price_modifier' => 0.00, 'sort_order' => 2],
            ['value' => 'Rose', 'price_modifier' => 0.00, 'sort_order' => 3],
        ]);

        // 4. Ring Size
        $this->seedAttribute([
            'name' => 'Ring Size',
            'slug' => 'ring-size',
            'input_type' => 'number',
            'unit' => 'mm',
            'affects_price' => true,
        ], [
            ['value' => '5', 'price_modifier' => 0.00, 'sort_order' => 1],
            ['value' => '6', 'price_modifier' => 0.00, 'sort_order' => 2],
            ['value' => '7', 'price_modifier' => 0.00, 'sort_order' => 3],
            ['value' => '8', 'price_modifier' => 200.00, 'sort_order' => 4],
            ['value' => '9', 'price_modifier' => 300.00, 'sort_order' => 5],
        ]);

        // 5. Chain Length
        $this->seedAttribute([
            'name' => 'Chain Length',
            'slug' => 'chain-length',
            'input_type' => 'select',
            'unit' => 'inches',
            'affects_price' => true,
        ], [
            ['value' => '16', 'price_modifier' => 0.00, 'sort_order' => 1],
            ['value' => '18', 'price_modifier' => 500.00, 'sort_order' => 2],
            ['value' => '20', 'price_modifier' => 1000.00, 'sort_order' => 3],
        ]);

        // 6. Gemstone
        $this->seedAttribute([
            'name' => 'Gemstone',
            'slug' => 'gemstone',
            'input_type' => 'select',
            'unit' => null,
            'affects_price' => true,
        ], [
            ['value' => 'Diamond', 'price_modifier' => 50000.00, 'sort_order' => 1],
            ['value' => 'Sapphire', 'price_modifier' => 15000.00, 'sort_order' => 2],
            ['value' => 'Emerald', 'price_modifier' => 20000.00, 'sort_order' => 3],
            ['value' => 'Ruby', 'price_modifier' => 18000.00, 'sort_order' => 4],
            ['value' => 'Pearl', 'price_modifier' => 2000.00, 'sort_order' => 5],
        ]);

        // 7. Stone Clarity
        $this->seedAttribute([
            'name' => 'Stone Clarity',
            'slug' => 'stone-clarity',
            'input_type' => 'select',
            'unit' => null,
            'affects_price' => true,
        ], [
            ['value' => 'SI', 'price_modifier' => 0.00, 'sort_order' => 1],
            ['value' => 'VS', 'price_modifier' => 5000.00, 'sort_order' => 2],
            ['value' => 'VVS', 'price_modifier' => 12000.00, 'sort_order' => 3],
            ['value' => 'IF', 'price_modifier' => 25000.00, 'sort_order' => 4],
        ]);
    }

    private function seedAttribute(array $attribute, array $values): Attribute
    {
        $model = Attribute::firstOrCreate(['slug' => $attribute['slug']], $attribute);

        foreach ($values as $value) {
            $model->values()->firstOrCreate(['value' => $value['value']], $value);
        }

        return $model;
    }
}
