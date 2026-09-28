<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->integer('sort_order')->default(0)->after('status');
            $table->string('menu_banner_image')->nullable()->after('sort_order');
            $table->string('menu_banner_link', 500)->nullable()->after('menu_banner_image');
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn(['sort_order', 'menu_banner_image', 'menu_banner_link']);
        });
    }
};
