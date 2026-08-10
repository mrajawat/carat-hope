<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Some attributes offer different value sets depending on a chosen scale:
     * Ring size in US is 000, 00, 0, 1/4 ... while FR is 41, 44, 47 ... The scale
     * options themselves live in attributes.allowed_units; this column scopes each
     * value to one of them.
     *
     * Null means the value applies regardless of scale, which is the case for
     * every attribute that has no scale selector (Gemstone, Primary colour, ...).
     */
    public function up(): void
    {
        Schema::table('attribute_values', function (Blueprint $table) {
            $table->string('scale', 20)->nullable()->after('value');
            $table->index(['attribute_id', 'scale']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attribute_values', function (Blueprint $table) {
            $table->dropIndex(['attribute_id', 'scale']);
            $table->dropColumn('scale');
        });
    }
};
