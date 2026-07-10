<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\AttachTrackingNumberRequest;
use App\Http\Requests\UpdateShipmentStatusRequest;
use App\Models\Order;
use App\Models\Shipment;
use App\Models\ShippingMethod;
use App\Services\ShipmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShipmentController extends Controller
{
    protected ShipmentService $shipmentService;

    public function __construct(ShipmentService $shipmentService)
    {
        $this->shipmentService = $shipmentService;
    }

    /**
     * Get a paginated and filterable list of shipments.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $query = Shipment::with(['order', 'shippingMethod.shippingZone', 'trackingEvents']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('shipping_zone_id')) {
            $query->whereHas('shippingMethod', function ($q) use ($request) {
                $q->where('shipping_zone_id', $request->shipping_zone_id);
            });
        }

        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereBetween('created_at', [
                $request->start_date . ' 00:00:00',
                $request->end_date . ' 23:59:59'
            ]);
        }

        $shipments = $query->orderBy('created_at', 'desc')->paginate($request->get('per_page', 15));

        return response()->json([
            'status' => true,
            'message' => 'Shipments retrieved successfully.',
            'data' => $shipments
        ]);
    }

    /**
     * Get a single shipment.
     *
     * @param Shipment $shipment
     * @return JsonResponse
     */
    public function show(Shipment $shipment): JsonResponse
    {
        $shipment->load(['order', 'shippingMethod.shippingZone', 'trackingEvents' => function ($q) {
            $q->orderBy('event_time', 'desc');
        }]);

        return response()->json([
            'status' => true,
            'message' => 'Shipment details retrieved successfully.',
            'data' => $shipment
        ]);
    }

    /**
     * Create a shipment manually for an order.
     *
     * @param Request $request
     * @param Order $order
     * @return JsonResponse
     */
    public function store(Request $request, Order $order): JsonResponse
    {
        $validated = $request->validate([
            'shipping_method_id' => 'required|exists:shipping_methods,id',
        ]);

        if ($order->shipment()->exists()) {
            return response()->json([
                'status' => false,
                'message' => 'Shipment already exists for this order.',
                'data' => null
            ], 422);
        }

        $method = ShippingMethod::findOrFail($validated['shipping_method_id']);
        $shipment = $this->shipmentService->createShipment($order, $method);

        return response()->json([
            'status' => true,
            'message' => 'Shipment created successfully.',
            'data' => $shipment
        ], 201);
    }

    /**
     * Update shipment status.
     *
     * @param UpdateShipmentStatusRequest $request
     * @param Shipment $shipment
     * @return JsonResponse
     */
    public function updateStatus(UpdateShipmentStatusRequest $request, Shipment $shipment): JsonResponse
    {
        $validated = $request->validated();

        try {
            $updated = $this->shipmentService->updateStatus($shipment, $validated['status'], $validated);
            return response()->json([
                'status' => true,
                'message' => 'Shipment status updated successfully.',
                'data' => $updated
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage(),
                'data' => null
            ], 422);
        }
    }

    /**
     * Attach a tracking number and carrier to a shipment.
     *
     * @param AttachTrackingNumberRequest $request
     * @param Shipment $shipment
     * @return JsonResponse
     */
    public function attachTrackingNumber(AttachTrackingNumberRequest $request, Shipment $shipment): JsonResponse
    {
        $validated = $request->validated();

        $updated = $this->shipmentService->attachTrackingNumber(
            $shipment,
            $validated['tracking_number'],
            $validated['carrier_name']
        );

        return response()->json([
            'status' => true,
            'message' => 'Tracking information attached successfully.',
            'data' => $updated
        ]);
    }
}
