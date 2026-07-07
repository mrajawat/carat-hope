<?php

namespace App\Services;

use App\Models\Region;
use Illuminate\Http\Request;

class RegionDetectionService
{
    /**
     * Detect the region for a request.
     * Resolution order:
     * 1. Check session for selected_region_id
     * 2. IP-based country lookup
     * 3. Fallback to default region
     *
     * @param Request $request
     * @return Region
     */
    public function detect(Request $request): Region
    {
        // 1. Check session first
        if ($request->hasSession() && $request->session()->has('selected_region_id')) {
            $regionId = $request->session()->get('selected_region_id');
            $region = Region::find($regionId);
            if ($region) {
                return $region;
            }
        }

        // Check if there is an explicit header or query parameter for testing/flexibility
        if ($request->has('region_id')) {
            $region = Region::find($request->input('region_id'));
            if ($region) {
                return $region;
            }
        }

        // 2. IP-based country lookup
        $ip = $request->ip();
        if ($ip && $ip !== '127.0.0.1' && $ip !== '::1') {
            $countryCode = $this->resolveCountryFromIp($ip);
            if ($countryCode) {
                $countryCode = strtoupper($countryCode);
                $regionName = match ($countryCode) {
                    'IN' => 'India',
                    'US' => 'United States',
                    default => 'Rest of World'
                };

                $region = Region::where('name', $regionName)->first();
                if ($region) {
                    return $region;
                }
            }
        }

        // 3. Fallback to the default region
        return Region::where('is_default', true)->first() ?? Region::first();
    }

    /**
     * Resolve the ISO country code from an IP address.
     * This is structured as a swappable helper method so we can plug in other IP geolocation APIs.
     *
     * @param string $ip
     * @return string|null E.g., 'IN', 'US', etc.
     */
    public function resolveCountryFromIp(string $ip): ?string
    {
        try {
            if (class_exists(\Stevebauman\Location\Facades\Location::class)) {
                if ($position = \Stevebauman\Location\Facades\Location::get($ip)) {
                    return $position->countryCode;
                }
            }
        } catch (\Exception $e) {
            // Log/ignore errors to prevent page crash on IP resolution failure
        }
        return null;
    }
}
