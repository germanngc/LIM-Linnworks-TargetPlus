<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendor_listing_feeds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->uuid('channel_feed_id');
            $table->string('operation', 20);
            $table->json('product_feeds');
            $table->timestamps();
            $table->unique(['tenant_id', 'channel_feed_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_listing_feeds');
    }
};
