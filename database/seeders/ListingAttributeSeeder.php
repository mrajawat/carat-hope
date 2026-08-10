<?php

namespace Database\Seeders;

use App\Models\Attribute;
use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Seeds the attribute master shown on the Item Options screen.
 *
 * Three groups:
 *  - global      : appear for every category (Materials, Gold purity, Recycled, ...)
 *  - per-category: linked via category_attributes, so Rings show "Ring size" while
 *                  Pendant Necklaces show "Pendant width/height"
 *  - variation   : can_be_variation = true, so they also appear in the
 *                  "What type of variation is it?" picker
 *
 * Idempotent - re-running updates rather than duplicating.
 */
class ListingAttributeSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->definitions() as $definition) {
            $values = $definition['values'] ?? [];
            $categories = $definition['categories'] ?? [];
            unset($definition['values'], $definition['categories']);

            $definition['slug'] ??= Str::slug($definition['name']);

            $attribute = Attribute::updateOrCreate(
                ['slug' => $definition['slug']],
                $definition
            );

            $this->syncValues($attribute, $values);
            $this->syncCategories($attribute, $categories);
        }
    }

    /**
     * @param array $values Either ['Ametrine', ...] or ['US' => ['4', '5'], ...] keyed by scale
     */
    private function syncValues(Attribute $attribute, array $values): void
    {
        $sortOrder = 0;

        foreach ($values as $scale => $entries) {
            $scoped = is_string($scale) ? $scale : null;

            foreach ((array) $entries as $value) {
                $attribute->values()->updateOrCreate(
                    ['value' => (string) $value, 'scale' => $scoped],
                    ['sort_order' => $sortOrder++]
                );
            }
        }
    }

    private function syncCategories(Attribute $attribute, array $categoryNames): void
    {
        // Attributes that declare no categories are global; leave any manual
        // links alone rather than wiping them.
        if (empty($categoryNames)) {
            return;
        }

        $ids = Category::whereIn('name', $categoryNames)->pluck('id');

        if ($ids->isNotEmpty()) {
            // Authoritative: the definition here is the full set, so a link that
            // is no longer declared gets removed rather than lingering.
            $attribute->categories()->sync(
                $ids->mapWithKeys(fn ($id) => [$id => ['is_required' => false]])->all()
            );
        }
    }

    private function definitions(): array
    {
        return [
            // ---------- Global: every category ----------
            [
                'name' => 'Materials',
                'input_type' => 'select',
                'can_be_variation' => false,
                'is_global' => true,
                'max_selections' => 5,
                'values' => [['Gold', 'Silver', 'Platinum', 'Rose gold', 'White gold', 'Brass', 'Stainless steel']],
            ],
            [
                'name' => 'Gold solidity',
                'input_type' => 'select',
                'can_be_variation' => false,
                'is_global' => true,
                'max_selections' => 4,
                'values' => [['Solid gold', 'Gold plated', 'Gold filled', 'Gold vermeil']],
            ],
            [
                'name' => 'Gold purity',
                'input_type' => 'select',
                'can_be_variation' => false,
                'is_global' => true,
                'max_selections' => 5,
                'values' => [['9k', '10k', '14k', '18k', '22k', '24k']],
            ],
            [
                'name' => 'Recycled',
                'input_type' => 'boolean',
                'can_be_variation' => false,
                'is_global' => true,
            ],

            // ---------- Global variation axes ----------
            [
                'name' => 'Gemstone',
                'input_type' => 'select',
                'can_be_variation' => true,
                'is_global' => true,
                'values' => [[
                    'Ametrine', 'Aquamarine', 'Bloodstone', 'Citrine', 'Coral', 'Diamond',
                    'Emerald', 'Garnet', 'Jade', 'Lapis lazuli', 'Moonstone', 'Onyx',
                    'Opal', 'Pearl', 'Peridot', 'Quartz', 'Ruby', 'Sapphire',
                    'Tanzanite', 'Topaz', 'Turquoise', 'Zircon',
                ]],
            ],
            [
                'name' => 'Primary colour',
                'input_type' => 'select',
                'can_be_variation' => true,
                'is_global' => true,
                'values' => [['Gold', 'Silver', 'Rose', 'White', 'Black', 'Blue', 'Green', 'Red', 'Pink', 'Purple']],
            ],
            [
                'name' => 'Secondary colour',
                'input_type' => 'select',
                'can_be_variation' => true,
                'is_global' => true,
                'values' => [['Gold', 'Silver', 'Rose', 'White', 'Black', 'Blue', 'Green', 'Red', 'Pink', 'Purple']],
            ],
            [
                'name' => 'Gem colour',
                'input_type' => 'select',
                'can_be_variation' => true,
                'is_global' => true,
                'values' => [['Clear', 'White', 'Blue', 'Green', 'Red', 'Pink', 'Purple', 'Yellow', 'Black', 'Multi']],
            ],
            [
                'name' => 'Stone source',
                'input_type' => 'select',
                'can_be_variation' => true,
                'is_global' => true,
                'values' => [['Lab grown', 'Natural']],
            ],
            [
                'name' => 'Cut type',
                'input_type' => 'select',
                'can_be_variation' => true,
                'is_global' => true,
                'values' => [['Brilliant', 'Emerald', 'Princess', 'Oval', 'Pear', 'Marquise', 'Cushion', 'Asscher', 'Radiant', 'Cabochon']],
            ],
            [
                'name' => 'Shape',
                'input_type' => 'select',
                'can_be_variation' => true,
                'is_global' => true,
                'values' => [['Round', 'Square', 'Oval', 'Heart', 'Star', 'Teardrop', 'Rectangle', 'Triangle']],
            ],
            [
                'name' => 'Spinner',
                'input_type' => 'boolean',
                'can_be_variation' => true,
                'is_global' => true,
            ],
            [
                'name' => 'Adjustable',
                'input_type' => 'boolean',
                'can_be_variation' => true,
                'is_global' => true,
            ],

            // ---------- Rings ----------
            [
                'name' => 'Ring size',
                'input_type' => 'select',
                'can_be_variation' => true,
                'allowed_units' => ['US', 'UK/AU', 'FR', 'DE'],
                'categories' => ['Rings'],
                'values' => [
                    'US' => ['3', '3 1/2', '4', '4 1/2', '5', '5 1/2', '6', '6 1/2', '7', '7 1/2', '8', '8 1/2', '9', '9 1/2', '10', '11', '12'],
                    'UK/AU' => ['F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V'],
                    'FR' => ['44', '45', '46', '47', '48', '49', '50', '52', '54', '56', '58', '60', '62', '64'],
                    'DE' => ['14', '14.5', '15', '15.5', '16', '16.5', '17', '17.5', '18', '19', '20', '21'],
                ],
            ],

            // ---------- Pendant necklaces ----------
            [
                'name' => 'Pendant width',
                'input_type' => 'number',
                'can_be_variation' => true,
                'allowed_units' => ['Centimetres', 'Feet', 'Inches', 'Metres', 'Millimetres', 'Yards'],
                'categories' => ['Pendant Necklaces', 'Necklaces'],
            ],
            [
                'name' => 'Pendant height',
                'input_type' => 'number',
                'can_be_variation' => true,
                'allowed_units' => ['Centimetres', 'Feet', 'Inches', 'Metres', 'Millimetres', 'Yards'],
                'categories' => ['Pendant Necklaces', 'Necklaces'],
            ],
            [
                'name' => 'Necklace length',
                'input_type' => 'number',
                'can_be_variation' => true,
                'allowed_units' => ['Centimetres', 'Inches', 'Millimetres'],
                'categories' => ['Pendant Necklaces', 'Necklaces', 'Charm Necklaces'],
            ],

            // ---------- Bracelets ----------
            [
                'name' => 'Bracelet width',
                'input_type' => 'number',
                'can_be_variation' => true,
                'allowed_units' => ['Centimetres', 'Inches', 'Millimetres'],
                'categories' => ['Bracelets'],
            ],
            [
                'name' => 'Bracelet length',
                'input_type' => 'number',
                'can_be_variation' => true,
                'allowed_units' => ['Centimetres', 'Inches', 'Millimetres'],
                'categories' => ['Bracelets'],
            ],

            // ---------- Earrings ----------
            [
                'name' => 'Earring drop length',
                'input_type' => 'number',
                'can_be_variation' => true,
                'allowed_units' => ['Centimetres', 'Inches', 'Millimetres'],
                'categories' => ['Earrings', 'Stud Earrings'],
            ],
        ];
    }
}
