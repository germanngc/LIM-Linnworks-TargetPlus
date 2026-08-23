<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Models\VendorOrder;
use App\Services\AlertService;
use App\Services\VendorApiService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class PushCancellationsToVendor extends Command
{
    protected $signature   = 'vendor:push-cancellations {tenant_id? : Limit to a specific tenant ID}';
    protected $description = 'Push Linnworks-initiated cancellations back to Vendor.';

    public function __construct(private AlertService $alerts)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $tenantId = $this->argument('tenant_id');
        $tenants = Tenant::syncable($tenantId === null ? null : (int) $tenantId);

        foreach ($tenants as $tenant) {
            $this->processTenant($tenant);
        }

        return Command::SUCCESS;
    }

    private function processTenant(Tenant $tenant): void
    {
        // Orders Linnworks flagged CANCELLED but we haven't told Vendor yet
        $orders = VendorOrder::where('tenant_id', $tenant->id)
            ->where('lw_payment_status', 'CANCELLED')
            ->where('cancel_pushed_to_vendor', false)
            ->whereNotIn('vendor_status', VendorOrder::TERMINAL_STATUSES)
            ->get();

        if ($orders->isEmpty()) {
            return;
        }

        $tp = new VendorApiService($tenant);

        foreach ($orders as $order) {
            $success = $tp->updateOrderStatus($order->vendor_order_id, 'CANCELED');

            if ($success) {
                $order->update([
                    'cancel_pushed_to_vendor' => true,
                    'vendor_status'           => 'CANCELED',
                    'sync_attempt'            => 0,
                    'last_sync_error'         => null,
                ]);

                Log::info("PushCancellations: order {$order->vendor_order_id} cancelled on Vendor");
            } else {
                $attempt = $order->sync_attempt + 1;
                $errMsg  = "Failed to cancel order {$order->vendor_order_id} on Vendor";

                $order->update(['sync_attempt' => $attempt, 'last_sync_error' => $errMsg]);
                $this->alerts->notifyOrderSyncFailure($tenant->id, $order->vendor_order_id, $errMsg, $attempt);
                Log::warning($errMsg);
            }
        }
    }
}
