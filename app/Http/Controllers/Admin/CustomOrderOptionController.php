<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Public\CustomOrderController as PublicCustomOrderController;
use App\Models\CustomOrder;
use App\Models\CustomOrderOption;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Manages the dropdown values of the custom order form: jewellery types,
 * metal types and their sub types, gemstones and occasions.
 */
class CustomOrderOptionController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'type' => ['nullable', Rule::in(CustomOrderOption::TYPES)],
            'parent_id' => 'nullable|integer',
        ]);

        $options = CustomOrderOption::with('parent:id,name')
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->type))
            ->when($request->filled('parent_id'), fn ($q) => $q->where('parent_id', $request->integer('parent_id')))
            ->orderBy('type')
            ->ordered()
            ->get();

        return response()->json([
            'success' => true,
            'data' => $options
        ]);
    }

    public function show($id)
    {
        $option = CustomOrderOption::with(['parent:id,name', 'children'])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $option
        ]);
    }

    public function store(Request $request)
    {
        $request->validate($this->rules($request));

        $option = CustomOrderOption::create([
            'type' => $request->type,
            'parent_id' => $request->type === 'metal_sub_type' ? $request->parent_id : null,
            'name' => $request->name,
            'sort_order' => $request->input('sort_order', 0),
            'status' => $request->input('status', 'active'),
        ]);

        $this->flushCache();

        return response()->json([
            'success' => true,
            'message' => 'Option created successfully',
            'data' => $option->load('parent:id,name')
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $option = CustomOrderOption::findOrFail($id);

        // Type is fixed once created; only metal sub types may move to another metal
        $request->merge(['type' => $option->type]);
        $request->validate($this->rules($request, $option));

        $option->update([
            'parent_id' => $option->type === 'metal_sub_type' ? $request->parent_id : null,
            'name' => $request->name,
            'sort_order' => $request->input('sort_order', $option->sort_order),
            'status' => $request->input('status', $option->status),
        ]);

        $this->flushCache();

        return response()->json([
            'success' => true,
            'message' => 'Option updated successfully',
            'data' => $option->load('parent:id,name')
        ]);
    }

    public function destroy($id)
    {
        $option = CustomOrderOption::findOrFail($id);

        $optionIds = $option->children()->pluck('id')->push($option->id);
        $inUse = CustomOrder::where(function ($q) use ($optionIds) {
            foreach (['type_of_jewellery_id', 'metal_type_id', 'metal_sub_type_id', 'primary_gemstone_id', 'occasion_id'] as $column) {
                $q->orWhereIn($column, $optionIds);
            }
        })->exists();

        if ($inUse) {
            return response()->json([
                'success' => false,
                'message' => 'This option is used by existing custom orders. Deactivate it instead.'
            ], 422);
        }

        $option->delete();
        $this->flushCache();

        return response()->json([
            'success' => true,
            'message' => 'Option deleted successfully'
        ]);
    }

    public function toggleStatus($id)
    {
        $option = CustomOrderOption::findOrFail($id);
        $option->status = $option->status === 'active' ? 'inactive' : 'active';
        $option->save();

        $this->flushCache();

        return response()->json([
            'success' => true,
            'message' => 'Option status updated successfully',
            'data' => $option
        ]);
    }

    private function rules(Request $request, ?CustomOrderOption $option = null): array
    {
        $isSubType = $request->type === 'metal_sub_type';

        return [
            'type' => ['required', Rule::in(CustomOrderOption::TYPES)],
            'parent_id' => [
                Rule::requiredIf($isSubType),
                'nullable',
                'integer',
                Rule::exists('custom_order_options', 'id')->where('type', 'metal_type'),
            ],
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('custom_order_options', 'name')
                    ->where('type', $request->type)
                    ->where(fn ($q) => $isSubType
                        ? $q->where('parent_id', $request->parent_id)
                        : $q->whereNull('parent_id'))
                    ->ignore($option?->id),
            ],
            'sort_order' => 'nullable|integer|min:0',
            'status' => 'nullable|in:active,inactive',
        ];
    }

    private function flushCache(): void
    {
        cache()->forget(PublicCustomOrderController::OPTIONS_CACHE_KEY);
    }
}
