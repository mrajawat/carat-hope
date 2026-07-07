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
}
