<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * A named, reusable delivery profile shared across listings ("free shipping",
     * dispatched from 302001). The per-destination rates and transit times live in
     * shipping_methods, which each belong to one zone; a profile groups one method
     * per zone so a product can be assigned a single profile and still resolve
     * correct rates for every destination.
     *
     * origin_pincode is where the merchant dispatches from - not modelled anywhere
     * previously (delivery_estimates.pincode_prefix is the destination prefix).
     */
    public function up(): void
    {
        Schema::create('shipping_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('origin_pincode')->nullable();
            $table->string('origin_country_code', 2)->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shipping_profiles');
    }
};
