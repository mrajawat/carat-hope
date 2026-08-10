<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ShippingProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'origin_pincode',
        'origin_country_code',
        'is_default',
        'is_active',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /**
     * The per-zone rates and transit times this profile offers.
     */
    public function shippingMethods(): HasMany
    {
        return $this->hasMany(ShippingMethod::class);
    }

    /**
     * Profile-specific free-shipping rules, overriding the zone-wide ones.
     */
    public function shippingThresholds(): HasMany
    {
        return $this->hasMany(ShippingThreshold::class);
    }
}
