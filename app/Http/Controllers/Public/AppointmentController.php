<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Mail\AppointmentBooked;
use App\Models\Appointment;
use App\Models\AppointmentServiceType;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;

class AppointmentController extends Controller
{
    /** How far ahead an appointment can be booked, in days. */
    private const MAX_ADVANCE_DAYS = 180;

    /**
     * Active services for step 1 of the booking form, in display order.
     */
    public function serviceTypes()
    {
        return response()->json([
            'success' => true,
            'data' => AppointmentServiceType::active()
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'name', 'slug', 'description', 'duration_minutes', 'icon'])
        ]);
    }

    /**
     * Book an appointment. Works for guests; when a customer token is sent
     * the booking is linked to that account.
     *
     * date_time should be ISO 8601 with an offset (e.g. 2026-11-02T14:30:00+05:30);
     * it is stored in UTC.
     */
    public function store(Request $request)
    {
        $request->validate([
            'service_type_id' => ['required', 'integer', Rule::exists('appointment_service_types', 'id')->where('is_active', true)],
            'date_time' => 'required|date|after:now|before:' . now()->addDays(self::MAX_ADVANCE_DAYS)->toDateTimeString(),
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'email' => 'required|email|max:255',
            'phone' => 'required|string|max:30',
            'note' => 'nullable|string|max:2000',
        ], [
            'service_type_id.exists' => 'The selected service is not available.',
            'date_time.after' => 'Please choose a date and time in the future.',
            'date_time.before' => 'Appointments can be booked up to ' . self::MAX_ADVANCE_DAYS . ' days in advance.',
        ]);

        $appointmentAt = Carbon::parse($request->date_time)->utc();

        $alreadyBooked = Appointment::where('email', $request->email)
            ->where('appointment_at', $appointmentAt)
            ->whereIn('status', Appointment::ACTIVE_STATUSES)
            ->exists();

        if ($alreadyBooked) {
            return response()->json([
                'success' => false,
                'message' => 'You already have an appointment booked for this date and time.',
                'errors' => ['date_time' => ['You already have an appointment booked for this date and time.']]
            ], 422);
        }

        $serviceType = AppointmentServiceType::findOrFail($request->service_type_id);
        $user = auth('sanctum')->user();

        $appointment = Appointment::create([
            'user_id' => $user instanceof User ? $user->id : null,
            'service_type_id' => $serviceType->id,
            'appointment_at' => $appointmentAt,
            'duration_minutes' => $serviceType->duration_minutes,
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'email' => $request->email,
            'phone' => $request->phone,
            'note' => $request->note,
            'status' => 'pending',
        ]);

        $appointment->load('serviceType:id,name,slug,duration_minutes');

        // A mail failure must not fail the booking
        try {
            Mail::to($appointment->email)->send(new AppointmentBooked($appointment));
        } catch (\Exception $e) {
            Log::error('Failed to send appointment confirmation email', [
                'appointment_id' => $appointment->id,
                'email' => $appointment->email,
                'error' => $e->getMessage()
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Your appointment request has been received. Our team will confirm it shortly.',
            'data' => $appointment->makeHidden(['admin_notes', 'user_id'])
        ], 201);
    }
}
