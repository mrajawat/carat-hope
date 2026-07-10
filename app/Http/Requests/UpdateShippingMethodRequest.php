<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Enums\CarrierType;

class UpdateShippingMethodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'shipping_zone_id' => 'sometimes|required|exists:shipping_zones,id',
            'name' => 'sometimes|required|string|max:255',
            'carrier_type' => ['sometimes', 'required', Rule::enum(CarrierType::class)],
            'carrier_name' => 'nullable|string|max:255',
            'base_rate' => 'sometimes|required|numeric|min:0',
            'insurance_percentage' => 'nullable|numeric|min:0|max:100',
            'requires_signature' => 'nullable|boolean',
            'min_transit_days' => 'sometimes|required|integer|min:0',
            'max_transit_days' => 'sometimes|required|integer|gte:min_transit_days',
            'processing_days' => 'sometimes|required|integer|min:0',
            'is_active' => 'nullable|boolean',
            'sort_order' => 'nullable|integer',
        ];
    }
}
