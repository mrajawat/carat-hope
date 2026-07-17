<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BulkUpdateVariantsRequest extends FormRequest
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
            'variants' => 'required|array|min:1',
            'variants.*.id' => 'required|exists:product_variants,id',
            'variants.*.price' => 'nullable|numeric|min:0',
            'variants.*.quantity' => 'nullable|integer|min:0',
            'variants.*.sku' => 'nullable|string',
            'variants.*.processing_days' => 'nullable|integer|min:0',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $variants = $this->input('variants', []);
            if (!is_array($variants)) {
                return;
            }

            $skus = [];
            foreach ($variants as $index => $v) {
                if (isset($v['sku']) && !is_null($v['sku']) && $v['sku'] !== '') {
                    $sku = $v['sku'];
                    $id = $v['id'] ?? null;

                    // 1. Check for duplicates in payload
                    if (in_array($sku, $skus)) {
                        $validator->errors()->add(
                            "variants.{$index}.sku",
                            "The SKU '{$sku}' is duplicated in this request."
                        );
                    }
                    $skus[] = $sku;

                    // 2. Check for uniqueness in DB
                    $exists = \App\Models\ProductVariant::where('sku', $sku)
                        ->where('id', '!=', $id)
                        ->exists();

                    if ($exists) {
                        $validator->errors()->add(
                            "variants.{$index}.sku",
                            "The SKU '{$sku}' has already been taken."
                        );
                    }
                }
            }
        });
    }
}
