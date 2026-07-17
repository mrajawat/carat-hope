<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'has_variants' => $this->has('has_variants')
                ? (filter_var($this->input('has_variants'), FILTER_VALIDATE_BOOLEAN) ? 1 : 0)
                : 0,
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'category_id' => 'required|exists:categories,id',
            'description' => 'nullable|string',
            'images' => 'required|array|min:1',
            'images.*' => 'required',
            'has_variants' => 'boolean',
            'prices_vary' => 'nullable|boolean',
            'quantities_vary' => 'nullable|boolean',
            'skus_vary' => 'nullable|boolean',
            'processing_time_varies' => 'nullable|boolean',
            'max_variation_axes' => 'nullable|integer|min:1|max:2',
            'total_stock' => 'nullable|integer|min:0',
            'sku' => 'nullable|string|unique:products,sku',
            'price' => 'required_if:has_variants,false,0|nullable|numeric|min:0',
            'discount_price' => 'nullable|numeric|min:0',
            'stock_qty' => 'required_if:has_variants,false,0|nullable|integer|min:0',
            'video' => 'nullable',
            'local_prices' => 'nullable|array',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'price.required_if' => 'Price is required for simple products.',
            'stock_qty.required_if' => 'Stock quantity is required for simple products.',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $hasVariants = filter_var($this->input('has_variants'), FILTER_VALIDATE_BOOLEAN);

            if (!$hasVariants) {
                $price = $this->input('price');
                $discountPrice = $this->input('discount_price');

                if (!is_null($price) && !is_null($discountPrice) && floatval($discountPrice) > floatval($price)) {
                    $validator->errors()->add(
                        'discount_price',
                        'The discount price must be less than or equal to the price.'
                    );
                }
            }
        });
    }
}
