<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateShippingZoneRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'region_id' => 'nullable|exists:regions,id',
            'name' => 'sometimes|required|string|max:255',
            'country_codes' => 'sometimes|required|array',
            'country_codes.*' => 'required|string|size:2',
            'is_active' => 'nullable|boolean',
        ];
    }
}
