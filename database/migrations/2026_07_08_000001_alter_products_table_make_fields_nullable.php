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
            $table->boolean('has_variants')->default(false)->after('status');
            $table->string('sku')->nullable()->change();
            $table->decimal('price', 12, 2)->nullable()->change();
            $table->integer('stock_qty')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('has_variants');
            $table->string('sku')->nullable(false)->change();
            $table->decimal('price', 12, 2)->nullable(false)->change();
            $table->integer('stock_qty')->nullable(false)->change();
        });
    }
};
