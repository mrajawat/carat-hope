<?php

namespace App\Services;

use App\Models\ShippingZone;
use App\Models\ShippingMethod;
use App\Models\ProductVariant;
use App\Models\Region;
use Illuminate\Support\Collection;

class ShippingRateService
{
    /**
     * Get all shipping methods eligible for a zone and order value.
     *
     * @param ShippingZone $zone
     * @param float $orderValue
     * @param string $currency
     * @param int|null $shippingProfileId Restrict to the delivery profile the cart's
     *                                    products use; null returns every method in
     *                                    the zone (profile-agnostic behaviour).
     * @return Collection
     */
    public function getEligibleMethods(
        ShippingZone $zone,
        float $orderValue,
        string $currency,
        ?int $shippingProfileId = null
    ): Collection {
        $currencyUpper = strtoupper($currency);
        $threshold = config("shipping.high_value_thresholds.{$currencyUpper}");

        if ($threshold === null) {
            $threshold = $this->convertAmount(600.00, 'USD', $currency);
        }

        $isHighValue = $orderValue > $threshold;

        $methods = $zone->shippingMethods()
            ->where('is_active', true)
            ->when(
                $shippingProfileId !== null,
                // Methods with no profile are shared defaults available to every profile
                fn ($q) => $q->where(function ($sub) use ($shippingProfileId) {
                    $sub->where('shipping_profile_id', $shippingProfileId)
                        ->orWhereNull('shipping_profile_id');
                })
            )
            ->orderBy('sort_order')
            ->get();

        if ($isHighValue) {
            $methods = $methods->filter(function ($method) {
                return $method->requires_signature && 
                       !is_null($method->insurance_percentage) && 
                       $method->insurance_percentage > 0;
            });
        }

        return $methods->values();
    }

    /**
     * Calculate the shipping and insurance cost for a method.
     *
     * @param ShippingMethod $method
     * @param float $orderValue
     * @param string $currency
     * @return array ['shipping_cost' => ..., 'insurance_cost' => ..., 'total' => ...]
     */
    public function calculateCost(ShippingMethod $method, float $orderValue, string $currency): array
    {
        $zone = $method->shippingZone;
        $methodCurrency = $zone->region?->currency_code ?? $currency;

        // Base rate converted to order's currency
        $baseCost = $this->convertAmount((float) $method->base_rate, $methodCurrency, $currency);

        // Insurance cost calculation
        $insuranceCost = 0.00;
        if (!is_null($method->insurance_percentage) && $method->insurance_percentage > 0) {
            $insuranceCost = $orderValue * ($method->insurance_percentage / 100);
        }

        // Check for free shipping threshold. A threshold tied to this method's
        // delivery profile wins over the zone-wide rule, so a "free shipping"
        // profile can differ from a paid one serving the same zone.
        $shippingCost = $baseCost;

        $thresholds = $zone->shippingThresholds()
            ->where(function ($q) use ($method) {
                $q->whereNull('shipping_profile_id');

                if ($method->shipping_profile_id) {
                    $q->orWhere('shipping_profile_id', $method->shipping_profile_id);
                }
            })
            ->get();

        // If the profile defines its own rules they replace the zone's entirely,
        // rather than the two competing on currency match.
        $profileSpecific = $thresholds->whereNotNull('shipping_profile_id');
        $candidates = $profileSpecific->isNotEmpty() ? $profileSpecific : $thresholds;

        $threshold = $candidates->firstWhere('currency', $currency) ?? $candidates->first();

        if (!$threshold) {
            $minOrderVal = null;
        } elseif ($threshold->currency === $currency) {
            $minOrderVal = (float) $threshold->min_order_value;
        } else {
            $minOrderVal = $this->convertAmount((float) $threshold->min_order_value, $threshold->currency, $currency);
        }

        if ($minOrderVal !== null && $orderValue >= $minOrderVal) {
            $shippingCost = 0.00;
        }

        return [
            'shipping_cost' => round($shippingCost, 2),
            'insurance_cost' => round($insuranceCost, 2),
            'total' => round($shippingCost + $insuranceCost, 2),
        ];
    }

    /**
     * Convert an amount between currencies using the regional pricing service when possible.
     *
     * @param float $amount
     * @param string $fromCurrency
     * @param string $toCurrency
     * @return float
     */
    public function convertAmount(float $amount, string $fromCurrency, string $toCurrency): float
    {
        $fromCurrency = strtoupper(trim($fromCurrency));
        $toCurrency = strtoupper(trim($toCurrency));

        if ($fromCurrency === $toCurrency) {
            return $amount;
        }

        try {
            $variant = ProductVariant::where('is_active', true)->first();
            if ($variant) {
                $fromRegion = Region::where('currency_code', $fromCurrency)->first();
                $toRegion = Region::where('currency_code', $toCurrency)->first();
                if ($fromRegion && $toRegion) {
                    $pricingService = app(VariantPricingService::class);
                    $fromPriceData = $pricingService->getPriceForRegion($variant, $fromRegion->id);
                    $toPriceData = $pricingService->getPriceForRegion($variant, $toRegion->id);

                    $fromPrice = $fromPriceData['price'];
                    $toPrice = $toPriceData['price'];

                    if ($fromPrice > 0) {
                        $rate = $toPrice / $fromPrice;
                        return $amount * $rate;
                    }
                }
            }
        } catch (\Exception $e) {
            // Ignore and fall back
        }

        // Fallback exchange rate (INR <=> USD)
        if ($fromCurrency === 'USD' && $toCurrency === 'INR') {
            return $amount * 83.00;
        }
        if ($fromCurrency === 'INR' && $toCurrency === 'USD') {
            return $amount / 83.00;
        }

        return $amount;
    }
}
