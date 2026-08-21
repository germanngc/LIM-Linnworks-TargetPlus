<?php

namespace App\Console\Commands;

use App\Models\TpProduct;
use App\Models\Tenant;
use App\Services\LinnworksInventoryService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncTargetListingsToLinnworks extends Command
{
    protected $signature = 'tp:sync-listings {tenant_id? : Limit to a specific tenant}';
    protected $description = 'Sync Target Plus catalog items into Linnworks stock items and channel mappings.';

    public function handle(LinnworksInventoryService $linnworks): int
    {
        $tenantId = $this->argument('tenant_id');
        $tenants = Tenant::syncable($tenantId === null ? null : (int) $tenantId);

        foreach ($tenants as $tenant) {
            $this->syncTenantListings($linnworks, $tenant);
        }

        return Command::SUCCESS;
    }

    private function syncTenantListings(LinnworksInventoryService $linnworks, Tenant $tenant): void
    {
        $products = TpProduct::where('tenant_id', $tenant->id)->get();

        foreach ($products as $product) {
            $result = $linnworks->syncTargetProductListing($tenant, $product);

            if (($result['status'] ?? null) === 'failed') {
                Log::warning('SyncTargetListingsToLinnworks failed', [
                    'tenant' => $tenant->id,
                    'tcin' => $product->tcin,
                    'reason' => $result['reason'] ?? 'unknown',
                ]);

                continue;
            }

            $this->line(sprintf(
                'tenant=%d tcin=%s sku=%s stock_item_id=%s mapping_id=%s status=%s',
                $tenant->id,
                $product->tcin,
                $result['sku'] ?? '-',
                $result['stock_item_id'] ?? '-',
                $result['channel_mapping_id'] ?? '-',
                $result['status'] ?? 'unknown'
            ));
        }
    }
}
