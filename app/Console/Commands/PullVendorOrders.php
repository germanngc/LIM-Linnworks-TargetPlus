<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Models\VendorOrder;
use App\Services\AlertService;
use App\Services\VendorApiService;
use App\Services\SyncLogService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class PullVendorOrders extends Command
{
    protected $signature   = 'vendor:pull-orders {tenant_id? : Limit to a specific tenant ID}';
    protected $description = 'Pull new and updated orders from Vendor into the local DB.';

    public function __construct(private AlertService $alerts, private SyncLogService $syncLogs)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $tenantId = $this->argument('tenant_id');
        $tenants = Tenant::syncable($tenantId === null ? null : (int) $tenantId);

        if ($tenants->isEmpty()) {
            $this->info('No active tenants found.');
            return Command::SUCCESS;
        }

        foreach ($tenants as $tenant) {
            $this->processTenant($tenant);
        }

        return Command::SUCCESS;
    }

    private function processTenant(Tenant $tenant): void
    {
        $this->info("Processing tenant {$tenant->id} ({$tenant->linnworks_email})");
        $tp = new VendorApiService($tenant);

        // Pass 1: new orders placed since our last sync
        $since    = $tenant->last_sync_at ?? Carbon::now()->subDays(30);
        $now      = Carbon::now();
        $interval = $since->toIso8601String() . '/' . $now->toIso8601String();

        $newOrdersOk = $this->fetchAndUpsert($tp, $tenant, ['order_date' => $interval, 'per_page' => 100]);

        // Pass 2: refresh status of all currently-active orders in TP
        $activeOrdersOk = $this->fetchAndUpsert($tp, $tenant, ['order_status' => VendorOrder::ACTIVE_STATUSES, 'per_page' => 100]);

        $success = $newOrdersOk && $activeOrdersOk;
        if ($success) {
            $tenant->update(['last_sync_at' => $now]);
        }
        $this->syncLogs->record($tenant, 'vendor_to_lw', 'order_pull', $success, $success ? 'Order pull completed.' : 'One or more Vendor order queries failed.');
    }

    /**
     * Page through a TP orders query and upsert each into the DB.
     */
    private function fetchAndUpsert(VendorApiService $tp, Tenant $tenant, array $params): bool
    {
        $page = 1;

        do {
            $params['page'] = $page;
            $orders = $tp->getOrders($params);

            if ($orders === null) {
                Log::error("PullVendorOrders: API error on page {$page} for tenant {$tenant->id}");
                $this->alerts->notifyGeneral(
                    "[TP Sync] Failed to fetch orders page {$page} for tenant {$tenant->id} ({$tenant->linnworks_email})"
                );
                return false;
            }

            foreach ($orders as $raw) {
                if (!is_array($raw)) {
                    Log::warning('PullVendorOrders: skipping malformed order payload item', [
                        'tenant' => $tenant->id,
                        'type' => gettype($raw),
                        'value' => $raw,
                    ]);
                    continue;
                }

                $this->upsertOrder($tp, $tenant, $raw);
            }

            $page++;
        } while (count($orders) === ($params['per_page'] ?? 10));

        return true;
    }

    private function upsertOrder(VendorApiService $tp, Tenant $tenant, array $raw): void
    {
        $orderId = $raw['id'] ?? null;
        if (!$orderId) {
            return;
        }

        $checksum = md5(json_encode($raw));

        $existing = VendorOrder::where('tenant_id', $tenant->id)
            ->where('vendor_order_id', $orderId)
            ->first();

        if ($existing && $existing->vendor_payload_checksum === $checksum) {
            return; // Nothing changed — skip
        }

        // Fetch shipping address only for new orders to conserve API calls
        $address = $existing?->vendor_address;
        if (!$existing) {
            $address = $tp->getOrderAddress($orderId);
            if ($address === null) {
                Log::warning("PullVendorOrders: could not fetch address for order {$orderId}");
            }
        }

        VendorOrder::updateOrCreate(
            ['tenant_id' => $tenant->id, 'vendor_order_id' => $orderId],
            [
                'vendor_order_number'     => $raw['order_number'] ?? '',
                'vendor_status'           => $raw['status'] ?? '',
                'vendor_order_date'       => isset($raw['order_date']) ? Carbon::parse($raw['order_date']) : null,
                'vendor_updated_at'       => isset($raw['last_modified']) ? Carbon::parse($raw['last_modified']) : null,
                'vendor_payload'          => $raw,
                'vendor_payload_checksum' => $checksum,
                'vendor_address'          => $address,
                'sync_status'             => VendorOrder::SYNC_PENDING,
                'synced_to_linnworks_at'  => null,
            ]
        );

        $action = $existing ? 'updated' : 'inserted';
        Log::info("PullVendorOrders: {$action} order {$orderId} (tenant {$tenant->id})");
    }
}
