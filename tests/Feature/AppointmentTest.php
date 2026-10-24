<?php

namespace Tests\Feature;

use App\Mail\AppointmentBooked;
use App\Models\Admin;
use App\Models\Appointment;
use App\Models\AppointmentServiceType;
use App\Models\User;
use Database\Seeders\AppointmentServiceTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AppointmentTest extends TestCase
{
    use RefreshDatabase;

    protected AppointmentServiceType $engagement;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();

        $this->seed(AppointmentServiceTypeSeeder::class);
        $this->engagement = AppointmentServiceType::where('slug', 'engagement-ring')->firstOrFail();
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'service_type_id' => $this->engagement->id,
            'date_time' => now()->addDays(3)->setTime(14, 30)->toIso8601String(),
            'first_name' => 'Asha',
            'last_name' => 'Verma',
            'email' => 'asha@example.com',
            'phone' => '9876543210',
            'note' => 'Looking for an oval solitaire.',
        ], $overrides);
    }

    public function test_seeder_is_idempotent(): void
    {
        $this->seed(AppointmentServiceTypeSeeder::class);

        $this->assertSame(4, AppointmentServiceType::count());
    }

    public function test_service_types_lists_active_services_in_order(): void
    {
        AppointmentServiceType::where('slug', 'wedding-band')->update(['is_active' => false]);

        $response = $this->getJson('/api/public/appointments/service-types')->assertOk();

        $this->assertSame(
            ['Engagement Ring', 'Fine Jewelry & Gifts', 'Bespoke Creation'],
            collect($response->json('data'))->pluck('name')->all()
        );
        $response->assertJsonStructure(['data' => [['id', 'name', 'slug', 'description', 'duration_minutes', 'icon']]]);
        $this->assertSame(60, $response->json('data.0.duration_minutes'));
    }

    public function test_guest_can_book_an_appointment(): void
    {
        $response = $this->postJson('/api/public/appointments', $this->payload())->assertCreated();

        $response->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.duration_minutes', 60)
            ->assertJsonPath('data.service_type.name', 'Engagement Ring')
            ->assertJsonMissingPath('data.admin_notes');
        $this->assertStringStartsWith('AP-', $response->json('data.reference_no'));
        $this->assertDatabaseHas('appointments', ['email' => 'asha@example.com', 'user_id' => null]);
    }

    public function test_confirmation_email_is_sent_to_the_customer(): void
    {
        $this->postJson('/api/public/appointments', $this->payload())->assertCreated();

        $appointment = Appointment::first();
        Mail::assertSent(AppointmentBooked::class, function (AppointmentBooked $mail) use ($appointment) {
            return $mail->hasTo('asha@example.com') && $mail->appointment->is($appointment);
        });
        Mail::assertSentCount(1);
    }

    public function test_confirmation_email_shows_booking_details_in_store_time(): void
    {
        $date = now()->addDays(3);
        $this->postJson('/api/public/appointments', $this->payload([
            'date_time' => $date->format('Y-m-d') . 'T09:00:00Z',
        ]))->assertCreated();

        $mail = new AppointmentBooked(Appointment::with('serviceType')->first());

        $mail->assertSeeInHtml(Appointment::first()->reference_no)
            ->assertSeeInHtml('Engagement Ring')
            ->assertSeeInHtml('2:30 PM')
            ->assertSeeInHtml($date->format('j F Y'))
            ->assertSeeInHtml('Looking for an oval solitaire.');
    }

    public function test_booking_succeeds_even_if_email_fails(): void
    {
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('SMTP down'));

        $this->postJson('/api/public/appointments', $this->payload())->assertCreated();

        $this->assertSame(1, Appointment::count());
    }

    public function test_date_time_is_stored_in_utc(): void
    {
        $this->postJson('/api/public/appointments', $this->payload([
            'date_time' => now()->addDays(3)->format('Y-m-d') . 'T14:30:00+05:30',
        ]))->assertCreated();

        $this->assertSame('09:00:00', Appointment::first()->appointment_at->format('H:i:s'));
    }

    public function test_logged_in_customer_booking_is_linked_to_account(): void
    {
        $user = User::create(['name' => 'Asha', 'email' => 'asha@example.com', 'phone' => '9876543210', 'password' => 'secret123', 'status' => 'active']);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/public/appointments', $this->payload())
            ->assertCreated()
            ->assertJsonMissingPath('data.user_id');

        $this->assertSame($user->id, Appointment::first()->user_id);
    }

    public function test_validation_rejects_bad_input(): void
    {
        $this->postJson('/api/public/appointments', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['service_type_id', 'date_time', 'first_name', 'last_name', 'email', 'phone']);

        $this->postJson('/api/public/appointments', $this->payload(['date_time' => now()->subHour()->toIso8601String()]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['date_time']);

        $this->postJson('/api/public/appointments', $this->payload(['date_time' => now()->addYear()->toIso8601String()]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['date_time']);

        $this->engagement->update(['is_active' => false]);
        $this->postJson('/api/public/appointments', $this->payload())
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['service_type_id']);
    }

    public function test_same_email_cannot_book_the_same_slot_twice(): void
    {
        $payload = $this->payload();
        $this->postJson('/api/public/appointments', $payload)->assertCreated();

        $this->postJson('/api/public/appointments', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['date_time']);

        // Once cancelled, the slot can be booked again
        Appointment::query()->update(['status' => 'cancelled']);
        $this->postJson('/api/public/appointments', $payload)->assertCreated();
    }

    public function test_admin_can_list_and_update_appointments(): void
    {
        $this->postJson('/api/public/appointments', $this->payload())->assertCreated();
        $appointment = Appointment::first();

        $admin = Admin::create(['name' => 'Admin', 'email' => 'admin@example.com', 'password' => 'secret123', 'role' => 'super_admin']);
        $this->actingAs($admin, 'sanctum');

        $this->getJson('/api/admin/appointments?status=pending')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->getJson("/api/admin/appointments/{$appointment->id}")
            ->assertOk()
            ->assertJsonPath('data.reference_no', $appointment->reference_no);

        $this->patchJson("/api/admin/appointments/{$appointment->id}/status", [
            'status' => 'confirmed',
            'admin_notes' => 'Assigned to Riya.',
        ])->assertOk()->assertJsonPath('data.status', 'confirmed');

        $this->assertDatabaseHas('appointments', ['id' => $appointment->id, 'status' => 'confirmed', 'admin_notes' => 'Assigned to Riya.']);
    }
}
