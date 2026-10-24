<x-mail::message>
# Thank you, {{ $appointment->first_name }}!

We have received your appointment request. Our team will confirm it with you shortly.

<x-mail::table>
| | |
|:--|:--|
| **Reference** | {{ $appointment->reference_no }} |
| **Service** | {{ $serviceName }} |
| **Date** | {{ $date }} |
| **Time** | {{ $time }} |
| **Duration** | {{ $appointment->duration_minutes }} minutes |
</x-mail::table>

@if ($appointment->note)
**Your note:**
{{ $appointment->note }}
@endif

If you need to reschedule or cancel, simply reply to this email and quote your reference number.

Warm regards,<br>
{{ config('mail.from.name') }}
</x-mail::message>
