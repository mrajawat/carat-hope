<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Region extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'currency_code',
        'currency_symbol',
        'is_default',
    ];

    protected $casts = [
        'is_default' => 'boolean',
    ];

    /**
     * Get the tax rules for this region.
     */
    public function taxRules()
    {
        return $this->hasMany(RegionTaxRule::class);
    }
}
