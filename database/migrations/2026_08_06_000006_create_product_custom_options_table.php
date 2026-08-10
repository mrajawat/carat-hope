<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Buyer-supplied personalisation fields ("Engraving text", "Upload your photo").
     * Unlike variations these create no variants and never affect inventory - the
     * buyer fills them in at purchase time. Capped at 5 per product in the
     * application layer.
     */
    public function up(): void
    {
        Schema::create('product_custom_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('label');
            $table->enum('type', ['text', 'image', 'dropdown'])->default('text');
            $table->boolean('is_required')->default(false);
            $table->unsignedSmallInteger('max_length')->nullable();
            $table->json('choices')->nullable();
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['product_id', 'sort_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_custom_options');
    }
};
