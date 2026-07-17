<?php

namespace App\Http\Requests;

use App\Models\Product;
use App\Models\AttributeValue;
use App\Services\AttributeService;
use Illuminate\Foundation\Http\FormRequest;

class StoreProductVariantRequest extends FormRequest
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
            'product_id' => 'required|exists:products,id',
            'sku' => 'nullable|string|unique:product_variants,sku',
            'weight_grams' => 'required|numeric|min:0',
            'making_charges' => 'nullable|numeric|min:0',
            'base_price' => 'nullable|numeric|min:0',
            'stock_quantity' => 'required|integer|min:0',
            'processing_days' => 'nullable|integer|min:0',
            'variant_images' => 'nullable|array',
            'attributes' => 'required|array',
            'prices' => 'nullable|array',
            'prices.*.region_id' => 'required|exists:regions,id',
            'prices.*.price' => 'required|numeric|min:0',
            'prices.*.compare_at_price' => 'nullable|numeric|min:0',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $productId = $this->input('product_id');
            $product = Product::find($productId);

            if (!$product) {
                return;
            }

            $attributeService = app(AttributeService::class);
            $requiredAttributes = $attributeService->getAttributesForCategory($product->category_id)
                ->where('pivot.is_required', true);

            $attributesInput = $this->input('attributes', []);

            foreach ($requiredAttributes as $attribute) {
                // Check if either the attribute ID or its slug is present in the input array
                $hasId = isset($attributesInput[$attribute->id]);
                $hasSlug = isset($attributesInput[$attribute->slug]);

                if (!$hasId && !$hasSlug) {
                    $validator->errors()->add(
                        "attributes.{$attribute->slug}",
                        "The attribute '{$attribute->name}' is required for this product's category."
                    );
                } else {
                    $valueId = $hasId ? $attributesInput[$attribute->id] : $attributesInput[$attribute->slug];
                    $valueExists = AttributeValue::where('id', $valueId)
                        ->where('attribute_id', $attribute->id)
                        ->exists();

                    if (!$valueExists) {
                        $validator->errors()->add(
                            "attributes.{$attribute->slug}",
                            "The selected value for '{$attribute->name}' is invalid."
                        );
                    }
                }
            }
        });
    }
}
