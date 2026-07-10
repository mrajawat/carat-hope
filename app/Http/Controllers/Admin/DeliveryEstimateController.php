<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDeliveryEstimateRequest;
use App\Http\Requests\UpdateDeliveryEstimateRequest;
use App\Models\DeliveryEstimate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeliveryEstimateController extends Controller
{
    /**
     * Display a listing of delivery estimates.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $estimates = DeliveryEstimate::with('shippingZone')
            ->orderBy('id', 'desc')
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'status' => true,
            'message' => 'Delivery estimates retrieved successfully.',
            'data' => $estimates
        ]);
    }

    /**
     * Store a newly created delivery estimate.
     *
     * @param StoreDeliveryEstimateRequest $request
     * @return JsonResponse
     */
    public function store(StoreDeliveryEstimateRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $estimate = DeliveryEstimate::create($validated);

        return response()->json([
            'status' => true,
            'message' => 'Delivery estimate created successfully.',
            'data' => $estimate
        ], 201);
    }

    /**
     * Display the specified delivery estimate.
     *
     * @param DeliveryEstimate $deliveryEstimate
     * @return JsonResponse
     */
    public function show(DeliveryEstimate $deliveryEstimate): JsonResponse
    {
        $deliveryEstimate->load('shippingZone');

        return response()->json([
            'status' => true,
            'message' => 'Delivery estimate retrieved successfully.',
            'data' => $deliveryEstimate
        ]);
    }

    /**
     * Update the specified delivery estimate.
     *
     * @param UpdateDeliveryEstimateRequest $request
     * @param DeliveryEstimate $deliveryEstimate
     * @return JsonResponse
     */
    public function update(UpdateDeliveryEstimateRequest $request, DeliveryEstimate $deliveryEstimate): JsonResponse
    {
        $validated = $request->validated();

        $deliveryEstimate->update($validated);

        return response()->json([
            'status' => true,
            'message' => 'Delivery estimate updated successfully.',
            'data' => $deliveryEstimate
        ]);
    }

    /**
     * Remove the specified delivery estimate.
     *
     * @param DeliveryEstimate $deliveryEstimate
     * @return JsonResponse
     */
    public function destroy(DeliveryEstimate $deliveryEstimate): JsonResponse
    {
        $deliveryEstimate->delete();

        return response()->json([
            'status' => true,
            'message' => 'Delivery estimate deleted successfully.',
            'data' => null
        ]);
    }
}
