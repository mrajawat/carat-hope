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
            if (!Schema::hasColumn('products', 'tags')) {
                $table->json('tags')->nullable()->after('description');
            }
            if (!Schema::hasColumn('products', 'materials')) {
                $table->json('materials')->nullable()->after('tags');
            }
            if (!Schema::hasColumn('products', 'gold_solidity')) {
                $table->json('gold_solidity')->nullable()->after('materials');
            }
            if (!Schema::hasColumn('products', 'gold_purity')) {
                $table->json('gold_purity')->nullable()->after('gold_solidity');
            }
            if (!Schema::hasColumn('products', 'listing_attributes')) {
                $table->json('listing_attributes')->nullable()->after('gold_purity');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $columns = ['tags', 'materials', 'gold_solidity', 'gold_purity', 'listing_attributes'];
            foreach ($columns as $column) {
                if (Schema::hasColumn('products', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
