<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Whether an attribute acts as a variation is a per-product decision, not a
     * property of the attribute itself: the same "Gemstone" is a flat attribute on
     * one product and a variation axis on another. What the attribute does own is
     * whether it is *allowed* to be promoted at all - Materials and Gold purity
     * never are. Hence a capability flag rather than a mutually-exclusive type.
     *
     * Also adds a selectable unit list (Pendant width offers cm/inches/...) and
     * extends input_type with 'boolean' for the Yes/No radio fields.
     */
    public function up(): void
    {
        Schema::table('attributes', function (Blueprint $table) {
            $table->boolean('can_be_variation')->default(true)->after('slug')->index();
            $table->json('allowed_units')->nullable()->after('unit');
        });

        // An earlier revision of this feature shipped a `type` enum that this flag
        // replaces. Guarded so the migration runs cleanly whether or not that
        // column was ever created.
        if (Schema::hasColumn('attributes', 'type')) {
            Schema::table('attributes', function (Blueprint $table) {
                $table->dropColumn('type');
            });
        }

        // Raw statement so this does not depend on doctrine/dbal being installed
        DB::statement("ALTER TABLE attributes MODIFY COLUMN input_type ENUM('select','number','text','boolean') NOT NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE attributes MODIFY COLUMN input_type ENUM('select','number','text') NOT NULL");

        Schema::table('attributes', function (Blueprint $table) {
            $table->dropColumn(['can_be_variation', 'allowed_units']);
        });
    }
};
