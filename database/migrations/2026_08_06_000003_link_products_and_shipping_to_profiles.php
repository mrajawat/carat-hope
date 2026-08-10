<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Points products at the new reusable profiles and drops the freeform JSON
     * columns they replace. The JSON columns were never populated, so no data
     * migration is required.
     *
     * shipping_methods gains a profile link so a profile owns one method per zone;
     * shipping_thresholds gains a nullable one so a profile may override the
     * zone-wide free-shipping rule while existing zone rules keep applying.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->unsignedBigInteger('processing_profile_id')->nullable()->after('delivery_option');
            $table->unsignedBigInteger('shipping_profile_id')->nullable()->after('processing_profile_id');

            $table->foreign('processing_profile_id')
                ->references('id')->on('processing_profiles')->nullOnDelete();
            $table->foreign('shipping_profile_id')
                ->references('id')->on('shipping_profiles')->nullOnDelete();
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['processing_profile', 'delivery_option']);
        });

        Schema::table('shipping_methods', function (Blueprint $table) {
            $table->unsignedBigInteger('shipping_profile_id')->nullable()->after('shipping_zone_id');

            $table->foreign('shipping_profile_id')
                ->references('id')->on('shipping_profiles')->cascadeOnDelete();
            $table->index(['shipping_profile_id', 'is_active']);
        });

        Schema::table('shipping_thresholds', function (Blueprint $table) {
            $table->unsignedBigInteger('shipping_profile_id')->nullable()->after('shipping_zone_id');

            $table->foreign('shipping_profile_id')
                ->references('id')->on('shipping_profiles')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shipping_thresholds', function (Blueprint $table) {
            $table->dropForeign(['shipping_profile_id']);
            $table->dropColumn('shipping_profile_id');
        });

        Schema::table('shipping_methods', function (Blueprint $table) {
            $table->dropForeign(['shipping_profile_id']);
            $table->dropIndex(['shipping_profile_id', 'is_active']);
            $table->dropColumn('shipping_profile_id');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->json('processing_profile')->nullable();
            $table->json('delivery_option')->nullable();
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['processing_profile_id']);
            $table->dropForeign(['shipping_profile_id']);
            $table->dropColumn(['processing_profile_id', 'shipping_profile_id']);
        });
    }
};
