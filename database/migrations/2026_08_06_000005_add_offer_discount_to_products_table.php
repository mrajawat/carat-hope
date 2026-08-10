<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The ceiling shown as "You'll receive offers for up to 30% off" beneath the
     * "Let buyers make offers on this listing" toggle. Only meaningful when
     * allow_offers is true.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->unsignedTinyInteger('max_offer_discount_percent')->nullable()->after('allow_offers');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('max_offer_discount_percent');
        });
    }
};
