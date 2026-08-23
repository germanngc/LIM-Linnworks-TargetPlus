<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Models\VendorOrder;
use App\Services\LinnworksOrderPushService;
use Illuminate\Console\Command;

class PushOrdersToLinnworks extends Command
{
    protected $signature = 'tp:push-orders {tenant_id? : Limit to a specific tenant}';
    protected $description = 'Push pending Vendor orders from local DB into Linnworks.';

    public function handle(LinnworksOrderPushService $pushService): int
    {
        $hasFailures = false;
        $hasBlocked = false;
        $pushedCount = 0;

        $tenantId = $this->argument('tenant_id');
        $tenants = Tenant::syncable($tenantId === null ? null : (int) $tenantId);

        foreach ($tenants as $tenant) {
            $orders = VendorOrder::where('tenant_id', $tenant->id)
                ->whereIn('sync_status', [VendorOrder::SYNC_PENDING, VendorOrder::SYNC_FAILED, VendorOrder::SYNC_BLOCKED])
                ->orderBy('vendor_order_date')
                ->limit(100)
                ->get();

            foreach ($orders as $order) {
                $result = $pushService->pushOrder($tenant, $order);

                if ($result === VendorOrder::SYNC_BLOCKED) {
                    $hasBlocked = true;
                    continue;
                }

                if ($result !== VendorOrder::SYNC_SYNCED) {
                    $hasFailures = true;
                    continue;
                }

                $order->update([
                    'sync_status' => VendorOrder::SYNC_SYNCED,
                    'synced_to_linnworks_at' => now(),
                    'sync_attempt' => 0,
                    'last_sync_error' => null,
                ]);

                $pushedCount++;
            }
        }

        $this->info("Orders pushed to Linnworks: {$pushedCount}");

        if ($hasBlocked && !$hasFailures) {
            $this->warn('Direct order push is unsupported for current Linnworks endpoint/path. Orders remain available for pull integration.');
            return Command::SUCCESS;
        }

        if ($hasFailures) {
            $this->error('Some orders failed to push to Linnworks.');
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
