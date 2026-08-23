<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::table('vendor_listing_feeds', function (Blueprint $table) { $table->string('request_hash', 64)->nullable()->after('operation'); $table->unique(['tenant_id', 'operation', 'request_hash'], 'vendor_listing_feed_idempotency'); });
        Schema::table('vendor_orders', fn (Blueprint $table) => $table->string('despatch_checksum', 64)->nullable()->index());
    }
    public function down(): void {
        Schema::table('vendor_listing_feeds', function (Blueprint $table) { $table->dropUnique('vendor_listing_feed_idempotency'); $table->dropColumn('request_hash'); });
        Schema::table('vendor_orders', fn (Blueprint $table) => $table->dropColumn('despatch_checksum'));
    }
};
