<?php

namespace App\Console\Commands;

use App\Models\TpProduct;
use App\Models\Tenant;
use App\Services\TargetPlusService;
use App\Services\SyncLogService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncTargetInventory extends Command
{
    protected $signature = 'tp:sync-inventory {tenant_id? : Limit to a specific tenant}';
    protected $description = 'Sync Target Plus stock availability into the local product inventory table.';

    public function handle(SyncLogService $syncLogs): int
    {
        $hasFailures = false;
        $tenantId = $this->argument('tenant_id');
        $tenants = Tenant::syncable($tenantId === null ? null : (int) $tenantId);

        foreach ($tenants as $tenant) {
            $result = $this->syncTenantInventory($tenant);
            $success = $result['failed'] === 0;
            $details = $result['failureMessages'] ? ' ' . implode(' | ', $result['failureMessages']) : '';
            $syncLogs->record($tenant, 'tp_to_lw', 'inventory_pull', $success, "Inventory synced: {$result['synced']}; failed: {$result['failed']}.{$details}");
            $hasFailures = $hasFailures || !$success;
        }

        return $hasFailures ? Command::FAILURE : Command::SUCCESS;
    }

    private function syncTenantInventory(Tenant $tenant): array
    {
        $tp = new TargetPlusService($tenant);
        $products = TpProduct::where('tenant_id', $tenant->id)->get();
        $synced = 0;
        $failed = 0;
        $failureMessages = [];

        foreach ($products as $product) {
            $inventory = $tp->getProductInventory($product->tcin);

            if ($inventory === null) {
                $failed++;
                $failureMessages[] = "{$product->tcin}: {$tp->lastFailureMessage()}";
                continue;
            }

            $product->update([
                'available_quantity' => (int) ($inventory['available_quantity'] ?? 0),
                'inventory_synced_at' => now(),
                'status' => $inventory['status'] ?? $product->status,
            ]);

            Log::info("Inventory synced: tenant={$tenant->id} tcin={$product->tcin} qty={$product->available_quantity}");
            $synced++;
        }

        return compact('synced', 'failed', 'failureMessages');
    }
}
