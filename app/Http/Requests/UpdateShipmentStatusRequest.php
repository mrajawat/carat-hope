<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Enums\ShipmentStatus;

class UpdateShipmentStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(ShipmentStatus::class)],
            'location' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'event_time' => 'nullable|date',
            'signature_image_url' => 'nullable|url|max:2048',
            'customs_hs_code' => 'nullable|string|max:50',
            'customs_declaration_note' => 'nullable|string',
            'notes' => 'nullable|string',
        ];
    }
}
