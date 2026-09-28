<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\ImageHelper;
use App\Http\Controllers\Controller;
use App\Models\AttributeValue;
use App\Models\Category;
use App\Models\MenuLink;
use App\Models\MenuSection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Mega menu management: sections (column headings) and links under each
 * top-level category, plus the category's featured banner.
 */
class MenuController extends Controller
{
    /**
     * Full menu tree for the admin panel, including inactive sections/links.
     */
    public function index()
    {
        $parents = Category::whereNull('parent_id')
            ->with(['menuSections.links.category:id,name,slug,status'])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'status', 'sort_order', 'menu_banner_image', 'menu_banner_link']);

        return response()->json([
            'success' => true,
            'data' => $parents
        ]);
    }

    // ---------------------------------------------------------------- Sections

    public function sections($categoryId)
    {
        $parent = $this->findParentOrFail($categoryId);

        return response()->json([
            'success' => true,
            'category' => $parent->only(['id', 'name', 'slug']),
            'data' => $parent->menuSections()->with('links.category:id,name,slug,status')->get()
        ]);
    }

    public function storeSection(Request $request, $categoryId)
    {
        $parent = $this->findParentOrFail($categoryId);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'sort_order' => 'nullable|integer|min:0',
            'status' => 'nullable|in:active,inactive',
        ]);

        $section = $parent->menuSections()->create([
            'title' => $validated['title'],
            'sort_order' => $validated['sort_order'] ?? ((int) $parent->menuSections()->max('sort_order') + 1),
            'status' => $validated['status'] ?? 'active',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Menu section created successfully',
            'data' => $section
        ], 201);
    }

    public function updateSection(Request $request, $id)
    {
        $section = MenuSection::findOrFail($id);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'sort_order' => 'nullable|integer|min:0',
            'status' => 'nullable|in:active,inactive',
        ]);

        $section->update(array_filter($validated, fn ($v) => $v !== null));

        return response()->json([
            'success' => true,
            'message' => 'Menu section updated successfully',
            'data' => $section->load('links')
        ]);
    }

    public function destroySection($id)
    {
        MenuSection::findOrFail($id)->delete();

        return response()->json([
            'success' => true,
            'message' => 'Menu section deleted successfully'
        ]);
    }

    // ------------------------------------------------------------------- Links

    public function storeLink(Request $request, $sectionId)
    {
        $section = MenuSection::findOrFail($sectionId);
        $data = $this->validateLink($request);

        $data['sort_order'] = $data['sort_order'] ?? ((int) $section->links()->max('sort_order') + 1);
        $data['status'] = $data['status'] ?? 'active';

        $link = $section->links()->create($data);

        return response()->json([
            'success' => true,
            'message' => 'Menu link created successfully',
            'data' => $link->load('category:id,name,slug,status')
        ], 201);
    }

    public function updateLink(Request $request, $id)
    {
        $link = MenuLink::findOrFail($id);
        $data = $this->validateLink($request);

        // Moving a link to another section
        if ($request->filled('menu_section_id')) {
            $request->validate(['menu_section_id' => 'integer|exists:menu_sections,id']);
            $data['menu_section_id'] = (int) $request->menu_section_id;
        }

        if ($data['sort_order'] === null) {
            unset($data['sort_order']);
        }
        if ($data['status'] === null) {
            unset($data['status']);
        }

        $link->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Menu link updated successfully',
            'data' => $link->load('category:id,name,slug,status')
        ]);
    }

    public function destroyLink($id)
    {
        MenuLink::findOrFail($id)->delete();

        return response()->json([
            'success' => true,
            'message' => 'Menu link deleted successfully'
        ]);
    }

    // --------------------------------------------------------- Banner & order

    /**
     * Featured banner shown on the right of a parent category's menu panel.
     * Send `image` (base64) to replace it, `remove: true` to clear it.
     */
    public function updateBanner(Request $request, $categoryId)
    {
        $parent = $this->findParentOrFail($categoryId);

        $request->validate([
            'image' => 'nullable|string',
            'link' => ['nullable', 'string', 'max:500', 'regex:/^(\/|https?:\/\/)/i'],
            'remove' => 'nullable|boolean',
        ], [
            'link.regex' => 'Link must be a relative path (starting with /) or an http(s) URL.',
        ]);

        if ($request->boolean('remove')) {
            $parent->update(['menu_banner_image' => null, 'menu_banner_link' => null]);
        } else {
            $data = ['menu_banner_link' => $request->link];

            if ($request->filled('image')) {
                try {
                    $data['menu_banner_image'] = ImageHelper::uploadBase64($request->image, 'categories/menu');
                } catch (\Exception $e) {
                    return response()->json([
                        'success' => false,
                        'message' => $e->getMessage()
                    ], 400);
                }
            }

            $parent->update($data);
        }

        return response()->json([
            'success' => true,
            'message' => 'Menu banner updated successfully',
            'data' => $parent->only(['id', 'name', 'menu_banner_image', 'menu_banner_link'])
        ]);
    }

    /**
     * Drag-and-drop ordering. `ids` in the desired order; sort_order becomes the index.
     *   type=categories → top-level nav items
     *   type=sections   → sections of one parent (all ids must share a parent)
     *   type=links      → links of one section (all ids must share a section)
     */
    public function reorder(Request $request)
    {
        $validated = $request->validate([
            'type' => 'required|in:categories,sections,links',
            'ids' => 'required|array|min:1',
            'ids.*' => 'required|integer|distinct',
        ]);

        $ids = $validated['ids'];

        switch ($validated['type']) {
            case 'categories':
                $query = Category::whereNull('parent_id');
                $groupColumn = null;
                break;
            case 'sections':
                $query = MenuSection::query();
                $groupColumn = 'category_id';
                break;
            default:
                $query = MenuLink::query();
                $groupColumn = 'menu_section_id';
        }

        $rows = $query->whereIn('id', $ids)->get();

        if ($rows->count() !== count($ids)) {
            throw ValidationException::withMessages(['ids' => 'One or more ids are invalid for this type.']);
        }
        if ($groupColumn && $rows->pluck($groupColumn)->unique()->count() > 1) {
            throw ValidationException::withMessages(['ids' => 'All ids must belong to the same parent.']);
        }

        $byId = $rows->keyBy('id');
        DB::transaction(function () use ($ids, $byId) {
            foreach ($ids as $index => $id) {
                // save() per row so model events clear the public menu cache
                $row = $byId[$id];
                $row->sort_order = $index;
                $row->save();
            }
        });

        return response()->json([
            'success' => true,
            'message' => 'Order updated successfully'
        ]);
    }

    // ----------------------------------------------------------------- Helpers

    private function findParentOrFail($categoryId): Category
    {
        return Category::whereNull('parent_id')->findOrFail($categoryId);
    }

    /**
     * A link needs at least one target: category, filters, or url.
     * Filters are {attribute_slug: [attribute_value_id, ...]} and every value
     * must belong to the named attribute.
     */
    private function validateLink(Request $request): array
    {
        $validated = $request->validate([
            'label' => 'required|string|max:255',
            'category_id' => 'nullable|integer|exists:categories,id',
            'filters' => 'nullable|array',
            'filters.*' => 'array|min:1',
            'filters.*.*' => 'integer',
            'url' => ['nullable', 'string', 'max:500', 'regex:/^(\/|https?:\/\/)/i', 'required_without_all:category_id,filters'],
            'sort_order' => 'nullable|integer|min:0',
            'status' => 'nullable|in:active,inactive',
        ], [
            'url.required_without_all' => 'A link needs a category, filters, or a url.',
            'url.regex' => 'Url must be a relative path (starting with /) or an http(s) URL.',
        ]);

        $filters = $validated['filters'] ?? null;

        if (!empty($validated['url']) && (!empty($validated['category_id']) || !empty($filters))) {
            throw ValidationException::withMessages([
                'url' => 'A url link cannot also have a category or filters.',
            ]);
        }

        if (!empty($filters)) {
            $filters = $this->normalizeFilters($filters);
        }

        return [
            'label' => $validated['label'],
            'category_id' => $validated['category_id'] ?? null,
            'filters' => $filters ?: null,
            'url' => $validated['url'] ?? null,
            'sort_order' => $validated['sort_order'] ?? null,
            'status' => $validated['status'] ?? null,
        ];
    }

    private function normalizeFilters(array $filters): array
    {
        $normalized = [];

        foreach ($filters as $slug => $valueIds) {
            $valueIds = array_values(array_unique(array_map('intval', $valueIds)));

            $validCount = AttributeValue::whereIn('id', $valueIds)
                ->whereHas('attribute', fn ($q) => $q->where('slug', $slug))
                ->count();

            if ($validCount !== count($valueIds)) {
                throw ValidationException::withMessages([
                    "filters.{$slug}" => "Unknown attribute \"{$slug}\" or values that don't belong to it.",
                ]);
            }

            $normalized[$slug] = $valueIds;
        }

        return $normalized;
    }
}
