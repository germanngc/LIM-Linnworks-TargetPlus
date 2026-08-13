<?php

namespace App\Console\Commands;

use App\Models\TargetProduct;
use App\Models\Tenant;
use App\Services\TargetPlusService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncTargetCatalog extends Command
{
    protected $signature = 'tp:sync-catalog {tenant_id? : Limit to a specific tenant}';
    protected $description = 'Sync Target Plus catalog information to the internal product mapping table.';

    public function handle(): int
    {
        $tenantId = $this->argument('tenant_id');
        $tenants = $tenantId
            ? Tenant::where('id', $tenantId)->where('active', true)->get()
            : Tenant::where('active', true)->get();

        foreach ($tenants as $tenant) {
            $this->syncTenantCatalog($tenant);
        }

        return Command::SUCCESS;
    }

    private function syncTenantCatalog(Tenant $tenant): void
    {
        $tp = new TargetPlusService($tenant);
        $page = 1;

        do {
            $items = $tp->getProducts(['page' => $page, 'per_page' => 100]);

            if ($items === null) {
                Log::warning("SyncTargetCatalog failed for tenant {$tenant->id}");
                break;
            }

            foreach ($items as $item) {
                $tcin = $item['tcin'] ?? null;

                if (!$tcin) {
                    continue;
                }

                TargetProduct::updateOrCreate(
                    ['tenant_id' => $tenant->id, 'tcin' => $tcin],
                    [
                        'external_id' => $item['external_id'] ?? null,
                        'sku' => $item['sku'] ?? $item['external_id'] ?? null,
                        'title' => $item['title'] ?? null,
                        'status' => $item['status'] ?? null,
                        'listing_active' => ($item['status'] ?? '') !== 'INACTIVE',
                        'raw_payload' => $item,
                    ]
                );
            }

            $page++;
        } while (count($items) === 100);
    }
}

