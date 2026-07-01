<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Banner extends Model
{
    use HasFactory;

    protected $fillable = [
        'image',
        'badge',
        'title',
        'description',
        'status',
    ];

    protected static function booted()
    {
        static::saved(function () {
            cache()->forget('public_banners');
        });

        static::deleted(function () {
            cache()->forget('public_banners');
        });
    }
}
