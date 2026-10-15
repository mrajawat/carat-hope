<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Dropdown values for the personalised jewellery (custom order) form.
     * Metal sub types point at their metal type through parent_id
     * (e.g. Gold -> 18K Yellow Gold).
     */
    public function up(): void
    {
        Schema::create('custom_order_options', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['jewellery_type', 'metal_type', 'metal_sub_type', 'gemstone', 'occasion']);
            $table->foreignId('parent_id')->nullable()->constrained('custom_order_options')->cascadeOnDelete();
            $table->string('name');
            $table->integer('sort_order')->default(0);
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();

            $table->index(['type', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('custom_order_options');
    }
};
