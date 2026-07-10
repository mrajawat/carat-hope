<?php

namespace App\Shipping\Providers;

use App\Models\Shipment;

class ManualShipmentProvider implements ShipmentProviderInterface
{
    /**
     * Create tracking for a shipment (for manual, we just return current or placeholder).
     *
     * @param Shipment $shipment
     * @return array
     */
    public function createTracking(Shipment $shipment): array
    {
        return [
            'tracking_number' => $shipment->tracking_number ?? 'MANUAL-' . strtoupper(uniqid()),
            'carrier_name' => $shipment->carrier_name ?? 'Manual Carrier',
        ];
    }

    /**
     * Fetch status for a shipment (returns null for manual provider).
     *
     * @param Shipment $shipment
     * @return string|null
     */
    public function fetchStatus(Shipment $shipment): ?string
    {
        return null; // Not implemented / manual
    }
}
