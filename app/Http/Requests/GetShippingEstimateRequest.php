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
            // Products in the cart, so their delivery profile can be resolved.
            // Omitted for a profile-agnostic estimate.
            'product_ids' => 'nullable|array',
            'product_ids.*' => 'required|integer|exists:products,id',
        ];
    }
}
