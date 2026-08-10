<?php

return [
    'brand_prefix' => env('JEWELRY_BRAND_PREFIX', 'CH'),
    'max_options_per_attribute' => env('JEWELRY_MAX_OPTIONS_PER_ATTRIBUTE', 50),

    /*
    | Hard ceiling on how many attributes a single product may vary by. A product
    | can set its own lower limit via products.max_variation_axes, but never above
    | this. Variant count grows multiplicatively with each axis.
    */
    'max_variation_axes' => env('JEWELRY_MAX_VARIATION_AXES', 2),

    /*
    |--------------------------------------------------------------------------
    | Static Product Option Lists
    |--------------------------------------------------------------------------
    |
    | Fixed dropdown lists on the product form. These are neither categories,
    | variation axes, nor descriptive attributes - they are product-level fields
    | with a standard set of choices, served by ProductOptionController.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Unit Presets
    |--------------------------------------------------------------------------
    |
    | Reusable unit lists offered when creating a numeric attribute. The chosen
    | list is persisted on the attribute itself (attributes.allowed_units), so
    | these are only defaults for the admin UI to seed from.
    |
    */

    'unit_presets' => [
        'dimension' => ['Centimetres', 'Feet', 'Inches', 'Metres', 'Millimetres', 'Yards'],
        'weight' => ['Grams', 'Kilograms', 'Ounces', 'Pounds'],
    ],

    'product_options' => [

        'when_was_it_made' => [
            [
                'group' => 'Made by me',
                'options' => [
                    ['value' => 'made_to_order', 'label' => 'Made To Order'],
                    ['value' => '2020_2029', 'label' => '2020 - 2029'],
                    ['value' => '2010_2019', 'label' => '2010 - 2019'],
                    ['value' => 'before_2010', 'label' => 'Before 2010'],
                ],
            ],
            [
                'group' => 'Vintage',
                'options' => [
                    ['value' => '2000_2009', 'label' => '2000 - 2009'],
                    ['value' => '1990s', 'label' => '1990s'],
                    ['value' => '1980s', 'label' => '1980s'],
                    ['value' => '1970s', 'label' => '1970s'],
                    ['value' => '1960s', 'label' => '1960s'],
                    ['value' => '1950s', 'label' => '1950s'],
                    ['value' => '1940s', 'label' => '1940s'],
                    ['value' => '1930s', 'label' => '1930s'],
                    ['value' => '1920s', 'label' => '1920s'],
                    ['value' => '1910s', 'label' => '1910s'],
                    ['value' => '1900_1909', 'label' => '1900 - 1909'],
                    ['value' => '1800s', 'label' => '1800s'],
                    ['value' => '1700s', 'label' => '1700s'],
                    ['value' => 'before_1700', 'label' => 'Before 1700'],
                ],
            ],
        ],

    ],
];
