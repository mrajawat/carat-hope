<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreDeliveryEstimateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'shipping_zone_id' => 'required|exists:shipping_zones,id',
            'pincode_prefix' => 'nullable|string|max:20',
            'min_days' => 'required|integer|min:0',
            'max_days' => 'required|integer|gte:min_days',
            'cutoff_time' => 'required|date_format:H:i:s',
        ];
    }
}
