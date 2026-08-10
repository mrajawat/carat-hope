<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ShippingProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ShippingProfileController extends Controller
{
    /**
     * List delivery profiles. products_count drives the "N active listings"
     * label shown next to each profile on the product form.
     */
    public function index(Request $request)
    {
        $query = ShippingProfile::withCount('products');

        if ($request->has('is_active')) {
            $query->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN));
        }

        return response()->json([
            'status' => true,
            'message' => 'Shipping profiles retrieved successfully',
            'data' => $query->orderByDesc('is_default')->orderBy('name')->get()
        ]);
    }

    /**
     * A profile with its per-zone rates and any profile-specific free-shipping rules.
     */
    public function show($id)
    {
        $profile = ShippingProfile::withCount('products')
            ->with([
                'shippingMethods' => fn ($q) => $q->orderBy('sort_order'),
                'shippingMethods.shippingZone',
                'shippingThresholds',
            ])
            ->findOrFail($id);

        return response()->json([
            'status' => true,
            'message' => 'Shipping profile retrieved successfully',
            'data' => $profile
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate($this->rules());

        $profile = DB::transaction(function () use ($validated) {
            $profile = ShippingProfile::create($validated);
            $this->syncDefault($profile);

            return $profile;
        });

        return response()->json([
            'status' => true,
            'message' => 'Shipping profile created successfully',
            'data' => $profile
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $profile = ShippingProfile::findOrFail($id);
        $validated = $request->validate($this->rules());

        DB::transaction(function () use ($profile, $validated) {
            $profile->update($validated);
            $this->syncDefault($profile);
        });

        return response()->json([
            'status' => true,
            'message' => 'Shipping profile updated successfully',
            'data' => $profile->fresh()
        ]);
    }

    public function destroy($id)
    {
        $profile = ShippingProfile::withCount('products')->findOrFail($id);

        if ($profile->products_count > 0) {
            return response()->json([
                'status' => false,
                'message' => "This profile is used by {$profile->products_count} product(s). Reassign them before deleting.",
                'data' => null
            ], 422);
        }

        $profile->delete();

        return response()->json([
            'status' => true,
            'message' => 'Shipping profile deleted successfully',
            'data' => null
        ]);
    }

    private function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'origin_pincode' => 'nullable|string|max:20',
            'origin_country_code' => 'nullable|string|size:2',
            'is_default' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
        ];
    }

    /**
     * Only one profile may be the default at a time.
     */
    private function syncDefault(ShippingProfile $profile): void
    {
        if ($profile->is_default) {
            ShippingProfile::where('id', '!=', $profile->id)->update(['is_default' => false]);
        }
    }
}
