<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductCustomOption extends Model
{
    use HasFactory;

    /**
     * Etsy-style cap on how many personalisation fields one listing may collect.
     */
    public const MAX_PER_PRODUCT = 5;

    protected $fillable = [
        'product_id',
        'label',
        'type',
        'is_required',
        'max_length',
        'choices',
        'sort_order',
    ];

    protected $casts = [
        'is_required' => 'boolean',
        'max_length' => 'integer',
        'choices' => 'array',
        'sort_order' => 'integer',
    ];

    protected static function booted()
    {
        // Enforced here rather than only in the controller so the cap holds no
        // matter which path creates the option.
        static::creating(function (self $option) {
            $existing = static::where('product_id', $option->product_id)->count();

            if ($existing >= self::MAX_PER_PRODUCT) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'custom_options' => 'You can create up to ' . self::MAX_PER_PRODUCT
                        . ' custom option fields per listing.',
                ]);
            }
        });
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
