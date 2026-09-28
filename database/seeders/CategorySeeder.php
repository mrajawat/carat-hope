<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

/**
 * Top-level (parent) categories. These are the only source of parent categories —
 * the admin API can only create subcategories under them.
 *
 * Idempotent: existing rows (matched by slug) are left untouched so admin edits
 * to image/description/status survive a re-seed. Safe to run in production:
 *   php artisan db:seed --class=CategorySeeder --force
 */
class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $parents = [
            [
                'name' => 'Rings',
                'slug' => 'rings',
                'image' => 'https://images.unsplash.com/photo-1605100804763-247f67b3557e?auto=format&fit=crop&q=80&w=500',
                'description' => 'Exquisite diamond, gold, and platinum rings for engagement and everyday elegance.',
            ],
            [
                'name' => 'Necklaces',
                'slug' => 'necklaces',
                'image' => 'https://images.unsplash.com/photo-1599643478518-a784e5dc4c8f?auto=format&fit=crop&q=80&w=500',
                'description' => 'Stunning necklaces, chokers, and chains featuring fine gemstones and pearls.',
            ],
            [
                'name' => 'Earrings',
                'slug' => 'earrings',
                'image' => 'https://images.unsplash.com/photo-1535632066927-ab7c9ab60908?auto=format&fit=crop&q=80&w=500',
                'description' => 'Studs, hoops, and drop earrings crafted to make you shine.',
            ],
            [
                'name' => 'Bracelets',
                'slug' => 'bracelets',
                'image' => 'https://images.unsplash.com/photo-1611591437281-460bfbe1220a?auto=format&fit=crop&q=80&w=500',
                'description' => 'Elegant bangles, charm bracelets, and tennis cuffs.',
            ],
        ];

        // Array order = navbar order (sort_order), only applied when the row is created
        foreach ($parents as $index => $parent) {
            Category::firstOrCreate(
                ['slug' => $parent['slug']],
                $parent + ['parent_id' => null, 'status' => 'active', 'sort_order' => $index]
            );
        }
    }
}
