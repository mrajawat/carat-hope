<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductCustomOption;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ProductCustomOptionController extends Controller
{
    /**
     * List a product's buyer-input fields.
     */
    public function index($productId)
    {
        $product = Product::findOrFail($productId);

        return response()->json([
            'status' => true,
            'message' => 'Custom options retrieved successfully',
            'data' => $product->customOptions()->get(),
            'meta' => [
                'max_per_product' => ProductCustomOption::MAX_PER_PRODUCT,
                'remaining' => ProductCustomOption::MAX_PER_PRODUCT - $product->customOptions()->count(),
            ]
        ]);
    }

    public function store(Request $request, $productId)
    {
        $product = Product::findOrFail($productId);
        $validated = $request->validate($this->rules());

        if ($product->customOptions()->count() >= ProductCustomOption::MAX_PER_PRODUCT) {
            throw ValidationException::withMessages([
                'label' => 'You can create up to ' . ProductCustomOption::MAX_PER_PRODUCT . ' custom option fields per listing.',
            ]);
        }

        $validated['product_id'] = $product->id;
        $option = ProductCustomOption::create($validated);

        return response()->json([
            'status' => true,
            'message' => 'Custom option created successfully',
            'data' => $option
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $option = ProductCustomOption::findOrFail($id);
        $option->update($request->validate($this->rules()));

        return response()->json([
            'status' => true,
            'message' => 'Custom option updated successfully',
            'data' => $option->fresh()
        ]);
    }

    public function destroy($id)
    {
        ProductCustomOption::findOrFail($id)->delete();

        return response()->json([
            'status' => true,
            'message' => 'Custom option deleted successfully',
            'data' => null
        ]);
    }

    private function rules(): array
    {
        return [
            'label' => 'required|string|max:255',
            'type' => 'required|in:text,image,dropdown',
            'is_required' => 'nullable|boolean',
            'max_length' => 'nullable|integer|min:1|max:1000',
            'choices' => 'nullable|array|required_if:type,dropdown',
            'choices.*' => 'required|string|max:255',
            'sort_order' => 'nullable|integer|min:0',
        ];
    }
}
