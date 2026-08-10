<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * How long the merchant takes to prepare an item before dispatch, as a named
     * reusable profile ("Ready to dispatch - 1-2 days"). Distinct from
     * shipping_methods.processing_days, which is tied to a carrier and zone;
     * preparation time depends on the item, not on where it is going.
     */
    public function up(): void
    {
        Schema::create('processing_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedInteger('min_days');
            $table->unsignedInteger('max_days');
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
        Schema::dropIfExists('processing_profiles');
    }
};
