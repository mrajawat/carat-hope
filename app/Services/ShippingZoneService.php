<?php

namespace App\Services;

use App\Models\ShippingZone;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class ShippingZoneService
{
    /**
     * Resolve the shipping zone for a given country code and pincode.
     *
     * @param string $countryCode
     * @param string|null $pincode
     * @return ShippingZone
     * @throws ModelNotFoundException
     */
    public function resolveZoneForAddress(string $countryCode, ?string $pincode): ShippingZone
    {
        $countryCode = strtoupper(trim($countryCode));

        $zones = ShippingZone::where('is_active', true)
            ->whereJsonContains('country_codes', $countryCode)
            ->get();

        if ($zones->isEmpty()) {
            throw new ModelNotFoundException("No active shipping zone found for country: {$countryCode}");
        }

        if ($zones->count() === 1) {
            return $zones->first();
        }

        if ($pincode) {
            $pincode = trim($pincode);
            // We can match prefixes of various lengths (e.g. 3-digit prefix, etc.)
            foreach ($zones as $zone) {
                // Check if any estimate matches the pincode prefix
                $prefixes = [
                    substr($pincode, 0, 3),
                    substr($pincode, 0, 4),
                    substr($pincode, 0, 2),
                ];
                $hasEstimate = $zone->deliveryEstimates()
                    ->whereIn('pincode_prefix', $prefixes)
                    ->exists();

                if ($hasEstimate) {
                    return $zone;
                }
            }
        }

        return $zones->first();
    }
}
