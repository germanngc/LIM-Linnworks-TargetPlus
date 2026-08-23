<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendor_orders', function (Blueprint $table) {
            $table->string('sync_status', 20)->default('pending')->after('cancel_pushed_to_vendor')->index();
            $table->timestamp('synced_to_linnworks_at')->nullable()->after('sync_status');
        });
    }

    public function down(): void
    {
        Schema::table('vendor_orders', function (Blueprint $table) {
            $table->dropColumn(['sync_status', 'synced_to_linnworks_at']);
        });
    }
};
