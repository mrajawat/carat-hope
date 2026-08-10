<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ProductOptionController extends Controller
{
    /**
     * List the static product option dropdowns (e.g. "When was it made?").
     *
     * These are product-level fields with a fixed set of choices - distinct from
     * categories, variation axes, and descriptive attributes, which each have
     * their own master data endpoints.
     *
     * Pass ?key=when_was_it_made to fetch a single list.
     */
    public function index(Request $request)
    {
        $options = config('jewelry.product_options', []);
        $options['unit_presets'] = config('jewelry.unit_presets', []);

        if ($request->filled('key')) {
            $key = $request->key;

            if (!array_key_exists($key, $options)) {
                return response()->json([
                    'status' => false,
                    'message' => "Unknown product option list '{$key}'.",
                    'data' => null
                ], 404);
            }

            return response()->json([
                'status' => true,
                'message' => 'Product options retrieved successfully',
                'data' => $options[$key]
            ]);
        }

        return response()->json([
            'status' => true,
            'message' => 'Product options retrieved successfully',
            'data' => $options
        ]);
    }
}
