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
     * @return Collection
     */
    public function getEligibleMethods(ShippingZone $zone, float $orderValue, string $currency): Collection
    {
        $currencyUpper = strtoupper($currency);
        $threshold = config("shipping.high_value_thresholds.{$currencyUpper}");

        if ($threshold === null) {
            $threshold = $this->convertAmount(600.00, 'USD', $currency);
        }

        $isHighValue = $orderValue > $threshold;

        $methods = $zone->shippingMethods()
            ->where('is_active', true)
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

        // Check for free shipping threshold
        $shippingCost = $baseCost;
        $threshold = $zone->shippingThresholds()->where('currency', $currency)->first();

        if ($threshold) {
            $minOrderVal = (float) $threshold->min_order_value;
        } else {
            $anyThreshold = $zone->shippingThresholds()->first();
            if ($anyThreshold) {
                $minOrderVal = $this->convertAmount((float) $anyThreshold->min_order_value, $anyThreshold->currency, $currency);
            } else {
                $minOrderVal = null;
            }
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
