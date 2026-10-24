<?php

namespace App\Mail;

use App\Models\Appointment;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent to the customer right after they book an appointment.
 */
class AppointmentBooked extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Appointment $appointment)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Appointment Request Received - ' . $this->appointment->reference_no,
        );
    }

    public function content(): Content
    {
        // Stored in UTC; shown to the customer in the store's local time
        $at = $this->appointment->appointment_at->copy()->setTimezone(config('app.store_timezone'));

        return new Content(
            markdown: 'emails.appointments.booked',
            with: [
                'appointment' => $this->appointment,
                'serviceName' => $this->appointment->serviceType->name,
                'date' => $at->format('l, j F Y'),
                'time' => $at->format('g:i A') . ' (' . $at->format('T') . ')',
            ],
        );
    }
}
