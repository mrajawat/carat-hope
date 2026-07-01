<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Coupon extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'discount_type',
        'discount_value',
        'expiry_date',
        'usage_limit',
        'usage_count',
        'status',
    ];

    protected $casts = [
        'discount_value' => 'decimal:2',
        'expiry_date' => 'datetime',
        'usage_limit' => 'integer',
        'usage_count' => 'integer',
    ];
}
