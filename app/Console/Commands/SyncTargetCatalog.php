<?php

namespace App\Console\Commands;

use App\Models\TpProduct;
use App\Models\Tenant;
use App\Services\TargetPlusService;
use App\Services\SyncLogService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncTargetCatalog extends Command
{
    protected $signature = 'tp:sync-catalog {tenant_id? : Limit to a specific tenant}';
    protected $description = 'Sync Target Plus catalog information to the internal product mapping table.';

    public function handle(SyncLogService $syncLogs): int
    {
        $hasFailures = false;
        $tenantId = $this->argument('tenant_id');
        $tenants = Tenant::syncable($tenantId === null ? null : (int) $tenantId);

        foreach ($tenants as $tenant) {
            $result = $this->syncTenantCatalog($tenant);
            $syncLogs->record($tenant, 'tp_to_lw', 'catalog_pull', $result['success'], "Products synced: {$result['synced']}. {$result['message']}");
            $hasFailures = $hasFailures || !$result['success'];
        }

        return $hasFailures ? Command::FAILURE : Command::SUCCESS;
    }

    private function syncTenantCatalog(Tenant $tenant): array
    {
        $tp = new TargetPlusService($tenant);
        $page = 1;
        $synced = 0;

        do {
            $items = $tp->getProducts(['page' => $page, 'per_page' => 100]);

            if ($items === null) {
                Log::warning("SyncTargetCatalog failed for tenant {$tenant->id}");
                return ['success' => false, 'synced' => $synced, 'message' => "Target Plus failed on page {$page}: {$tp->lastFailureMessage()}"];
            }

            foreach ($items as $item) {
                $tcin = $item['tcin'] ?? null;

                if (!$tcin) {
                    continue;
                }

                TpProduct::updateOrCreate(
                    ['tenant_id' => $tenant->id, 'tcin' => $tcin],
                    [
                        'external_id' => $item['external_id'] ?? null,
                        'sku' => $item['sku'] ?? $item['external_id'] ?? null,
                        'title' => $item['title'] ?? null,
                        'status' => $item['status'] ?? null,
                        'available_quantity' => max(0, (int) ($item['available_quantity'] ?? 0)),
                        'price' => max(0, (float) ($item['price'] ?? 0)),
                        'listing_active' => ($item['status'] ?? '') !== 'INACTIVE',
                        'raw_payload' => $item,
                    ]
                );
                $synced++;
            }

            $page++;
        } while (count($items) === 100);

        return ['success' => true, 'synced' => $synced, 'message' => 'Catalog pull completed.'];
    }
}
