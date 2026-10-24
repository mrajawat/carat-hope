<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Appointment extends Model
{
    use HasFactory;

    public const STATUSES = ['pending', 'confirmed', 'completed', 'cancelled', 'no_show'];

    /** Statuses that still hold the slot. */
    public const ACTIVE_STATUSES = ['pending', 'confirmed'];

    protected $fillable = [
        'reference_no',
        'user_id',
        'service_type_id',
        'appointment_at',
        'duration_minutes',
        'first_name',
        'last_name',
        'email',
        'phone',
        'note',
        'status',
        'admin_notes',
    ];

    protected $casts = [
        'appointment_at' => 'datetime',
        'duration_minutes' => 'integer',
    ];

    protected static function booted()
    {
        static::creating(function (Appointment $appointment) {
            if (empty($appointment->reference_no)) {
                do {
                    $reference = 'AP-' . now()->format('ymd') . '-' . strtoupper(Str::random(5));
                } while (static::where('reference_no', $reference)->exists());

                $appointment->reference_no = $reference;
            }
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function serviceType()
    {
        return $this->belongsTo(AppointmentServiceType::class, 'service_type_id');
    }
}
