<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Helpers\ImageHelper;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CategoryController extends Controller
{
    public function index(Request $request)
    {
        $query = Category::with('parent');

        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Only categories with no children — products are assigned to leaf categories
        if (filter_var($request->get('leaf_only'), FILTER_VALIDATE_BOOLEAN)) {
            $query->whereDoesntHave('children');
        }

        $categories = $query->orderBy('name')->get();

        // Build the full breadcrumb path ("Jewellery > Necklaces > Pendant Necklaces")
        // from an id-keyed map so ancestors resolve without extra queries.
        $allCategories = Category::select('id', 'name', 'parent_id')->get()->keyBy('id');

        $categories->each(function ($category) use ($allCategories) {
            $segments = [];
            $current = $allCategories->get($category->id);
            $guard = 0;

            while ($current && $guard++ < 10) {
                array_unshift($segments, $current->name);
                $current = $current->parent_id ? $allCategories->get($current->parent_id) : null;
            }

            $category->path = implode(' > ', $segments);
        });

        return response()->json([
            'success' => true,
            'data' => $categories->values()
        ]);
    }

    public function show($id)
    {
        $category = Category::with('parent')->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $category
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string',
            'image' => 'nullable|string',
            'description' => 'nullable|string',
            'parent_id' => 'nullable|exists:categories,id',
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
        // Ensure slug is unique
        $originalSlug = $slug;
        $count = 1;
        while (Category::where('slug', $slug)->exists()) {
            $slug = $originalSlug . '-' . $count++;
        }

        $category = Category::create([
            'name' => $request->name,
            'slug' => $slug,
            'image' => $imageUrl,
            'description' => $request->description,
            'parent_id' => $request->parent_id,
            'status' => 'active'
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Category created successfully',
            'data' => $category
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $category = Category::findOrFail($id);

        $request->validate([
            'name' => 'required|string',
            'image' => 'nullable|string',
            'description' => 'nullable|string',
            'parent_id' => 'nullable|exists:categories,id|different:id',
        ]);

        $data = [
            'name' => $request->name,
            'description' => $request->description,
            'parent_id' => $request->parent_id,
        ];

        if ($category->name !== $request->name) {
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

        $category->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Category updated successfully',
            'data' => $category
        ]);
    }

    public function destroy($id)
    {
        $category = Category::findOrFail($id);
        $category->delete();

        return response()->json([
            'success' => true,
            'message' => 'Category deleted successfully'
        ]);
    }

    public function toggleStatus($id)
    {
        $category = Category::findOrFail($id);
        $category->status = $category->status === 'active' ? 'inactive' : 'active';
        $category->save();

        return response()->json([
            'success' => true,
            'message' => 'Category status updated successfully',
            'data' => $category
        ]);
    }
}
