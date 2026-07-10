<?php

namespace App\Models;

use App\Enums\ShipmentStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Shipment extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'shipping_method_id',
        'tracking_number',
        'carrier_name',
        'status',
        'shipping_cost',
        'insurance_cost',
        'declared_value',
        'estimated_delivery_min',
        'estimated_delivery_max',
        'dispatched_at',
        'delivered_at',
        'signature_image_url',
        'customs_hs_code',
        'customs_declaration_note',
        'notes',
    ];

    protected $casts = [
        'status' => ShipmentStatus::class,
        'shipping_cost' => 'decimal:2',
        'insurance_cost' => 'decimal:2',
        'declared_value' => 'decimal:2',
        'estimated_delivery_min' => 'date',
        'estimated_delivery_max' => 'date',
        'dispatched_at' => 'datetime',
        'delivered_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function shippingMethod(): BelongsTo
    {
        return $this->belongsTo(ShippingMethod::class);
    }

    public function trackingEvents(): HasMany
    {
        return $this->hasMany(ShipmentTrackingEvent::class);
    }
}
