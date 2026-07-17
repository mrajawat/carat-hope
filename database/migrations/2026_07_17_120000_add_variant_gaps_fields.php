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
            if (!Schema::hasColumn('products', 'processing_time_varies')) {
                $table->boolean('processing_time_varies')->default(false)->after('skus_vary');
            }
        });

        Schema::table('product_variants', function (Blueprint $table) {
            if (!Schema::hasColumn('product_variants', 'processing_days')) {
                $table->integer('processing_days')->nullable()->after('stock_quantity');
            }
        });

        Schema::table('product_images', function (Blueprint $table) {
            if (!Schema::hasColumn('product_images', 'variant_option_id')) {
                $table->foreignId('variant_option_id')->nullable()->after('product_id')->constrained('attribute_values')->nullOnDelete();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_images', function (Blueprint $table) {
            if (Schema::hasColumn('product_images', 'variant_option_id')) {
                $table->dropForeign(['variant_option_id']);
                $table->dropColumn('variant_option_id');
            }
        });

        Schema::table('product_variants', function (Blueprint $table) {
            if (Schema::hasColumn('product_variants', 'processing_days')) {
                $table->dropColumn('processing_days');
            }
        });

        Schema::table('products', function (Blueprint $table) {
            if (Schema::hasColumn('products', 'processing_time_varies')) {
                $table->dropColumn('processing_time_varies');
            }
        });
    }
};
