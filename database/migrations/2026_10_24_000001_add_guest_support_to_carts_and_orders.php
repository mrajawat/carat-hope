<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Guest shopping: a cart belongs either to a user or to an anonymous guest
     * token, and an order placed without logging in gets its own access token so
     * the buyer can view it straight away. Only SHA-256 hashes of the tokens are
     * stored - the plain tokens are handed to the client once.
     */
    public function up(): void
    {
        Schema::table('carts', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable()->change();
            $table->string('guest_token_hash', 64)->nullable()->unique()->after('user_id');
            $table->index('updated_at');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->boolean('placed_as_guest')->default(false)->after('user_id');
            $table->string('access_token_hash', 64)->nullable()->unique()->after('placed_as_guest');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropUnique(['access_token_hash']);
            $table->dropColumn(['placed_as_guest', 'access_token_hash']);
        });

        Schema::table('carts', function (Blueprint $table) {
            $table->dropIndex(['updated_at']);
            $table->dropUnique(['guest_token_hash']);
            $table->dropColumn('guest_token_hash');
        });

        // Guest carts have no owner and cannot survive user_id becoming required again.
        \Illuminate\Support\Facades\DB::table('carts')->whereNull('user_id')->delete();

        Schema::table('carts', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable(false)->change();
        });
    }
};
