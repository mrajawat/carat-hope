<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Attribute;
use App\Models\AttributeValue;
use Illuminate\Http\Request;

class AttributeController extends Controller
{
    /**
     * Display a listing of attributes.
     */
    public function index()
    {
        $attributes = Attribute::with('values')->get();

        return response()->json([
            'status' => true,
            'message' => 'Attributes retrieved successfully',
            'data' => $attributes
        ]);
    }

    /**
     * Store a newly created attribute.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:attributes,slug',
            'input_type' => 'required|in:select,number,text',
            'unit' => 'nullable|string|max:50',
            'affects_price' => 'nullable|boolean',
        ]);

        $attribute = Attribute::create($validated);

        return response()->json([
            'status' => true,
            'message' => 'Attribute created successfully',
            'data' => $attribute->load('values')
        ], 201);
    }

    /**
     * Display the specified attribute.
     */
    public function show($id)
    {
        $attribute = Attribute::with('values')->findOrFail($id);

        return response()->json([
            'status' => true,
            'message' => 'Attribute retrieved successfully',
            'data' => $attribute
        ]);
    }

    /**
     * Update the specified attribute.
     */
    public function update(Request $request, $id)
    {
        $attribute = Attribute::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:attributes,slug,' . $id,
            'input_type' => 'required|in:select,number,text',
            'unit' => 'nullable|string|max:50',
            'affects_price' => 'nullable|boolean',
        ]);

        $attribute->update($validated);

        return response()->json([
            'status' => true,
            'message' => 'Attribute updated successfully',
            'data' => $attribute->load('values')
        ]);
    }

    /**
     * Remove the specified attribute.
     */
    public function destroy($id)
    {
        $attribute = Attribute::findOrFail($id);
        $attribute->delete();

        return response()->json([
            'status' => true,
            'message' => 'Attribute deleted successfully',
            'data' => null
        ]);
    }

    /**
     * Store a value for a specific attribute.
     */
    public function storeValue(Request $request, $attributeId)
    {
        $attribute = Attribute::findOrFail($attributeId);

        $validated = $request->validate([
            'value' => 'required|string|max:255',
            'price_modifier' => 'nullable|numeric',
            'sort_order' => 'nullable|integer',
        ]);

        $value = $attribute->values()->create([
            'value' => $validated['value'],
            'price_modifier' => $validated['price_modifier'] ?? 0.00,
            'sort_order' => $validated['sort_order'] ?? 0,
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Attribute value created successfully',
            'data' => $value
        ], 201);
    }

    /**
     * Remove an attribute value option.
     */
    public function destroyValue($valueId)
    {
        $value = AttributeValue::findOrFail($valueId);
        $value->delete();

        return response()->json([
            'status' => true,
            'message' => 'Attribute value deleted successfully',
            'data' => null
        ]);
    }
}
