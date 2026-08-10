<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProcessingProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'min_days',
        'max_days',
        'is_default',
        'is_active',
    ];

    protected $casts = [
        'min_days' => 'integer',
        'max_days' => 'integer',
        'is_default' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
