<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->boolean('prices_vary')->default(true);
            $table->boolean('quantities_vary')->default(true);
            $table->boolean('skus_vary')->default(true);
            $table->tinyInteger('max_variation_axes')->default(2);
            $table->integer('total_stock')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn([
                'prices_vary',
                'quantities_vary',
                'skus_vary',
                'max_variation_axes',
                'total_stock'
            ]);
        });
    }
};
