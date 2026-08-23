<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class SyncOrdersToLinnworks extends Command
{
    protected $signature = 'tp:sync-orders {tenant_id? : Limit to a specific tenant}';
    protected $description = 'Pull Vendor orders and push pending ones to Linnworks.';

    public function handle(): int
    {
        $tenantId = $this->argument('tenant_id');

        $pullCode = Artisan::call('vendor:pull-orders', [
            'tenant_id' => $tenantId,
            '--no-interaction' => true,
        ]);

        if ($pullCode !== Command::SUCCESS) {
            $this->error('vendor:pull-orders failed.');
            return Command::FAILURE;
        }

        $pushCode = Artisan::call('tp:push-orders', [
            'tenant_id' => $tenantId,
            '--no-interaction' => true,
        ]);

        if ($pushCode !== Command::SUCCESS) {
            $this->error('tp:push-orders failed.');
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
