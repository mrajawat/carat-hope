<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Attribute extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'can_be_variation',
        'input_type',
        'unit',
        'allowed_units',
        'max_selections',
        'affects_price',
        'is_global',
        'is_custom',
    ];

    protected $casts = [
        'can_be_variation' => 'boolean',
        'allowed_units' => 'array',
        'max_selections' => 'integer',
        'affects_price' => 'boolean',
        'is_global' => 'boolean',
        'is_custom' => 'boolean',
    ];

    /**
     * Get the values for this attribute.
     */
    public function values()
    {
        return $this->hasMany(AttributeValue::class);
    }

    /**
     * Get the categories this attribute is assigned to.
     */
    public function categories()
    {
        return $this->belongsToMany(Category::class, 'category_attributes')
            ->withPivot('is_required')
            ->withTimestamps();
    }
}
