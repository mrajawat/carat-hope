<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Attribute;
use App\Models\AttributeValue;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AttributeController extends Controller
{
    /**
     * Build a URL-safe, unique slug from an attribute name, appending a counter
     * on collision (e.g. "gemstone", "gemstone-1").
     */
    private function generateUniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $slug = Str::slug($name);
        $originalSlug = $slug;
        $count = 1;

        while (
            Attribute::where('slug', $slug)
                ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
                ->exists()
        ) {
            $slug = $originalSlug . '-' . $count++;
        }

        return $slug;
    }

    /**
     * Display a listing of attributes.
     */
    public function index(Request $request)
    {
        $query = Attribute::with('values');

        if ($request->has('is_global')) {
            $query->where('is_global', filter_var($request->is_global, FILTER_VALIDATE_BOOLEAN));
        }

        if ($request->has('can_be_variation')) {
            $query->where('can_be_variation', filter_var($request->can_be_variation, FILTER_VALIDATE_BOOLEAN));
        }

        if ($request->has('is_custom')) {
            $query->where('is_custom', filter_var($request->is_custom, FILTER_VALIDATE_BOOLEAN));
        }

        $attributes = $query->get();

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
            'slug' => 'nullable|string|max:255|unique:attributes,slug',
            'can_be_variation' => 'nullable|boolean',
            'input_type' => 'required|in:select,number,text,boolean',
            'unit' => 'nullable|string|max:50',
            'allowed_units' => 'nullable|array',
            'allowed_units.*' => 'required|string|max:50',
            'max_selections' => 'nullable|integer|min:1|max:100',
            'affects_price' => 'nullable|boolean',
            'is_global' => 'nullable|boolean',
            'is_custom' => 'nullable|boolean',
        ]);

        // Derive the slug from the name when the client doesn't supply one
        if (empty($validated['slug'])) {
            $validated['slug'] = $this->generateUniqueSlug($validated['name']);
        }

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
            'slug' => 'nullable|string|max:255|unique:attributes,slug,' . $id,
            'can_be_variation' => 'nullable|boolean',
            'input_type' => 'required|in:select,number,text,boolean',
            'unit' => 'nullable|string|max:50',
            'allowed_units' => 'nullable|array',
            'allowed_units.*' => 'required|string|max:50',
            'max_selections' => 'nullable|integer|min:1|max:100',
            'affects_price' => 'nullable|boolean',
            'is_global' => 'nullable|boolean',
            'is_custom' => 'nullable|boolean',
        ]);

        // Regenerate the slug when the name changed and no explicit slug was given
        if (empty($validated['slug'])) {
            $validated['slug'] = $attribute->name === $validated['name']
                ? $attribute->slug
                : $this->generateUniqueSlug($validated['name'], $attribute->id);
        }

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
     * List the selectable options for one attribute.
     *
     * Kept separate from show() because a variation type can carry well over a
     * hundred options (e.g. Gemstone), which the picker loads incrementally.
     * Pass ?search= to type-ahead, ?per_page=0 to fetch every option at once
     * (the "Add all options" action).
     */
    public function values(Request $request, $attributeId)
    {
        $attribute = Attribute::findOrFail($attributeId);

        $query = $attribute->values()
            ->orderBy('sort_order')
            ->orderBy('value');

        if ($request->filled('search')) {
            $query->where('value', 'like', '%' . $request->search . '%');
        }

        // Scale-scoped options (Ring size in US vs FR). Values with a null scale
        // apply to every scale, so they stay in the list.
        if ($request->filled('scale')) {
            $scale = $request->scale;
            $query->where(function ($q) use ($scale) {
                $q->where('scale', $scale)->orWhereNull('scale');
            });
        }

        $perPage = (int) $request->get('per_page', 25);

        if ($perPage === 0) {
            $values = $query->get();

            return response()->json([
                'status' => true,
                'message' => 'Attribute values retrieved successfully',
                'data' => $values,
                'meta' => [
                    'total' => $values->count(),
                    'attribute_id' => $attribute->id,
                    'attribute_name' => $attribute->name,
                ]
            ]);
        }

        $values = $query->paginate($perPage);

        return response()->json([
            'status' => true,
            'message' => 'Attribute values retrieved successfully',
            'data' => $values->items(),
            'meta' => [
                'total' => $values->total(),
                'per_page' => $values->perPage(),
                'current_page' => $values->currentPage(),
                'last_page' => $values->lastPage(),
                'attribute_id' => $attribute->id,
                'attribute_name' => $attribute->name,
            ]
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
            'scale' => 'nullable|string|max:20',
            'price_modifier' => 'nullable|numeric',
            'sort_order' => 'nullable|integer',
        ]);

        $value = $attribute->values()->create([
            'value' => $validated['value'],
            'scale' => $validated['scale'] ?? null,
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
