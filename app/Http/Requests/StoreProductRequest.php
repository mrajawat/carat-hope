<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductRequest extends FormRequest
{
    private const VARIANT_PRICE_REQUIRED_MESSAGE = 'Each variant requires at least one regional price.';

    /** Etsy-style cap shown as the "13 left" counter on the Attributes screen. */
    public const MAX_TAGS = 13;

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
        $hasVariants = $this->has('has_variants')
            ? filter_var($this->input('has_variants'), FILTER_VALIDATE_BOOLEAN)
            : ($this->has('variants') || $this->has('variations') || $this->has('attributes'));

        $merge = ['has_variants' => $hasVariants ? 1 : 0];

        // Tags may arrive as "a, b, c" or as an array; normalise so the max:13
        // rule reports a proper error instead of the value being silently trimmed
        $tags = $this->input('tags');
        if (is_string($tags)) {
            $merge['tags'] = array_values(array_filter(array_map('trim', explode(',', $tags))));
        }

        $this->merge($merge);
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
            'images' => 'required|array|min:1|max:22',
            'images.*' => 'required',
            'has_variants' => 'boolean',
            'prices_vary' => 'nullable|boolean',
            'quantities_vary' => 'nullable|boolean',
            'skus_vary' => 'nullable|boolean',
            'processing_time_varies' => 'nullable|boolean',
            'processing_profiles_vary' => 'nullable|boolean',
            'max_variation_axes' => 'nullable|integer|min:1|max:' . (int) config('jewelry.max_variation_axes', 2),
            'total_stock' => 'nullable|integer|min:1|max:999',
            'sku' => 'nullable|string|unique:products,sku',
            'stock_qty' => 'required_if:has_variants,false,0|nullable|integer|min:1|max:999',
            'video' => 'nullable',
            'attributes' => 'nullable|array',
            'variants' => 'nullable|array',
            'variants.*.stock_quantity' => 'nullable|integer|min:0|max:999',
            'variations' => 'nullable|array',
            'variations.*.stock_quantity' => 'nullable|integer|min:0|max:999',
            'prices' => 'nullable|array|min:1',
            'prices.*.region_id' => 'required_with:prices|exists:regions,id',
            'prices.*.price' => 'required_with:prices|numeric|min:0',
            'prices.*.compare_at_price' => 'nullable|numeric|min:0',
            'variants.*.prices' => 'nullable|array|min:1',
            'variants.*.prices.*.region_id' => 'required_with:variants.*.prices|exists:regions,id',
            'variants.*.prices.*.price' => 'required_with:variants.*.prices|numeric|min:0',
            'variations.*.prices' => 'nullable|array|min:1',
            'variations.*.prices.*.region_id' => 'required_with:variations.*.prices|exists:regions,id',
            'variations.*.prices.*.price' => 'required_with:variations.*.prices|numeric|min:0',
            'tags' => 'nullable|array|max:13',
            'tags.*' => 'required|string|max:255',
            'materials' => 'nullable',
            'gold_solidity' => 'nullable',
            'gold_purity' => 'nullable',
            'max_offer_discount_percent' => 'nullable|integer|min:1|max:100',
            'primary_colour' => 'nullable',
            'primary_color' => 'nullable',
            'pendant_width' => 'nullable',
            'pendant_height' => 'nullable',
            'necklace_length' => 'nullable',
            'recycled' => 'nullable',
            'is_recycled' => 'nullable',
            'listing_attributes' => 'nullable',
            'is_global_pricing_enabled' => 'nullable|boolean',
            'domestic_and_global_pricing' => 'nullable|boolean',
            'allow_offers' => 'nullable|boolean',
            'allow_buyer_offers' => 'nullable|boolean',
            'processing_profile_id' => 'nullable|exists:processing_profiles,id',
            'shipping_profile_id' => 'nullable|exists:shipping_profiles,id',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'images.required' => 'At least one product image is required.',
            'images.max' => 'You can upload up to 20 photos and 2 videos maximum.',
            'stock_qty.required_if' => 'Stock quantity is required for simple products.',
            'stock_qty.min' => 'Enter a quantity between 1 and 999.',
            'stock_qty.max' => 'Enter a quantity between 1 and 999.',
            'total_stock.min' => 'Enter a quantity between 1 and 999.',
            'total_stock.max' => 'Enter a quantity between 1 and 999.',
            'tags.max' => 'You can add up to 13 tags.',
            'prices.min' => 'At least one regional price is required.',
            'variants.*.prices.min' => self::VARIANT_PRICE_REQUIRED_MESSAGE,
            'variations.*.prices.min' => self::VARIANT_PRICE_REQUIRED_MESSAGE,
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $this->validatePricingRequirement($validator);
            $this->validateAllRegionalPrices($validator);
            $this->validateGlobalPricingScope($validator);
            $this->validateImageCounts($validator);
            $this->validateTagCount($validator);
            $this->validateAttributeSelectionLimits($validator);
        });
    }

    /**
     * Tags were previously truncated silently in ProductService, leaving the
     * client's "13 left" counter disagreeing with what actually saved.
     */
    protected function validateTagCount($validator): void
    {
        $tags = $this->input('tags');

        if (is_string($tags)) {
            $tags = array_filter(array_map('trim', explode(',', $tags)));
        }

        if (is_array($tags) && count($tags) > self::MAX_TAGS) {
            $validator->errors()->add('tags', 'You can add up to ' . self::MAX_TAGS . ' tags.');
        }
    }

    /**
     * Every selection must be a real option from the attribute master - either its
     * value id, or its exact label. Without this the API would accept any string,
     * leaving products pointing at options that do not exist.
     */
    protected function validateAgainstMaster($validator, $attribute, string $field, array $selected): void
    {
        $master = $attribute->values()->get(['id', 'value']);

        foreach ($selected as $idx => $entry) {
            if (!is_scalar($entry)) {
                continue;
            }

            $matched = is_numeric($entry)
                ? $master->contains('id', (int) $entry)
                : $master->contains(fn ($v) => strcasecmp((string) $v->value, trim((string) $entry)) === 0);

            if (!$matched) {
                $validator->errors()->add(
                    "{$field}.{$idx}",
                    "\"{$entry}\" is not a valid option for {$attribute->name}."
                );
            }
        }
    }

    /**
     * Honour each attribute's max_selections ("Select up to 5", "Select up to 4").
     * Covers both the descriptive fields stored as named lists and the
     * attribute_id-keyed map form.
     */
    protected function validateAttributeSelectionLimits($validator): void
    {
        // Named lists on the Attributes screen map to attributes by slug
        foreach (['materials', 'gold_solidity', 'gold_purity'] as $field) {
            $value = $this->input($field);

            if (is_string($value)) {
                $value = array_values(array_filter(array_map('trim', explode(',', $value))));
            }

            if (!is_array($value) || empty($value)) {
                continue;
            }

            $attribute = \App\Models\Attribute::where('slug', str_replace('_', '-', $field))->first();

            if (!$attribute) {
                continue;
            }

            if ($attribute->max_selections && count($value) > $attribute->max_selections) {
                $validator->errors()->add(
                    $field,
                    "Select up to {$attribute->max_selections} for {$attribute->name}."
                );
            }

            $this->validateAgainstMaster($validator, $attribute, $field, $value);
        }

        // Map form: {"6": [29, 30, 31]} - keys are attribute ids
        $attributes = $this->input('attributes');

        if (!is_array($attributes)) {
            return;
        }

        foreach ($attributes as $key => $values) {
            if (!is_numeric($key) || !is_array($values)) {
                continue;
            }

            $attribute = \App\Models\Attribute::find((int) $key);

            if ($attribute && $attribute->max_selections && count($values) > $attribute->max_selections) {
                $validator->errors()->add(
                    "attributes.{$key}",
                    "Select up to {$attribute->max_selections} for {$attribute->name}."
                );
            }
        }
    }

    /**
     * Regional pricing is always mandatory, but *where* it must be provided depends
     * on prices_vary: a simple product, or a variant product where prices don't vary,
     * needs exactly one shared 'prices' array; a variant product where prices do vary
     * needs a 'prices' array on every individual variant.
     */
    protected function validatePricingRequirement($validator): void
    {
        $hasVariants = filter_var($this->input('has_variants'), FILTER_VALIDATE_BOOLEAN);
        $pricesVary = $this->has('prices_vary')
            ? filter_var($this->input('prices_vary'), FILTER_VALIDATE_BOOLEAN)
            : true;

        $this->validateGlobalPricingScope($validator);

        if (!$hasVariants || !$pricesVary) {
            $prices = $this->input('prices');
            if (empty($prices) || !is_array($prices)) {
                $validator->errors()->add('prices', 'At least one regional price is required.');
            }
            return;
        }

        $variantsKey = 'variants';
        if (!$this->has('variants') && $this->has('variations')) {
            $variantsKey = 'variations';
        } elseif (!$this->has('variants') && !$this->has('variations')) {
            return;
        }

        foreach ((array) $this->input($variantsKey, []) as $idx => $variant) {
            if (empty($variant['prices']) || !is_array($variant['prices'])) {
                $validator->errors()->add(
                    "{$variantsKey}.{$idx}.prices",
                    self::VARIANT_PRICE_REQUIRED_MESSAGE
                );
            }
        }
    }

    /**
     * With "Domestic and global pricing" switched off the listing has a single
     * price for every buyer, so only the default region may be priced.
     */
    protected function validateGlobalPricingScope($validator): void
    {
        $globalPricing = $this->has('is_global_pricing_enabled') || $this->has('domestic_and_global_pricing')
            ? filter_var(
                $this->input('is_global_pricing_enabled') ?? $this->input('domestic_and_global_pricing'),
                FILTER_VALIDATE_BOOLEAN
            )
            : true;

        if ($globalPricing) {
            return;
        }

        $defaultRegionId = \App\Models\Region::where('is_default', true)->value('id');

        $groups = [['prices', $this->input('prices')]];

        foreach (['variants', 'variations'] as $variantsKey) {
            foreach ((array) $this->input($variantsKey, []) as $idx => $variant) {
                $groups[] = ["{$variantsKey}.{$idx}.prices", $variant['prices'] ?? null];
            }
        }

        foreach ($groups as [$field, $prices]) {
            if (!is_array($prices)) {
                continue;
            }

            foreach ($prices as $idx => $entry) {
                if (!is_array($entry) || !isset($entry['region_id'])) {
                    continue;
                }

                if ((int) $entry['region_id'] !== (int) $defaultRegionId) {
                    $validator->errors()->add(
                        "{$field}.{$idx}.region_id",
                        'Turn on domestic and global pricing to set prices for other locations.'
                    );
                }
            }
        }
    }

    /**
     * Ensure compare_at_price >= price for every regional price entry,
     * at the product level and within each variant.
     */
    protected function validateAllRegionalPrices($validator): void
    {
        $this->validateRegionalPriceGroup($validator, $this->input('prices'), 'prices');

        foreach ((array) $this->input('variants', []) as $vIdx => $variant) {
            $this->validateRegionalPriceGroup($validator, $variant['prices'] ?? null, "variants.{$vIdx}.prices");
        }
        foreach ((array) $this->input('variations', []) as $vIdx => $variant) {
            $this->validateRegionalPriceGroup($validator, $variant['prices'] ?? null, "variations.{$vIdx}.prices");
        }
    }

    protected function validateRegionalPriceGroup($validator, $pricesInput, string $fieldPrefix): void
    {
        if (!is_array($pricesInput)) {
            return;
        }

        foreach ($pricesInput as $idx => $priceEntry) {
            if (!is_array($priceEntry) || !isset($priceEntry['price'], $priceEntry['compare_at_price'])) {
                continue;
            }
            if ($priceEntry['compare_at_price'] === null) {
                continue;
            }
            if (floatval($priceEntry['compare_at_price']) < floatval($priceEntry['price'])) {
                $validator->errors()->add(
                    "{$fieldPrefix}.{$idx}.compare_at_price",
                    'The compare-at price must be greater than or equal to the price.'
                );
            }
        }
    }

    protected function validateImageCounts($validator): void
    {
        $images = $this->input('images');
        if (!is_array($images)) {
            return;
        }

        $photoCount = 0;
        $videoCount = 0;

        foreach ($images as $item) {
            if (\App\Helpers\VideoHelper::isVideoInput($item)) {
                $videoCount++;
            } else {
                $photoCount++;
            }
        }

        if ($this->has('video') && !empty($this->input('video'))) {
            $videoCount++;
        }

        if ($photoCount > 20) {
            $validator->errors()->add('images', 'You can upload up to 20 photos maximum.');
        }
        if ($videoCount > 2) {
            $validator->errors()->add('images', 'You can upload up to 2 videos maximum.');
        }
    }
}
