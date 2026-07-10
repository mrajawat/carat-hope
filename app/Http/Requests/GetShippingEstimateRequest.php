<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GetShippingEstimateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'country_code' => 'required|string|size:2',
            'pincode' => 'nullable|string',
            'order_value' => 'required|numeric|min:0',
            'currency' => 'required|string|size:3',
        ];
    }
}
