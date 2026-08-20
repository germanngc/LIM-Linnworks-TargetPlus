<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('target_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('tcin')->index();
            $table->string('external_id')->nullable()->index();
            $table->string('sku')->nullable()->index();
            $table->string('title')->nullable();
            $table->string('status')->nullable();
            $table->integer('available_quantity')->default(0);
            $table->boolean('listing_active')->default(false);
            $table->json('raw_payload')->nullable();
            $table->timestamp('inventory_synced_at')->nullable();
            $table->string('linnworks_stock_item_id')->nullable()->index();
            $table->string('linnworks_channel_mapping_id')->nullable()->index();
            $table->timestamp('linnworks_synced_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'tcin']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('target_products');
    }
};
