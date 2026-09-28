<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MenuSection extends Model
{
    public const PUBLIC_MENU_CACHE_KEY = 'public_menu';

    protected $fillable = [
        'category_id',
        'title',
        'sort_order',
        'status',
    ];

    protected $casts = [
        'category_id' => 'integer',
        'sort_order' => 'integer',
    ];

    protected static function booted()
    {
        static::saved(fn () => cache()->forget(self::PUBLIC_MENU_CACHE_KEY));
        static::deleted(fn () => cache()->forget(self::PUBLIC_MENU_CACHE_KEY));
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function links()
    {
        return $this->hasMany(MenuLink::class)->orderBy('sort_order')->orderBy('id');
    }
}
