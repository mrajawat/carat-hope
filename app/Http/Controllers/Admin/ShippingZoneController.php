<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreShippingZoneRequest;
use App\Http\Requests\UpdateShippingZoneRequest;
use App\Models\ShippingZone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShippingZoneController extends Controller
{
    /**
     * Display a listing of shipping zones.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $zones = ShippingZone::with(['region', 'shippingMethods', 'shippingThresholds', 'deliveryEstimates'])
            ->orderBy('id', 'desc')
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'status' => true,
            'message' => 'Shipping zones retrieved successfully.',
            'data' => $zones
        ]);
    }

    /**
     * Store a newly created shipping zone.
     *
     * @param StoreShippingZoneRequest $request
     * @return JsonResponse
     */
    public function store(StoreShippingZoneRequest $request): JsonResponse
    {
        $validated = $request->validated();
        
        $zone = ShippingZone::create($validated);

        return response()->json([
            'status' => true,
            'message' => 'Shipping zone created successfully.',
            'data' => $zone
        ], 201);
    }

    /**
     * Display the specified shipping zone.
     *
     * @param ShippingZone $shippingZone
     * @return JsonResponse
     */
    public function show(ShippingZone $shippingZone): JsonResponse
    {
        $shippingZone->load(['region', 'shippingMethods', 'shippingThresholds', 'deliveryEstimates']);

        return response()->json([
            'status' => true,
            'message' => 'Shipping zone retrieved successfully.',
            'data' => $shippingZone
        ]);
    }

    /**
     * Update the specified shipping zone.
     *
     * @param UpdateShippingZoneRequest $request
     * @param ShippingZone $shippingZone
     * @return JsonResponse
     */
    public function update(UpdateShippingZoneRequest $request, ShippingZone $shippingZone): JsonResponse
    {
        $validated = $request->validated();
        
        $shippingZone->update($validated);

        return response()->json([
            'status' => true,
            'message' => 'Shipping zone updated successfully.',
            'data' => $shippingZone
        ]);
    }

    /**
     * Remove the specified shipping zone.
     *
     * @param ShippingZone $shippingZone
     * @return JsonResponse
     */
    public function destroy(ShippingZone $shippingZone): JsonResponse
    {
        $shippingZone->delete();

        return response()->json([
            'status' => true,
            'message' => 'Shipping zone deleted successfully.',
            'data' => null
        ]);
    }
}
