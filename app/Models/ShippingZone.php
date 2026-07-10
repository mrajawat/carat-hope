<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ShippingZone extends Model
{
    use HasFactory;

    protected $fillable = [
        'region_id',
        'name',
        'country_codes',
        'is_active',
    ];

    protected $casts = [
        'country_codes' => 'array',
        'is_active' => 'boolean',
    ];

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    public function shippingMethods(): HasMany
    {
        return $this->hasMany(ShippingMethod::class);
    }

    public function shippingThresholds(): HasMany
    {
        return $this->hasMany(ShippingThreshold::class);
    }

    public function deliveryEstimates(): HasMany
    {
        return $this->hasMany(DeliveryEstimate::class);
    }
}
