<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * How many values a product may pick for this attribute, driving the
     * "Select up to 5" / "Select up to 4" hints on the Attributes screen.
     * Null means unlimited.
     */
    public function up(): void
    {
        Schema::table('attributes', function (Blueprint $table) {
            $table->unsignedSmallInteger('max_selections')->nullable()->after('allowed_units');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attributes', function (Blueprint $table) {
            $table->dropColumn('max_selections');
        });
    }
};
