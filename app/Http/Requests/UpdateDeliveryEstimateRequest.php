<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDeliveryEstimateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'shipping_zone_id' => 'sometimes|required|exists:shipping_zones,id',
            'pincode_prefix' => 'nullable|string|max:20',
            'min_days' => 'sometimes|required|integer|min:0',
            'max_days' => 'sometimes|required|integer|gte:min_days',
            'cutoff_time' => 'sometimes|required|date_format:H:i:s',
        ];
    }
}
