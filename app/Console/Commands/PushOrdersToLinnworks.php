<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Models\TpOrder;
use App\Services\LinnworksOrderPushService;
use Illuminate\Console\Command;

class PushOrdersToLinnworks extends Command
{
    protected $signature = 'tp:push-orders {tenant_id? : Limit to a specific tenant}';
    protected $description = 'Push pending Target Plus orders from local DB into Linnworks.';

    public function handle(LinnworksOrderPushService $pushService): int
    {
        $hasFailures = false;
        $pushedCount = 0;

        $tenantId = $this->argument('tenant_id');
        $tenants = $tenantId
            ? Tenant::where('id', $tenantId)->where('active', true)->get()
            : Tenant::where('active', true)->get();

        foreach ($tenants as $tenant) {
            $orders = TpOrder::where('tenant_id', $tenant->id)
                ->whereIn('sync_status', [TpOrder::SYNC_PENDING, TpOrder::SYNC_FAILED])
                ->orderBy('target_order_date')
                ->limit(100)
                ->get();

            foreach ($orders as $order) {
                $ok = $pushService->pushOrder($tenant, $order);

                if (!$ok) {
                    $hasFailures = true;
                    continue;
                }

                $order->update([
                    'sync_status' => TpOrder::SYNC_SYNCED,
                    'synced_to_linnworks_at' => now(),
                    'sync_attempt' => 0,
                    'last_sync_error' => null,
                ]);

                $pushedCount++;
            }
        }

        $this->info("Orders pushed to Linnworks: {$pushedCount}");

        if ($hasFailures) {
            $this->error('Some orders failed to push to Linnworks.');
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
