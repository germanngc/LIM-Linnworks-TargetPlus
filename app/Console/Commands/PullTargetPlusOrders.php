<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Models\TpOrder;
use App\Services\AlertService;
use App\Services\TargetPlusService;
use App\Services\SyncLogService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class PullTargetPlusOrders extends Command
{
    protected $signature   = 'tp:pull-orders {tenant_id? : Limit to a specific tenant ID}';
    protected $description = 'Pull new and updated orders from Target Plus into the local DB.';

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
        $tp = new TargetPlusService($tenant);

        // Pass 1: new orders placed since our last sync
        $since    = $tenant->last_sync_at ?? Carbon::now()->subDays(30);
        $now      = Carbon::now();
        $interval = $since->toIso8601String() . '/' . $now->toIso8601String();

        $newOrdersOk = $this->fetchAndUpsert($tp, $tenant, ['order_date' => $interval, 'per_page' => 100]);

        // Pass 2: refresh status of all currently-active orders in TP
        $activeOrdersOk = $this->fetchAndUpsert($tp, $tenant, ['order_status' => TpOrder::ACTIVE_STATUSES, 'per_page' => 100]);

        $success = $newOrdersOk && $activeOrdersOk;
        if ($success) {
            $tenant->update(['last_sync_at' => $now]);
        }
        $this->syncLogs->record($tenant, 'tp_to_lw', 'order_pull', $success, $success ? 'Order pull completed.' : 'One or more Target Plus order queries failed.');
    }

    /**
     * Page through a TP orders query and upsert each into the DB.
     */
    private function fetchAndUpsert(TargetPlusService $tp, Tenant $tenant, array $params): bool
    {
        $page = 1;

        do {
            $params['page'] = $page;
            $orders = $tp->getOrders($params);

            if ($orders === null) {
                Log::error("PullTargetPlusOrders: API error on page {$page} for tenant {$tenant->id}");
                $this->alerts->notifyGeneral(
                    "[TP Sync] Failed to fetch orders page {$page} for tenant {$tenant->id} ({$tenant->linnworks_email})"
                );
                return false;
            }

            foreach ($orders as $raw) {
                if (!is_array($raw)) {
                    Log::warning('PullTargetPlusOrders: skipping malformed order payload item', [
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

    private function upsertOrder(TargetPlusService $tp, Tenant $tenant, array $raw): void
    {
        $orderId = $raw['id'] ?? null;
        if (!$orderId) {
            return;
        }

        $checksum = md5(json_encode($raw));

        $existing = TpOrder::where('tenant_id', $tenant->id)
            ->where('target_order_id', $orderId)
            ->first();

        if ($existing && $existing->target_payload_checksum === $checksum) {
            return; // Nothing changed — skip
        }

        // Fetch shipping address only for new orders to conserve API calls
        $address = $existing?->target_address;
        if (!$existing) {
            $address = $tp->getOrderAddress($orderId);
            if ($address === null) {
                Log::warning("PullTargetPlusOrders: could not fetch address for order {$orderId}");
            }
        }

        TpOrder::updateOrCreate(
            ['tenant_id' => $tenant->id, 'target_order_id' => $orderId],
            [
                'target_order_number'     => $raw['order_number'] ?? '',
                'target_status'           => $raw['status'] ?? '',
                'target_order_date'       => isset($raw['order_date']) ? Carbon::parse($raw['order_date']) : null,
                'target_updated_at'       => isset($raw['last_modified']) ? Carbon::parse($raw['last_modified']) : null,
                'target_payload'          => $raw,
                'target_payload_checksum' => $checksum,
                'target_address'          => $address,
                'sync_status'             => TpOrder::SYNC_PENDING,
                'synced_to_linnworks_at'  => null,
            ]
        );

        $action = $existing ? 'updated' : 'inserted';
        Log::info("PullTargetPlusOrders: {$action} order {$orderId} (tenant {$tenant->id})");
    }
}
