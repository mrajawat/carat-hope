<?php

namespace App\Shipping\Providers;

use App\Models\Shipment;

interface ShipmentProviderInterface
{
    /**
     * Create tracking for a shipment.
     *
     * @param Shipment $shipment
     * @return array ['tracking_number' => string, 'carrier_name' => string]
     */
    public function createTracking(Shipment $shipment): array;

    /**
     * Fetch status for a shipment.
     *
     * @param Shipment $shipment
     * @return string|null
     */
    public function fetchStatus(Shipment $shipment): ?string;
}
