<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Links inside a menu section. A link targets a category (optionally with
     * attribute filters), a filtered listing across all categories, or a URL.
     */
    public function up(): void
    {
        Schema::create('menu_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('menu_section_id')->constrained('menu_sections')->cascadeOnDelete();
            $table->string('label');
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->json('filters')->nullable();
            $table->string('url', 500)->nullable();
            $table->integer('sort_order')->default(0);
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();

            $table->index(['menu_section_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_links');
    }
};
