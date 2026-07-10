<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShippingThreshold extends Model
{
    use HasFactory;

    protected $fillable = [
        'shipping_zone_id',
        'min_order_value',
        'currency',
    ];

    protected $casts = [
        'min_order_value' => 'decimal:2',
    ];

    public function shippingZone(): BelongsTo
    {
        return $this->belongsTo(ShippingZone::class);
    }
}
