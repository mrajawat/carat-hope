<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreShippingMethodRequest;
use App\Http\Requests\UpdateShippingMethodRequest;
use App\Models\ShippingMethod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShippingMethodController extends Controller
{
    /**
     * Display a listing of shipping methods.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $methods = ShippingMethod::with('shippingZone')
            ->orderBy('sort_order')
            ->orderBy('id', 'desc')
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'status' => true,
            'message' => 'Shipping methods retrieved successfully.',
            'data' => $methods
        ]);
    }

    /**
     * Store a newly created shipping method.
     *
     * @param StoreShippingMethodRequest $request
     * @return JsonResponse
     */
    public function store(StoreShippingMethodRequest $request): JsonResponse
    {
        $validated = $request->validated();
        
        $method = ShippingMethod::create($validated);

        return response()->json([
            'status' => true,
            'message' => 'Shipping method created successfully.',
            'data' => $method
        ], 201);
    }

    /**
     * Display the specified shipping method.
     *
     * @param ShippingMethod $shippingMethod
     * @return JsonResponse
     */
    public function show(ShippingMethod $shippingMethod): JsonResponse
    {
        $shippingMethod->load('shippingZone');

        return response()->json([
            'status' => true,
            'message' => 'Shipping method retrieved successfully.',
            'data' => $shippingMethod
        ]);
    }

    /**
     * Update the specified shipping method.
     *
     * @param UpdateShippingMethodRequest $request
     * @param ShippingMethod $shippingMethod
     * @return JsonResponse
     */
    public function update(UpdateShippingMethodRequest $request, ShippingMethod $shippingMethod): JsonResponse
    {
        $validated = $request->validated();
        
        $shippingMethod->update($validated);

        return response()->json([
            'status' => true,
            'message' => 'Shipping method updated successfully.',
            'data' => $shippingMethod
        ]);
    }

    /**
     * Remove the specified shipping method.
     *
     * @param ShippingMethod $shippingMethod
     * @return JsonResponse
     */
    public function destroy(ShippingMethod $shippingMethod): JsonResponse
    {
        $shippingMethod->delete();

        return response()->json([
            'status' => true,
            'message' => 'Shipping method deleted successfully.',
            'data' => null
        ]);
    }
}
