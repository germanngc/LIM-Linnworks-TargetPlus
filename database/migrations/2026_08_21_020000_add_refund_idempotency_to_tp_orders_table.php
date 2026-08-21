<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void { Schema::table('tp_orders', function (Blueprint $table) { $table->string('refund_reference')->nullable()->index(); $table->timestamp('refunded_to_target_at')->nullable(); }); }
    public function down(): void { Schema::table('tp_orders', fn (Blueprint $table) => $table->dropColumn(['refund_reference', 'refunded_to_target_at'])); }
};
