<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCategoryAttributeRequest;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CategoryAttributeController extends Controller
{
    /**
     * Display category-attribute mappings.
     */
    public function index()
    {
        $mappings = DB::table('category_attributes')
            ->join('categories', 'category_attributes.category_id', '=', 'categories.id')
            ->join('attributes', 'category_attributes.attribute_id', '=', 'attributes.id')
            ->select(
                'category_attributes.id',
                'category_attributes.category_id',
                'categories.name as category_name',
                'category_attributes.attribute_id',
                'attributes.name as attribute_name',
                'category_attributes.is_required',
                'category_attributes.created_at'
            )
            ->get();

        return response()->json([
            'status' => true,
            'message' => 'Category attribute mappings retrieved successfully',
            'data' => $mappings
        ]);
    }

    /**
     * Associate an attribute with a category.
     */
    public function store(StoreCategoryAttributeRequest $request)
    {
        $category = Category::findOrFail($request->category_id);

        $category->attributes()->syncWithoutDetaching([
            $request->attribute_id => [
                'is_required' => $request->input('is_required', true)
            ]
        ]);

        $pivotRow = DB::table('category_attributes')
            ->where('category_id', $request->category_id)
            ->where('attribute_id', $request->attribute_id)
            ->first();

        return response()->json([
            'status' => true,
            'message' => 'Attribute associated with category successfully',
            'data' => $pivotRow
        ], 201);
    }

    /**
     * Dissociate an attribute from a category.
     */
    public function destroy($id)
    {
        $deleted = DB::table('category_attributes')->where('id', $id)->delete();

        if (!$deleted) {
            return response()->json([
                'status' => false,
                'message' => 'Mapping not found',
                'data' => null
            ], 404);
        }

        return response()->json([
            'status' => true,
            'message' => 'Category attribute association removed successfully',
            'data' => null
        ]);
    }

    /**
     * Get attributes applicable to a category (public route).
     */
    public function getAttributesForCategory($categoryId, \App\Services\AttributeService $attributeService)
    {
        $attributes = $attributeService->getAttributesForCategory($categoryId);

        return response()->json([
            'status' => true,
            'message' => 'Category attributes retrieved successfully',
            'data' => $attributes
        ]);
    }
}
