<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RegionTaxRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'region_id',
        'tax_name',
        'tax_percentage',
        'inclusive',
    ];

    protected $casts = [
        'tax_percentage' => 'decimal:2',
        'inclusive' => 'boolean',
    ];

    /**
     * Get the region this tax rule belongs to.
     */
    public function region()
    {
        return $this->belongsTo(Region::class);
    }
}
