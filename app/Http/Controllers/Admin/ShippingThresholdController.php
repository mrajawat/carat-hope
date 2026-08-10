<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ShippingThreshold;
use Illuminate\Http\Request;

class ShippingThresholdController extends Controller
{
    /**
     * Free-shipping rules: order value above which shipping is waived.
     *
     * A threshold with a shipping_profile_id applies only to that profile;
     * one without applies to every profile serving the zone.
     */
    public function index(Request $request)
    {
        $query = ShippingThreshold::with(['shippingZone', 'shippingProfile']);

        if ($request->filled('shipping_zone_id')) {
            $query->where('shipping_zone_id', $request->shipping_zone_id);
        }

        if ($request->filled('shipping_profile_id')) {
            $query->where('shipping_profile_id', $request->shipping_profile_id);
        }

        return response()->json([
            'status' => true,
            'message' => 'Shipping thresholds retrieved successfully',
            'data' => $query->get()
        ]);
    }

    public function show($id)
    {
        return response()->json([
            'status' => true,
            'message' => 'Shipping threshold retrieved successfully',
            'data' => ShippingThreshold::with(['shippingZone', 'shippingProfile'])->findOrFail($id)
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate($this->rules());

        $threshold = ShippingThreshold::create($validated);

        return response()->json([
            'status' => true,
            'message' => 'Shipping threshold created successfully',
            'data' => $threshold->load(['shippingZone', 'shippingProfile'])
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $threshold = ShippingThreshold::findOrFail($id);
        $threshold->update($request->validate($this->rules()));

        return response()->json([
            'status' => true,
            'message' => 'Shipping threshold updated successfully',
            'data' => $threshold->fresh()->load(['shippingZone', 'shippingProfile'])
        ]);
    }

    public function destroy($id)
    {
        ShippingThreshold::findOrFail($id)->delete();

        return response()->json([
            'status' => true,
            'message' => 'Shipping threshold deleted successfully',
            'data' => null
        ]);
    }

    private function rules(): array
    {
        return [
            'shipping_zone_id' => 'required|exists:shipping_zones,id',
            'shipping_profile_id' => 'nullable|exists:shipping_profiles,id',
            'min_order_value' => 'required|numeric|min:0',
            'currency' => 'required|string|size:3',
        ];
    }
}
