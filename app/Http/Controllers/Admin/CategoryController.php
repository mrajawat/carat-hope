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

        if (filter_var($request->get('roots_only'), FILTER_VALIDATE_BOOLEAN)) {
            // Only seeded parent categories — use to populate the "parent" dropdown
            $query->whereNull('parent_id');
        } elseif (filter_var($request->get('leaf_only'), FILTER_VALIDATE_BOOLEAN)) {
            // Only categories with no children — products are assigned to leaf categories.
            // Includes parents without subcategories so existing product assignments still resolve.
            $query->whereDoesntHave('children');
        } elseif (!filter_var($request->get('include_parents'), FILTER_VALIDATE_BOOLEAN)) {
            // Default master list: only admin-created subcategories. Parents come from the
            // seeder and are listed separately (roots_only / menu), so they'd show up twice.
            $query->whereNotNull('parent_id');
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
            'parent_id' => 'required|integer|exists:categories,id',
        ], [
            'parent_id.required' => 'Parent category is required. Top-level categories are managed via seeder.',
        ]);

        if ($error = $this->invalidParentResponse((int) $request->parent_id)) {
            return $error;
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

        $isRoot = $category->parent_id === null;

        $request->validate([
            'name' => 'required|string',
            'image' => 'nullable|string',
            'description' => 'nullable|string',
            // Seeded parents stay top-level; subcategories must keep a parent
            'parent_id' => $isRoot
                ? 'prohibited'
                : 'required|integer|exists:categories,id|not_in:' . $category->id,
        ], [
            'parent_id.prohibited' => 'A top-level category cannot be moved under another category.',
            'parent_id.required' => 'A subcategory must have a parent category.',
        ]);

        $data = [
            'name' => $request->name,
            'description' => $request->description,
        ];

        if (!$isRoot) {
            if ($error = $this->invalidParentResponse((int) $request->parent_id)) {
                return $error;
            }
            $data['parent_id'] = $request->parent_id;
        }

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

        if ($category->parent_id === null) {
            return response()->json([
                'success' => false,
                'message' => 'Top-level categories are managed via seeder and cannot be deleted. Deactivate it instead.'
            ], 422);
        }

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

    /**
     * Subcategories may only be attached to a top-level (seeded) category.
     */
    private function invalidParentResponse(int $parentId)
    {
        $parent = Category::find($parentId);

        if (!$parent || $parent->parent_id !== null) {
            return response()->json([
                'success' => false,
                'message' => 'Subcategories can only be added under a top-level category.'
            ], 422);
        }

        return null;
    }
}
