<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MenuLink extends Model
{
    protected $fillable = [
        'menu_section_id',
        'label',
        'category_id',
        'filters',
        'url',
        'sort_order',
        'status',
    ];

    protected $casts = [
        'menu_section_id' => 'integer',
        'category_id' => 'integer',
        'filters' => 'array',
        'sort_order' => 'integer',
    ];

    protected static function booted()
    {
        static::saved(fn () => cache()->forget(MenuSection::PUBLIC_MENU_CACHE_KEY));
        static::deleted(fn () => cache()->forget(MenuSection::PUBLIC_MENU_CACHE_KEY));
    }

    public function section()
    {
        return $this->belongsTo(MenuSection::class, 'menu_section_id');
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }
}
