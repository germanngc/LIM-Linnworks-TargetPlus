<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sync_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('direction', 20); // 'tp_to_lw' | 'lw_to_tp'
            $table->string('event', 40);     // 'pull' | 'despatch' | 'cancel'
            $table->string('target_order_id')->nullable();
            $table->boolean('success');
            $table->text('message')->nullable();
            $table->timestamp('logged_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_logs');
    }
};
