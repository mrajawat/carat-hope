<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AppointmentController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'search' => 'nullable|string|max:100',
            'status' => ['nullable', Rule::in(Appointment::STATUSES)],
            'service_type_id' => 'nullable|integer',
            'from' => 'nullable|date',
            'to' => 'nullable|date|after_or_equal:from',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $query = Appointment::with('serviceType:id,name,slug')
            ->orderBy('appointment_at');

        if ($search = trim((string) $request->input('search'))) {
            $query->where(function ($q) use ($search) {
                $q->where('reference_no', 'like', "%{$search}%")
                    ->orWhere('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('service_type_id')) {
            $query->where('service_type_id', $request->integer('service_type_id'));
        }

        if ($request->filled('from')) {
            $query->where('appointment_at', '>=', $request->date('from')->startOfDay());
        }

        if ($request->filled('to')) {
            $query->where('appointment_at', '<=', $request->date('to')->endOfDay());
        }

        // Paginate only when per_page is requested; otherwise return the full list
        if ($request->filled('per_page')) {
            $appointments = $query->paginate($request->integer('per_page'));

            return response()->json([
                'success' => true,
                'data' => $appointments->items(),
                'meta' => [
                    'current_page' => $appointments->currentPage(),
                    'last_page' => $appointments->lastPage(),
                    'per_page' => $appointments->perPage(),
                    'total' => $appointments->total(),
                ],
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => $query->get()
        ]);
    }

    public function show($id)
    {
        $appointment = Appointment::with(['serviceType:id,name,slug,duration_minutes', 'user:id,name,email,phone'])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $appointment
        ]);
    }

    public function updateStatus(Request $request, $id)
    {
        $request->validate([
            'status' => ['required', Rule::in(Appointment::STATUSES)],
            'admin_notes' => 'nullable|string|max:5000',
        ]);

        $appointment = Appointment::findOrFail($id);
        $appointment->status = $request->status;
        if ($request->has('admin_notes')) {
            $appointment->admin_notes = $request->admin_notes;
        }
        $appointment->save();

        return response()->json([
            'success' => true,
            'message' => 'Appointment status updated successfully',
            'data' => $appointment->load('serviceType:id,name,slug')
        ]);
    }
}
