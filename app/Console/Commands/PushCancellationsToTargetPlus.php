<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Models\TpOrder;
use App\Services\AlertService;
use App\Services\TargetPlusService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class PushCancellationsToTargetPlus extends Command
{
    protected $signature   = 'tp:push-cancellations {tenant_id? : Limit to a specific tenant ID}';
    protected $description = 'Push Linnworks-initiated cancellations back to Target Plus.';

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
        // Orders Linnworks flagged CANCELLED but we haven't told Target Plus yet
        $orders = TpOrder::where('tenant_id', $tenant->id)
            ->where('lw_payment_status', 'CANCELLED')
            ->where('cancel_pushed_to_target', false)
            ->whereNotIn('target_status', TpOrder::TERMINAL_STATUSES)
            ->get();

        if ($orders->isEmpty()) {
            return;
        }

        $tp = new TargetPlusService($tenant);

        foreach ($orders as $order) {
            $success = $tp->updateOrderStatus($order->target_order_id, 'CANCELED');

            if ($success) {
                $order->update([
                    'cancel_pushed_to_target' => true,
                    'target_status'           => 'CANCELED',
                    'sync_attempt'            => 0,
                    'last_sync_error'         => null,
                ]);

                Log::info("PushCancellations: order {$order->target_order_id} cancelled on Target Plus");
            } else {
                $attempt = $order->sync_attempt + 1;
                $errMsg  = "Failed to cancel order {$order->target_order_id} on Target Plus";

                $order->update(['sync_attempt' => $attempt, 'last_sync_error' => $errMsg]);
                $this->alerts->notifyOrderSyncFailure($tenant->id, $order->target_order_id, $errMsg, $attempt);
                Log::warning($errMsg);
            }
        }
    }
}
