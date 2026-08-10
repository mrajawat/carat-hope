<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Distinguishes curated variation types (which buyers can filter by on the
     * storefront) from ones a merchant created ad hoc via "Create your own",
     * which are not surfaced in buyer-facing filters.
     *
     * Defaults to false so the seeded standard types stay filterable.
     */
    public function up(): void
    {
        Schema::table('attributes', function (Blueprint $table) {
            $table->boolean('is_custom')->default(false)->after('is_global');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attributes', function (Blueprint $table) {
            $table->dropColumn('is_custom');
        });
    }
};
