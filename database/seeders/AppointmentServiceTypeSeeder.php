<?php

namespace Database\Seeders;

use App\Models\AppointmentServiceType;
use Illuminate\Database\Seeder;

/**
 * Seeds the services offered on the "Book an Appointment" page.
 *
 * Idempotent - re-running updates rather than duplicating.
 */
class AppointmentServiceTypeSeeder extends Seeder
{
    public function run(): void
    {
        $services = [
            [
                'name' => 'Engagement Ring',
                'slug' => 'engagement-ring',
                'description' => 'Find your perfect center diamond and ring setting with our experts.',
                'duration_minutes' => 60,
                'icon' => 'diamond',
            ],
            [
                'name' => 'Wedding Band',
                'slug' => 'wedding-band',
                'description' => "Discover bands that complement your style and your partner's.",
                'duration_minutes' => 45,
                'icon' => 'ring',
            ],
            [
                'name' => 'Fine Jewelry & Gifts',
                'slug' => 'fine-jewelry-gifts',
                'description' => 'Explore necklaces, bracelets, earrings and more from our collections.',
                'duration_minutes' => 45,
                'icon' => 'gem',
            ],
            [
                'name' => 'Bespoke Creation',
                'slug' => 'bespoke-creation',
                'description' => 'Begin the journey of designing a completely one-of-a-kind piece.',
                'duration_minutes' => 90,
                'icon' => 'sparkles',
            ],
        ];

        foreach ($services as $index => $service) {
            AppointmentServiceType::updateOrCreate(
                ['slug' => $service['slug']],
                $service + ['sort_order' => $index + 1, 'is_active' => true]
            );
        }
    }
}
