<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductGstRate extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'region_id',
        'gst_percentage',
    ];

    protected $casts = [
        'gst_percentage' => 'decimal:2',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function region()
    {
        return $this->belongsTo(Region::class);
    }
}
