<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Requests\GetShippingEstimateRequest;
use App\Models\Order;
use App\Services\ShippingZoneService;
use App\Services\ShippingRateService;
use App\Services\DeliveryEstimateService;
use Illuminate\Http\JsonResponse;

class ShippingController extends Controller
{
    protected ShippingZoneService $zoneService;
    protected ShippingRateService $rateService;
    protected DeliveryEstimateService $estimateService;

    public function __construct(
        ShippingZoneService $zoneService,
        ShippingRateService $rateService,
        DeliveryEstimateService $estimateService
    ) {
        $this->zoneService = $zoneService;
        $this->rateService = $rateService;
        $this->estimateService = $estimateService;
    }

    /**
     * Get eligible shipping methods, calculated rates, and delivery estimates for checkout.
     *
     * @param GetShippingEstimateRequest $request
     * @return JsonResponse
     */
    public function getEstimate(GetShippingEstimateRequest $request): JsonResponse
    {
        $validated = $request->validated();
        
        try {
            $zone = $this->zoneService->resolveZoneForAddress(
                $validated['country_code'],
                $validated['pincode'] ?? null
            );
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage(),
                'data' => null
            ], 404);
        }

        $orderValue = (float) $validated['order_value'];
        $currency = $validated['currency'];

        $methods = $this->rateService->getEligibleMethods($zone, $orderValue, $currency);

        $data = $methods->map(function ($method) use ($orderValue, $currency, $validated, $zone) {
            $cost = $this->rateService->calculateCost($method, $orderValue, $currency);
            
            $estimate = $this->estimateService->estimate(
                $zone,
                $validated['pincode'] ?? null,
                now(),
                $method->processing_days
            );

            return [
                'id' => $method->id,
                'name' => $method->name,
                'carrier_type' => $method->carrier_type,
                'carrier_name' => $method->carrier_name,
                'requires_signature' => $method->requires_signature,
                'cost' => $cost,
                'estimated_delivery' => $estimate,
            ];
        });

        return response()->json([
            'status' => true,
            'message' => 'Shipping estimates calculated successfully.',
            'data' => $data
        ]);
    }

    /**
     * Get shipment + tracking history for customer's own order.
     *
     * @param Order $order
     * @return JsonResponse
     */
    public function getOrderShipment(Order $order): JsonResponse
    {
        if ($order->user_id !== auth()->id()) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized access to order shipment.',
                'data' => null
            ], 403);
        }

        $shipment = $order->shipment()
            ->with(['trackingEvents' => function ($q) {
                $q->orderBy('event_time', 'desc');
            }, 'shippingMethod'])
            ->first();

        if (!$shipment) {
            return response()->json([
                'status' => false,
                'message' => 'No shipment found for this order.',
                'data' => null
            ], 404);
        }

        return response()->json([
            'status' => true,
            'message' => 'Shipment retrieved successfully.',
            'data' => $shipment
        ]);
    }
}
