<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->string('linnworks_user_id')->unique()->index();
            $table->string('linnworks_email');
            $table->text('linnworks_application_token');
            $table->text('linnworks_token');
            $table->string('linnworks_server');
            // UUID sent by Linnworks on every API call to identify this tenant
            $table->uuid('authorization_token')->unique()->index();
            $table->text('target_api_key');     // stored encrypted
            $table->text('target_seller_id');   // stored encrypted
            $table->text('target_seller_token'); // stored encrypted
            $table->enum('target_environment', ['prod', 'stage'])->default('prod');
            $table->boolean('active')->default(true);
            $table->timestamp('last_sync_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
