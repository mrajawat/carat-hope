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
            if (!Schema::hasColumn('products', 'is_global_pricing_enabled')) {
                $table->boolean('is_global_pricing_enabled')->default(true)->after('prices_vary');
            }
            if (!Schema::hasColumn('products', 'allow_offers')) {
                $table->boolean('allow_offers')->default(false)->after('is_global_pricing_enabled');
            }
            if (!Schema::hasColumn('products', 'processing_profile')) {
                $table->json('processing_profile')->nullable()->after('listing_attributes');
            }
            if (!Schema::hasColumn('products', 'delivery_option')) {
                $table->json('delivery_option')->nullable()->after('processing_profile');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $columns = ['is_global_pricing_enabled', 'allow_offers', 'processing_profile', 'delivery_option'];
            foreach ($columns as $column) {
                if (Schema::hasColumn('products', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
