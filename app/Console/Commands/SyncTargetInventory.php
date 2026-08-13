<?php

namespace App\Console\Commands;

use App\Models\TargetProduct;
use App\Models\Tenant;
use App\Services\TargetPlusService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncTargetInventory extends Command
{
    protected $signature = 'tp:sync-inventory {tenant_id? : Limit to a specific tenant}';
    protected $description = 'Sync Target Plus stock availability into the local product inventory table.';

    public function handle(): int
    {
        $tenantId = $this->argument('tenant_id');
        $tenants = $tenantId
            ? Tenant::where('id', $tenantId)->where('active', true)->get()
            : Tenant::where('active', true)->get();

        foreach ($tenants as $tenant) {
            $this->syncTenantInventory($tenant);
        }

        return Command::SUCCESS;
    }

    private function syncTenantInventory(Tenant $tenant): void
    {
        $tp = new TargetPlusService($tenant);
        $products = TargetProduct::where('tenant_id', $tenant->id)->get();

        foreach ($products as $product) {
            $inventory = $tp->getProductInventory($product->tcin);

            if ($inventory === null) {
                continue;
            }

            $product->update([
                'available_quantity' => (int) ($inventory['available_quantity'] ?? 0),
                'inventory_synced_at' => now(),
                'status' => $inventory['status'] ?? $product->status,
            ]);

            Log::info("Inventory synced: tenant={$tenant->id} tcin={$product->tcin} qty={$product->available_quantity}");
        }
    }
}

