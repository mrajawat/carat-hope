<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * A variant's SKU is generated from its attribute values, which are only
     * associated after the row exists - so the column has to tolerate a null
     * between insert and generation. It is populated immediately afterwards by
     * SkuGeneratorService, or set to the product's shared SKU when skus_vary
     * is false.
     *
     * Raw statement so this does not depend on doctrine/dbal being installed.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE product_variants MODIFY COLUMN sku VARCHAR(255) NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("UPDATE product_variants SET sku = CONCAT('SKU-', id) WHERE sku IS NULL");
        DB::statement('ALTER TABLE product_variants MODIFY COLUMN sku VARCHAR(255) NOT NULL');
    }
};
