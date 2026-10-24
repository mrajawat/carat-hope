<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    // Values mirror the enum columns on the orders table; used for public dropdowns.
    public const ORDER_STATUSES = [
        'pending' => 'Pending',
        'processing' => 'Processing',
        'shipped' => 'Shipped',
        'delivered' => 'Delivered',
        'cancelled' => 'Cancelled',
    ];

    public const PAYMENT_STATUSES = [
        'unpaid' => 'Unpaid',
        'paid' => 'Paid',
        'refunded' => 'Refunded',
    ];

    public const PAYMENT_METHODS = [
        'razorpay' => 'Pay Online (Razorpay)',
        'cod' => 'Cash on Delivery',
    ];

    protected $fillable = [
        'user_id',
        'placed_as_guest',
        'access_token_hash',
        'subtotal_amount',
        'discount_amount',
        'gst_amount',
        'customer_name',
        'customer_email',
        'customer_phone',
        'total_amount',
        'currency',
        'payment_status',
        'order_status',
        'billing_address',
        'shipping_address',
        'notes',
        'payment_method',
        'razorpay_order_id',
        'razorpay_payment_id',
        'paid_at',
    ];

    protected $hidden = [
        'access_token_hash',
    ];

    protected $casts = [
        'placed_as_guest' => 'boolean',
        'total_amount' => 'decimal:2',
        'subtotal_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'gst_amount' => 'decimal:2',
        'billing_address' => 'array',
        'shipping_address' => 'array',
        'paid_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function order_items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function shipments()
    {
        return $this->hasMany(Shipment::class);
    }

    public function shipment()
    {
        return $this->hasOne(Shipment::class)->latestOfMany();
    }
}

