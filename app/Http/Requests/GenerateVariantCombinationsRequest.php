<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GenerateVariantCombinationsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'attributes' => 'required|array|min:1|max:2',
            'attributes.*' => 'required|array|min:1',
            'attributes.*.*' => 'required|integer|exists:attribute_values,id',
        ];
    }

    /**
     * Get custom error messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'attributes.max' => 'Maximum of 2 variation axes (attributes) can be selected at once.',
            'attributes.min' => 'At least one variation axis (attribute) must be selected.',
            'attributes.*.min' => 'Each selected attribute must have at least one value.',
            'attributes.*.*.exists' => 'One or more of the selected attribute values is invalid.',
        ];
    }
}
