<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Helpers\ImageHelper;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SubcategoryController extends Controller
{
    /**
     * Display a listing of all subcategories (categories with a parent).
     */
    public function index()
    {
        $subcategories = Category::with('parent')
            ->whereNotNull('parent_id')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $subcategories
        ]);
    }

    /**
     * Display subcategories of a specific parent category.
     */
    public function subcategoriesByCategory($categoryId)
    {
        $parentCategory = Category::findOrFail($categoryId);

        $subcategories = Category::with('parent')
            ->where('parent_id', $categoryId)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'parent_category' => $parentCategory,
            'data' => $subcategories
        ]);
    }

    /**
     * Store a newly created subcategory in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'image' => 'nullable|string',
            'description' => 'nullable|string',
            'parent_id' => 'required|exists:categories,id',
        ]);

        $parent = Category::findOrFail($request->parent_id);
        if ($parent->parent_id !== null) {
            return response()->json([
                'success' => false,
                'message' => 'A subcategory cannot be assigned as a parent of another subcategory.'
            ], 422);
        }

        $imageUrl = null;
        if ($request->has('image') && !empty($request->image)) {
            try {
                $imageUrl = ImageHelper::uploadBase64($request->image, 'categories');
            } catch (\Exception $e) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage()
                ], 400);
            }
        }

        $slug = Str::slug($request->name);
        $originalSlug = $slug;
        $count = 1;
        while (Category::where('slug', $slug)->exists()) {
            $slug = $originalSlug . '-' . $count++;
        }

        $subcategory = Category::create([
            'name' => $request->name,
            'slug' => $slug,
            'image' => $imageUrl,
            'description' => $request->description,
            'parent_id' => $request->parent_id,
            'status' => 'active'
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Subcategory created successfully',
            'data' => $subcategory->load('parent')
        ], 201);
    }

    /**
     * Store a newly created subcategory specifically under a given category ID from the route.
     */
    public function storeByCategory(Request $request, $categoryId)
    {
        $parent = Category::findOrFail($categoryId);
        if ($parent->parent_id !== null) {
            return response()->json([
                'success' => false,
                'message' => 'A subcategory cannot be assigned as a parent of another subcategory.'
            ], 422);
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'image' => 'nullable|string',
            'description' => 'nullable|string',
        ]);

        $imageUrl = null;
        if ($request->has('image') && !empty($request->image)) {
            try {
                $imageUrl = ImageHelper::uploadBase64($request->image, 'categories');
            } catch (\Exception $e) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage()
                ], 400);
            }
        }

        $slug = Str::slug($request->name);
        $originalSlug = $slug;
        $count = 1;
        while (Category::where('slug', $slug)->exists()) {
            $slug = $originalSlug . '-' . $count++;
        }

        $subcategory = Category::create([
            'name' => $request->name,
            'slug' => $slug,
            'image' => $imageUrl,
            'description' => $request->description,
            'parent_id' => $categoryId,
            'status' => 'active'
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Subcategory created successfully',
            'data' => $subcategory->load('parent')
        ], 201);
    }

    /**
     * Display the specified subcategory.
     */
    public function show($id)
    {
        $subcategory = Category::with('parent')
            ->whereNotNull('parent_id')
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $subcategory
        ]);
    }

    /**
     * Update the specified subcategory in storage.
     */
    public function update(Request $request, $id)
    {
        $subcategory = Category::whereNotNull('parent_id')->findOrFail($id);

        $request->validate([
            'name' => 'required|string|max:255',
            'image' => 'nullable|string',
            'description' => 'nullable|string',
            'parent_id' => 'required|exists:categories,id|different:id',
        ]);

        $parent = Category::findOrFail($request->parent_id);
        if ($parent->parent_id !== null) {
            return response()->json([
                'success' => false,
                'message' => 'A subcategory cannot be assigned as a parent of another subcategory.'
            ], 422);
        }

        $data = [
            'name' => $request->name,
            'description' => $request->description,
            'parent_id' => $request->parent_id,
        ];

        if ($subcategory->name !== $request->name) {
            $slug = Str::slug($request->name);
            $originalSlug = $slug;
            $count = 1;
            while (Category::where('slug', $slug)->where('id', '!=', $id)->exists()) {
                $slug = $originalSlug . '-' . $count++;
            }
            $data['slug'] = $slug;
        }

        if ($request->has('image') && !empty($request->image)) {
            try {
                $data['image'] = ImageHelper::uploadBase64($request->image, 'categories');
            } catch (\Exception $e) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage()
                ], 400);
            }
        }

        $subcategory->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Subcategory updated successfully',
            'data' => $subcategory->load('parent')
        ]);
    }

    /**
     * Remove the specified subcategory from storage.
     */
    public function destroy($id)
    {
        $subcategory = Category::whereNotNull('parent_id')->findOrFail($id);
        $subcategory->delete();

        return response()->json([
            'success' => true,
            'message' => 'Subcategory deleted successfully'
        ]);
    }

    /**
     * Toggle the status of a subcategory.
     */
    public function toggleStatus($id)
    {
        $subcategory = Category::whereNotNull('parent_id')->findOrFail($id);
        $subcategory->status = $subcategory->status === 'active' ? 'inactive' : 'active';
        $subcategory->save();

        return response()->json([
            'success' => true,
            'message' => 'Subcategory status updated successfully',
            'data' => $subcategory->load('parent')
        ]);
    }
}
