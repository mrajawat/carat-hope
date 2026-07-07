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
        'input_type',
        'unit',
        'affects_price',
    ];

    protected $casts = [
        'affects_price' => 'boolean',
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
