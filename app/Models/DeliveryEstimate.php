<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeliveryEstimate extends Model
{
    use HasFactory;

    protected $fillable = [
        'shipping_zone_id',
        'pincode_prefix',
        'min_days',
        'max_days',
        'cutoff_time',
    ];

    protected $casts = [
        'min_days' => 'integer',
        'max_days' => 'integer',
    ];

    public function shippingZone(): BelongsTo
    {
        return $this->belongsTo(ShippingZone::class);
    }
}
