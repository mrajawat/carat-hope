<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WishlistItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'product_id',
        'product_variant_id',
    ];

    protected $hidden = [
        'variant_key',
    ];

    protected static function booted(): void
    {
        // Keep the unique-index helper column in step with the nullable variant id.
        static::saving(function (WishlistItem $item) {
            $item->variant_key = $item->product_variant_id ?? 0;
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function variant()
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }
}
