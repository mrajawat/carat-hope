<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AttributeValue extends Model
{
    use HasFactory;

    protected $fillable = [
        'attribute_id',
        'value',
        'scale',
        'price_modifier',
        'sort_order',
    ];

    protected $casts = [
        'price_modifier' => 'decimal:2',
        'sort_order' => 'integer',
    ];

    /**
     * Get the attribute that owns this value.
     */
    public function attribute()
    {
        return $this->belongsTo(Attribute::class);
    }

    /**
     * Variants offered under this value, e.g. "Gold" -> 14k, 18k, 22k.
     * Used by the custom order form's metal type -> variant dropdowns.
     */
    public function variants()
    {
        return $this->belongsToMany(AttributeValue::class, 'attribute_value_variants', 'parent_value_id', 'variant_value_id')
            ->withPivot('sort_order')
            ->withTimestamps()
            ->orderByPivot('sort_order')
            ->orderBy('attribute_values.value');
    }
}
