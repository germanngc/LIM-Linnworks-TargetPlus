<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendor_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            // Vendor identifiers
            $table->string('vendor_order_id')->index();   // order.id (e.g. "1028521085032-1635740801")
            $table->string('vendor_order_number')->index(); // order.order_number (human-readable)
            $table->string('vendor_status', 40);
            $table->timestamp('vendor_order_date')->nullable();
            $table->timestamp('vendor_updated_at')->nullable();
            $table->json('vendor_payload');               // full raw order object
            $table->json('vendor_address')->nullable();   // from /order_addresses endpoint
            $table->string('vendor_payload_checksum', 32);
            // Fulfilment fields populated after Linnworks despatch
            $table->string('tracking_number')->nullable();
            $table->string('carrier')->nullable();
            $table->timestamp('shipped_date')->nullable();
            // Reverse-sync state
            $table->string('lw_payment_status', 20)->nullable(); // last PaymentStatus we served to LW
            $table->timestamp('pushed_to_vendor_at')->nullable(); // shipment confirmed to TP
            $table->boolean('cancel_pushed_to_vendor')->default(false);
            // Error / retry tracking
            $table->tinyInteger('sync_attempt')->default(0);
            $table->text('last_sync_error')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'vendor_order_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_orders');
    }
};
