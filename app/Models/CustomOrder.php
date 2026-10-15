<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class CustomOrder extends Model
{
    use HasFactory;

    public const STATUSES = ['pending', 'in_review', 'quoted', 'accepted', 'in_production', 'completed', 'rejected', 'cancelled'];

    public const MAX_IMAGES = 5;

    protected $fillable = [
        'reference_no',
        'user_id',
        'full_name',
        'email',
        'phone',
        'country',
        'type_of_jewellery_id',
        'metal_type_id',
        'metal_sub_type_id',
        'primary_gemstone_id',
        'occasion_id',
        'description',
        'target_date',
        'engraving_text',
        'images',
        'image_description',
        'status',
        'admin_notes',
    ];

    protected $casts = [
        'images' => 'array',
        'target_date' => 'date:Y-m-d',
    ];

    protected static function booted()
    {
        static::creating(function (CustomOrder $order) {
            if (empty($order->reference_no)) {
                do {
                    $reference = 'CO-' . now()->format('ymd') . '-' . strtoupper(Str::random(5));
                } while (static::where('reference_no', $reference)->exists());

                $order->reference_no = $reference;
            }
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function typeOfJewellery()
    {
        return $this->belongsTo(CustomOrderOption::class, 'type_of_jewellery_id');
    }

    public function metalType()
    {
        return $this->belongsTo(CustomOrderOption::class, 'metal_type_id');
    }

    public function metalSubType()
    {
        return $this->belongsTo(CustomOrderOption::class, 'metal_sub_type_id');
    }

    public function primaryGemstone()
    {
        return $this->belongsTo(CustomOrderOption::class, 'primary_gemstone_id');
    }

    public function occasion()
    {
        return $this->belongsTo(CustomOrderOption::class, 'occasion_id');
    }

    /**
     * Relations to eager load so responses carry option names, not just ids.
     */
    public static function optionRelations(): array
    {
        return [
            'typeOfJewellery:id,name',
            'metalType:id,name',
            'metalSubType:id,name',
            'primaryGemstone:id,name',
            'occasion:id,name',
        ];
    }
}
