<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CustomOrderOption extends Model
{
    use HasFactory;

    public const TYPES = ['jewellery_type', 'metal_type', 'metal_sub_type', 'gemstone', 'occasion'];

    protected $fillable = [
        'type',
        'parent_id',
        'name',
        'sort_order',
        'status',
    ];

    protected $casts = [
        'parent_id' => 'integer',
        'sort_order' => 'integer',
    ];

    /**
     * Metal type a metal sub type belongs to.
     */
    public function parent()
    {
        return $this->belongsTo(CustomOrderOption::class, 'parent_id');
    }

    /**
     * Metal sub types of a metal type.
     */
    public function children()
    {
        return $this->hasMany(CustomOrderOption::class, 'parent_id');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }
}
