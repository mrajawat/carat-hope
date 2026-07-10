<?php

namespace App\Services;

use App\Models\ShippingZone;
use Carbon\Carbon;

class DeliveryEstimateService
{
    /**
     * Calculate delivery estimates.
     *
     * @param ShippingZone $zone
     * @param string|null $pincode
     * @param Carbon|null $orderDate
     * @param int|null $processingDays
     * @return array ['min_date' => ..., 'max_date' => ..., 'min_days' => ..., 'max_days' => ...]
     */
    public function estimate(
        ShippingZone $zone, 
        ?string $pincode, 
        ?Carbon $orderDate = null, 
        ?int $processingDays = null
    ): array {
        $orderDate = $orderDate ? $orderDate->copy() : Carbon::now();
        $pincode = $pincode ? trim($pincode) : null;

        // 1. Resolve delivery estimates for that zone (pincode-specific first, fallback to zone-wide)
        $estimate = null;
        if ($pincode) {
            $estimate = $zone->deliveryEstimates()
                ->whereNotNull('pincode_prefix')
                ->whereRaw('? LIKE CONCAT(pincode_prefix, "%")', [$pincode])
                ->orderByRaw('LENGTH(pincode_prefix) DESC')
                ->first();
        }

        if (!$estimate) {
            $estimate = $zone->deliveryEstimates()
                ->whereNull('pincode_prefix')
                ->first();
        }

        $minDays = $estimate ? (int) $estimate->min_days : 3;
        $maxDays = $estimate ? (int) $estimate->max_days : 7;
        $cutoffTime = $estimate ? $estimate->cutoff_time : '14:00:00';

        // 2. Resolve processing days
        if ($processingDays === null) {
            $minProcessing = $zone->shippingMethods()
                ->where('is_active', true)
                ->min('processing_days');
            
            $processingDays = $minProcessing !== null ? (int) $minProcessing : 1;
        }

        // 3. If current time is past cutoff_time, add 1 to processing days
        $cutoffCarbon = $orderDate->copy()->setTimeFromTimeString($cutoffTime);
        if ($orderDate->greaterThan($cutoffCarbon)) {
            $processingDays += 1;
        }

        // 4. Calculate min and max delivery dates
        $minDate = $this->addBusinessDays($orderDate, $processingDays + $minDays);
        $maxDate = $this->addBusinessDays($orderDate, $processingDays + $maxDays);

        return [
            'min_date' => $minDate->toDateString(),
            'max_date' => $maxDate->toDateString(),
            'min_days' => $processingDays + $minDays,
            'max_days' => $processingDays + $maxDays,
        ];
    }

    /**
     * Add business days, skipping Sundays.
     *
     * @param Carbon $date
     * @param int $days
     * @return Carbon
     */
    private function addBusinessDays(Carbon $date, int $days): Carbon
    {
        $date = $date->copy();
        while ($days > 0) {
            $date->addDay();
            if ($date->dayOfWeek !== Carbon::SUNDAY) {
                $days--;
            }
        }
        return $date;
    }
}
