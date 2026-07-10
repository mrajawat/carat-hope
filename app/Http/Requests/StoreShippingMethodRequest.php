<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Enums\CarrierType;

class StoreShippingMethodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'shipping_zone_id' => 'required|exists:shipping_zones,id',
            'name' => 'required|string|max:255',
            'carrier_type' => ['required', Rule::enum(CarrierType::class)],
            'carrier_name' => 'nullable|string|max:255',
            'base_rate' => 'required|numeric|min:0',
            'insurance_percentage' => 'nullable|numeric|min:0|max:100',
            'requires_signature' => 'nullable|boolean',
            'min_transit_days' => 'required|integer|min:0',
            'max_transit_days' => 'required|integer|gte:min_transit_days',
            'processing_days' => 'required|integer|min:0',
            'is_active' => 'nullable|boolean',
            'sort_order' => 'nullable|integer',
        ];
    }
}
