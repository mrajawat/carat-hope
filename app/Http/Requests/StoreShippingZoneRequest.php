<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreShippingZoneRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'region_id' => 'nullable|exists:regions,id',
            'name' => 'required|string|max:255',
            'country_codes' => 'required|array',
            'country_codes.*' => 'required|string|size:2',
            'is_active' => 'nullable|boolean',
        ];
    }
}
