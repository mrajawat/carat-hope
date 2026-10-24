<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Customer wishlists: one row per saved product (optionally a specific variant).
     * Like cart items, only the choice is stored - prices are resolved live for the
     * shopper's region when the wishlist is read.
     *
     * variant_key mirrors product_variant_id with 0 for "no variant". MySQL treats
     * NULLs as distinct in unique indexes, so this non-null column is what lets the
     * database itself reject a duplicate product-level entry under concurrent adds.
     */
    public function up(): void
    {
        Schema::create('wishlist_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained('product_variants')->cascadeOnDelete();
            $table->unsignedBigInteger('variant_key')->default(0);
            $table->timestamps();

            $table->unique(['user_id', 'product_id', 'variant_key']);
            $table->index(['user_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wishlist_items');
    }
};
