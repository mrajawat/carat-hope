<?php

namespace App\Models;

use App\Enums\CarrierType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShippingMethod extends Model
{
    use HasFactory;

    protected $fillable = [
        'shipping_zone_id',
        'name',
        'carrier_type',
        'carrier_name',
        'base_rate',
        'insurance_percentage',
        'requires_signature',
        'min_transit_days',
        'max_transit_days',
        'processing_days',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'carrier_type' => CarrierType::class,
        'base_rate' => 'decimal:2',
        'insurance_percentage' => 'decimal:2',
        'requires_signature' => 'boolean',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
        'min_transit_days' => 'integer',
        'max_transit_days' => 'integer',
        'processing_days' => 'integer',
    ];

    public function shippingZone(): BelongsTo
    {
        return $this->belongsTo(ShippingZone::class);
    }
}
