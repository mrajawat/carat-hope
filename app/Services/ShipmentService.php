<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Shipment;
use App\Models\ShippingMethod;
use App\Enums\ShipmentStatus;
use App\Shipping\Providers\ShipmentProviderInterface;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ShipmentService
{
    protected ShipmentProviderInterface $provider;

    public function __construct(ShipmentProviderInterface $provider)
    {
        $this->provider = $provider;
    }

    /**
     * Create a shipment for an order.
     *
     * @param Order $order
     * @param ShippingMethod $method
     * @return Shipment
     */
    public function createShipment(Order $order, ShippingMethod $method): Shipment
    {
        return DB::transaction(function () use ($order, $method) {
            $orderValue = (float) $order->total_amount;
            $shippingAddress = $order->shipping_address;
            
            $countryCode = $shippingAddress['country_code'] ?? 'IN';
            $pincode = $shippingAddress['pincode'] ?? $shippingAddress['postal_code'] ?? $shippingAddress['zip'] ?? null;

            $currency = $method->shippingZone->region?->currency_code ?? 'INR';

            // 1. Calculate costs
            $rateService = app(ShippingRateService::class);
            $costDetails = $rateService->calculateCost($method, $orderValue, $currency);

            // 2. Calculate delivery dates estimate
            $estimateService = app(DeliveryEstimateService::class);
            $estimate = $estimateService->estimate(
                $method->shippingZone,
                $pincode,
                $order->created_at ?? Carbon::now(),
                $method->processing_days
            );

            // 3. Create Shipment
            $shipment = Shipment::create([
                'order_id' => $order->id,
                'shipping_method_id' => $method->id,
                'tracking_number' => null,
                'carrier_name' => $method->carrier_name ?? 'Manual Carrier',
                'status' => ShipmentStatus::PENDING,
                'shipping_cost' => $costDetails['shipping_cost'],
                'insurance_cost' => $costDetails['insurance_cost'],
                'declared_value' => $orderValue,
                'estimated_delivery_min' => $estimate['min_date'],
                'estimated_delivery_max' => $estimate['max_date'],
                'dispatched_at' => null,
                'delivered_at' => null,
                'signature_image_url' => null,
                'customs_hs_code' => null,
                'customs_declaration_note' => null,
                'notes' => null,
            ]);

            // 4. Log initial tracking event
            $shipment->trackingEvents()->create([
                'status' => ShipmentStatus::PENDING,
                'location' => null,
                'description' => 'Shipment pending processing.',
                'event_time' => Carbon::now(),
                'created_by' => null,
            ]);

            return $shipment;
        });
    }

    /**
     * Update the shipment status with validation and history logging.
     *
     * @param Shipment $shipment
     * @param string $newStatus
     * @param array $eventData ['location' => ..., 'description' => ..., 'event_time' => ..., 'created_by' => ...]
     * @return Shipment
     */
    public function updateStatus(Shipment $shipment, string $newStatus, array $eventData = []): Shipment
    {
        return DB::transaction(function () use ($shipment, $newStatus, $eventData) {
            $currentStatus = $shipment->status instanceof ShipmentStatus 
                ? $shipment->status->value 
                : $shipment->status;

            // Define valid transitions
            $validNext = match($currentStatus) {
                'pending' => ['processing', 'dispatched', 'cancelled'],
                'processing' => ['dispatched', 'in_transit', 'cancelled'],
                'dispatched' => ['in_transit', 'out_for_delivery', 'failed_attempt', 'cancelled'],
                'in_transit' => ['out_for_delivery', 'delivered', 'failed_attempt', 'cancelled'],
                'out_for_delivery' => ['delivered', 'failed_attempt', 'cancelled'],
                'failed_attempt' => ['out_for_delivery', 'in_transit', 'returned', 'cancelled'],
                'delivered' => [],
                'returned' => [],
                'cancelled' => [],
                default => []
            };

            if (!in_array($newStatus, $validNext)) {
                throw new \InvalidArgumentException("Invalid status transition from '{$currentStatus}' to '{$newStatus}'.");
            }

            $shipment->status = ShipmentStatus::from($newStatus);

            if ($newStatus === 'dispatched') {
                $shipment->dispatched_at = Carbon::now();
                $shipment->order->update(['order_status' => 'shipped']);
            } elseif ($newStatus === 'in_transit') {
                $shipment->order->update(['order_status' => 'shipped']);
            } elseif ($newStatus === 'delivered') {
                $shipment->delivered_at = Carbon::now();
                $shipment->order->update(['order_status' => 'delivered']);
            } elseif ($newStatus === 'cancelled') {
                $shipment->order->update(['order_status' => 'cancelled']);
            }

            // Optional signature image URL updates or other shipment details if provided
            if (isset($eventData['signature_image_url'])) {
                $shipment->signature_image_url = $eventData['signature_image_url'];
            }
            if (isset($eventData['customs_hs_code'])) {
                $shipment->customs_hs_code = $eventData['customs_hs_code'];
            }
            if (isset($eventData['customs_declaration_note'])) {
                $shipment->customs_declaration_note = $eventData['customs_declaration_note'];
            }
            if (isset($eventData['notes'])) {
                $shipment->notes = $eventData['notes'];
            }

            $shipment->save();

            // Resolve creator (must be User or null to avoid FK errors if Admin)
            $createdBy = null;
            if (isset($eventData['created_by'])) {
                $createdBy = $eventData['created_by'];
            } elseif (auth()->check() && auth()->user() instanceof \App\Models\User) {
                $createdBy = auth()->id();
            }

            $shipment->trackingEvents()->create([
                'status' => ShipmentStatus::from($newStatus),
                'location' => $eventData['location'] ?? null,
                'description' => $eventData['description'] ?? 'Status updated to ' . $newStatus,
                'event_time' => isset($eventData['event_time']) ? Carbon::parse($eventData['event_time']) : Carbon::now(),
                'created_by' => $createdBy,
            ]);

            return $shipment;
        });
    }

    /**
     * Attach a tracking number to a shipment.
     *
     * @param Shipment $shipment
     * @param string $trackingNumber
     * @param string $carrierName
     * @return Shipment
     */
    public function attachTrackingNumber(Shipment $shipment, string $trackingNumber, string $carrierName): Shipment
    {
        return DB::transaction(function () use ($shipment, $trackingNumber, $carrierName) {
            $shipment->tracking_number = $trackingNumber;
            $shipment->carrier_name = $carrierName;
            $shipment->save();

            $currentStatus = $shipment->status instanceof ShipmentStatus 
                ? $shipment->status->value 
                : $shipment->status;

            if (in_array($currentStatus, ['pending', 'processing'])) {
                $this->updateStatus($shipment, 'dispatched', [
                    'description' => "Tracking attached: {$trackingNumber} via {$carrierName}.",
                ]);
            }

            return $shipment;
        });
    }
}
