<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'image',
        'description',
        'parent_id',
        'status',
        'sort_order',
        'menu_banner_image',
        'menu_banner_link',
    ];

    protected $casts = [
        'parent_id' => 'integer',
        'sort_order' => 'integer',
    ];

    protected static function booted()
    {
        static::saved(function () {
            cache()->forget('public_categories');
            cache()->forget('public_category_tree');
            cache()->forget(MenuSection::PUBLIC_MENU_CACHE_KEY);
        });

        static::deleted(function () {
            cache()->forget('public_categories');
            cache()->forget('public_category_tree');
            cache()->forget(MenuSection::PUBLIC_MENU_CACHE_KEY);
        });
    }

    public function parent()
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(Category::class, 'parent_id');
    }

    public function menuSections()
    {
        return $this->hasMany(MenuSection::class)->orderBy('sort_order')->orderBy('id');
    }

    public function products()
    {
        return $this->hasMany(Product::class);
    }

    public function attributes()
    {
        return $this->belongsToMany(Attribute::class, 'category_attributes')
            ->withPivot('is_required')
            ->withTimestamps();
    }
}
