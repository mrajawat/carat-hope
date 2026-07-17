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
            'attributes.*.attribute_id' => 'required|integer|exists:attributes,id',
            'attributes.*.attribute_value_ids' => 'required|array|min:1',
            'attributes.*.attribute_value_ids.*' => 'required|integer|exists:attribute_values,id',
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
            'attributes.*.attribute_id.exists' => 'The selected attribute is invalid.',
            'attributes.*.attribute_value_ids.min' => 'Each selected attribute must have at least one value.',
            'attributes.*.attribute_value_ids.*.exists' => 'One or more of the selected attribute values is invalid.',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $maxOptions = config('jewelry.max_options_per_attribute', 50);
            $attributes = $this->input('attributes', []);

            if (is_array($attributes)) {
                foreach ($attributes as $index => $attr) {
                    if (is_array($attr) && isset($attr['attribute_value_ids'])) {
                        $valueIds = $attr['attribute_value_ids'];
                        if (is_array($valueIds) && count($valueIds) > $maxOptions) {
                            $validator->errors()->add(
                                "attributes.{$index}.attribute_value_ids",
                                "Maximum of {$maxOptions} option values can be selected per attribute type."
                            );
                        }
                    }
                }
            }
        });
    }
}
