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
        // 1. shipping_zones
        Schema::create('shipping_zones', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('region_id')->nullable();
            $table->string('name');
            $table->json('country_codes');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('region_id')->references('id')->on('regions')->onDelete('set null');
        });

        // 2. shipping_methods
        Schema::create('shipping_methods', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('shipping_zone_id');
            $table->string('name');
            $table->enum('carrier_type', ['manual', 'shiprocket', 'direct_carrier']);
            $table->string('carrier_name')->nullable();
            $table->decimal('base_rate', 10, 2);
            $table->decimal('insurance_percentage', 5, 2)->nullable();
            $table->boolean('requires_signature')->default(true);
            $table->integer('min_transit_days');
            $table->integer('max_transit_days');
            $table->integer('processing_days');
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('shipping_zone_id')->references('id')->on('shipping_zones')->onDelete('cascade');
            $table->index(['shipping_zone_id', 'is_active']);
        });

        // 3. shipping_thresholds
        Schema::create('shipping_thresholds', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('shipping_zone_id');
            $table->decimal('min_order_value', 10, 2);
            $table->string('currency');
            $table->timestamps();

            $table->foreign('shipping_zone_id')->references('id')->on('shipping_zones')->onDelete('cascade');
        });

        // 4. delivery_estimates
        Schema::create('delivery_estimates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('shipping_zone_id');
            $table->string('pincode_prefix')->nullable();
            $table->integer('min_days');
            $table->integer('max_days');
            $table->time('cutoff_time');
            $table->timestamps();

            $table->foreign('shipping_zone_id')->references('id')->on('shipping_zones')->onDelete('cascade');
            $table->index(['shipping_zone_id', 'pincode_prefix']);
        });

        // 5. shipments
        Schema::create('shipments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('shipping_method_id')->nullable();
            $table->string('tracking_number')->nullable();
            $table->string('carrier_name')->nullable();
            $table->enum('status', [
                'pending', 'processing', 'dispatched', 'in_transit',
                'out_for_delivery', 'delivered', 'failed_attempt', 'returned', 'cancelled'
            ])->default('pending');
            $table->decimal('shipping_cost', 10, 2);
            $table->decimal('insurance_cost', 10, 2)->default(0.00);
            $table->decimal('declared_value', 10, 2);
            $table->date('estimated_delivery_min');
            $table->date('estimated_delivery_max');
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->string('signature_image_url')->nullable();
            $table->string('customs_hs_code')->nullable();
            $table->text('customs_declaration_note')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('order_id')->references('id')->on('orders')->onDelete('cascade');
            $table->foreign('shipping_method_id')->references('id')->on('shipping_methods')->onDelete('set null');
            
            $table->index('order_id');
            $table->index('status');
            $table->index('tracking_number');
        });

        // 6. shipment_tracking_events
        Schema::create('shipment_tracking_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('shipment_id');
            $table->enum('status', [
                'pending', 'processing', 'dispatched', 'in_transit',
                'out_for_delivery', 'delivered', 'failed_attempt', 'returned', 'cancelled'
            ]);
            $table->string('location')->nullable();
            $table->text('description')->nullable();
            $table->timestamp('event_time');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->foreign('shipment_id')->references('id')->on('shipments')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            
            $table->index(['shipment_id', 'event_time']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shipment_tracking_events');
        Schema::dropIfExists('shipments');
        Schema::dropIfExists('delivery_estimates');
        Schema::dropIfExists('shipping_thresholds');
        Schema::dropIfExists('shipping_methods');
        Schema::dropIfExists('shipping_zones');
    }
};
