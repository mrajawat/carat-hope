<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Region;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RegionController extends Controller
{
    /**
     * Display a listing of regions with their tax rules.
     */
    public function index()
    {
        $regions = Region::with('taxRules')->get();

        return response()->json([
            'status' => true,
            'message' => 'Regions retrieved successfully',
            'data' => $regions
        ]);
    }

    /**
     * Store a newly created region with optional tax rules.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'currency_code' => 'required|string|max:10',
            'currency_symbol' => 'required|string|max:10',
            'is_default' => 'nullable|boolean',
            'tax_rules' => 'nullable|array',
            'tax_rules.*.tax_name' => 'required|string',
            'tax_rules.*.tax_percentage' => 'required|numeric|min:0|max:100',
            'tax_rules.*.inclusive' => 'nullable|boolean',
        ]);

        $region = DB::transaction(function () use ($validated) {
            if (!empty($validated['is_default'])) {
                Region::where('is_default', true)->update(['is_default' => false]);
            }

            $region = Region::create([
                'name' => $validated['name'],
                'currency_code' => $validated['currency_code'],
                'currency_symbol' => $validated['currency_symbol'],
                'is_default' => $validated['is_default'] ?? false,
            ]);

            if (!empty($validated['tax_rules'])) {
                foreach ($validated['tax_rules'] as $rule) {
                    $region->taxRules()->create([
                        'tax_name' => $rule['tax_name'],
                        'tax_percentage' => $rule['tax_percentage'],
                        'inclusive' => $rule['inclusive'] ?? false,
                    ]);
                }
            }

            return $region;
        });

        return response()->json([
            'status' => true,
            'message' => 'Region created successfully',
            'data' => $region->load('taxRules')
        ], 201);
    }

    /**
     * Display the specified region.
     */
    public function show($id)
    {
        $region = Region::with('taxRules')->findOrFail($id);

        return response()->json([
            'status' => true,
            'message' => 'Region retrieved successfully',
            'data' => $region
        ]);
    }

    /**
     * Update the specified region.
     */
    public function update(Request $request, $id)
    {
        $region = Region::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'currency_code' => 'required|string|max:10',
            'currency_symbol' => 'required|string|max:10',
            'is_default' => 'nullable|boolean',
        ]);

        DB::transaction(function () use ($validated, $region) {
            if (!empty($validated['is_default']) && !$region->is_default) {
                Region::where('is_default', true)->update(['is_default' => false]);
            }
            $region->update($validated);
        });

        return response()->json([
            'status' => true,
            'message' => 'Region updated successfully',
            'data' => $region->load('taxRules')
        ]);
    }

    /**
     * Remove the specified region.
     */
    public function destroy($id)
    {
        $region = Region::findOrFail($id);

        if ($region->is_default) {
            return response()->json([
                'status' => false,
                'message' => 'Cannot delete the default region',
                'data' => null
            ], 422);
        }

        $region->delete();

        return response()->json([
            'status' => true,
            'message' => 'Region deleted successfully',
            'data' => null
        ]);
    }
}
