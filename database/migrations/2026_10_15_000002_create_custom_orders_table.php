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
        Schema::create('custom_orders', function (Blueprint $table) {
            $table->id();
            $table->string('reference_no')->unique();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('full_name');
            $table->string('email');
            $table->string('phone', 30);
            $table->string('country', 100);

            // Options in use cannot be deleted; admins deactivate them instead
            $table->foreignId('type_of_jewellery_id')->constrained('custom_order_options')->restrictOnDelete();
            $table->foreignId('metal_type_id')->nullable()->constrained('custom_order_options')->restrictOnDelete();
            $table->foreignId('metal_sub_type_id')->nullable()->constrained('custom_order_options')->restrictOnDelete();
            $table->foreignId('primary_gemstone_id')->nullable()->constrained('custom_order_options')->restrictOnDelete();
            $table->foreignId('occasion_id')->nullable()->constrained('custom_order_options')->restrictOnDelete();

            $table->text('description');
            $table->date('target_date')->nullable();
            $table->string('engraving_text', 100)->nullable();
            $table->json('images')->nullable();
            $table->text('image_description')->nullable();

            $table->enum('status', ['pending', 'in_review', 'quoted', 'accepted', 'in_production', 'completed', 'rejected', 'cancelled'])->default('pending');
            $table->text('admin_notes')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('email');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('custom_orders');
    }
};
