<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Custom order dropdowns now come from the catalogue instead of
     * custom_order_options:
     *  - type_of_jewellery_id -> categories
     *  - metal_type_id        -> attribute_values of the "metal-type" attribute
     *  - metal_sub_type_id    -> attribute_values linked to that metal in attribute_value_variants
     *  - primary_gemstone_id  -> attribute_values of the "gemstone" attribute
     *  - occasion_id          -> free text occasion_name
     *
     * attribute_value_variants ties a metal (Gold) to its variants (14k, 18k). It is
 * many-to-many because one karat value is shared by Yellow, White and Rose Gold.
     *
     * Existing requests keep their selections where a category / attribute value
     * with the same name exists; anything that cannot be matched is left null.
     */
    public function up(): void
    {
        Schema::create('attribute_value_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_value_id')->constrained('attribute_values')->cascadeOnDelete();
            $table->foreignId('variant_value_id')->constrained('attribute_values')->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['parent_value_id', 'variant_value_id']);
        });

        Schema::table('custom_orders', function (Blueprint $table) {
            $table->dropForeign(['type_of_jewellery_id']);
            $table->dropForeign(['metal_type_id']);
            $table->dropForeign(['metal_sub_type_id']);
            $table->dropForeign(['primary_gemstone_id']);
            $table->dropForeign(['occasion_id']);

            $table->string('occasion_name', 100)->nullable()->after('primary_gemstone_id');
        });

        Schema::table('custom_orders', function (Blueprint $table) {
            // Nullable so deleting a category or attribute value never blocks on old requests
            $table->unsignedBigInteger('type_of_jewellery_id')->nullable()->change();
        });

        $this->remapExistingRequests();

        Schema::table('custom_orders', function (Blueprint $table) {
            $table->dropColumn('occasion_id');

            $table->foreign('type_of_jewellery_id')->references('id')->on('categories')->nullOnDelete();
            $table->foreign('metal_type_id')->references('id')->on('attribute_values')->nullOnDelete();
            $table->foreign('metal_sub_type_id')->references('id')->on('attribute_values')->nullOnDelete();
            $table->foreign('primary_gemstone_id')->references('id')->on('attribute_values')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('custom_orders', function (Blueprint $table) {
            $table->dropForeign(['type_of_jewellery_id']);
            $table->dropForeign(['metal_type_id']);
            $table->dropForeign(['metal_sub_type_id']);
            $table->dropForeign(['primary_gemstone_id']);
        });

        // Ids now point at other tables; they cannot be mapped back reliably
        DB::table('custom_orders')->update([
            'type_of_jewellery_id' => null,
            'metal_type_id' => null,
            'metal_sub_type_id' => null,
            'primary_gemstone_id' => null,
        ]);

        Schema::table('custom_orders', function (Blueprint $table) {
            $table->foreignId('occasion_id')->nullable()->after('primary_gemstone_id');
            $table->dropColumn('occasion_name');

            $table->foreign('type_of_jewellery_id')->references('id')->on('custom_order_options')->restrictOnDelete();
            $table->foreign('metal_type_id')->references('id')->on('custom_order_options')->restrictOnDelete();
            $table->foreign('metal_sub_type_id')->references('id')->on('custom_order_options')->restrictOnDelete();
            $table->foreign('primary_gemstone_id')->references('id')->on('custom_order_options')->restrictOnDelete();
            $table->foreign('occasion_id')->references('id')->on('custom_order_options')->restrictOnDelete();
        });

        Schema::dropIfExists('attribute_value_variants');
    }

    private function remapExistingRequests(): void
    {
        if (!Schema::hasTable('custom_order_options')) {
            return;
        }

        $optionNames = DB::table('custom_order_options')->pluck('name', 'id');
        $name = fn ($id) => $id ? mb_strtolower(trim((string) ($optionNames[$id] ?? ''))) : '';

        $categories = DB::table('categories')->pluck('id', 'name')
            ->mapWithKeys(fn ($id, $n) => [mb_strtolower(trim($n)) => $id]);
        $valuesOf = fn (string $slug) => DB::table('attribute_values')
            ->join('attributes', 'attributes.id', '=', 'attribute_values.attribute_id')
            ->where('attributes.slug', $slug)
            ->pluck('attribute_values.id', 'attribute_values.value')
            ->mapWithKeys(fn ($id, $v) => [mb_strtolower(trim($v)) => $id]);
        $metals = $valuesOf('metal-type');
        $gemstones = $valuesOf('gemstone');

        DB::table('custom_orders')->orderBy('id')->each(function ($order) use ($name, $categories, $metals, $gemstones) {
            DB::table('custom_orders')->where('id', $order->id)->update([
                'type_of_jewellery_id' => $categories[$name($order->type_of_jewellery_id)] ?? null,
                'metal_type_id' => $metals[$name($order->metal_type_id)] ?? null,
                // No metal -> variant links exist yet, so variants cannot be matched
                'metal_sub_type_id' => null,
                'primary_gemstone_id' => $gemstones[$name($order->primary_gemstone_id)] ?? null,
                'occasion_name' => $name($order->occasion_id) !== ''
                    ? $optionNames[$order->occasion_id]
                    : null,
            ]);
        });
    }
};
