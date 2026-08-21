<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('target_products') && !Schema::hasTable('tp_products')) {
            Schema::rename('target_products', 'tp_products');
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('tp_products') && !Schema::hasTable('target_products')) {
            Schema::rename('tp_products', 'target_products');
        }
    }
};
