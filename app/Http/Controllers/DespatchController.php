<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Models\TpOrder;
use App\Services\AlertService;
use App\Services\TargetPlusService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DespatchController extends Controller
{
    public function __construct(private AlertService $alerts) {}

    /**
     * Linnworks calls this when it marks an order as dispatched.
     *
     * Expected body:
     * {
     *   AuthorizationToken: string,
     *   Orders: [
     *     {
     *       ReferenceNumber: string,     // = target_order_id
     *       TrackingNumber: string,
     *       PostalServiceName: string,
     *       // Linnworks may also include Package/line info
     *     }
     *   ]
     * }
     */
    public function store(Request $request): JsonResponse
    {
        /** @var Tenant $tenant */
        $tenant  = $request->attributes->get('tenant');
        $orders  = $request->input('Orders', []);
        $errors  = [];

        if (empty($orders)) {
            return response()->json(['Error' => null]);
        }

        $tp = new TargetPlusService($tenant);

        foreach ($orders as $lwOrder) {
            $referenceNumber = $lwOrder['ReferenceNumber'] ?? null;
            $trackingNumber  = $lwOrder['TrackingNumber'] ?? null;
            $postalService   = $lwOrder['PostalServiceName'] ?? '';

            if (!$referenceNumber || !$trackingNumber) {
                $errors[] = "Skipped order — missing ReferenceNumber or TrackingNumber.";
                continue;
            }

            $dbOrder = TpOrder::where('tenant_id', $tenant->id)
                ->where('target_order_id', $referenceNumber)
                ->first();

            if (!$dbOrder) {
                Log::warning("Despatch: order {$referenceNumber} not found in DB for tenant {$tenant->id}");
                $errors[] = "Order {$referenceNumber} not found.";
                continue;
            }

            // Build fulfillment items from all order lines that are not yet shipped
            $items = $this->buildFulfillmentItems($dbOrder, $trackingNumber, $postalService);

            $success = $tp->confirmShipment($referenceNumber, $items);

            if ($success) {
                $dbOrder->update([
                    'tracking_number'    => $trackingNumber,
                    'carrier'            => $postalService,
                    'shipped_date'       => now(),
                    'pushed_to_target_at' => now(),
                    'sync_attempt'       => 0,
                    'last_sync_error'    => null,
                ]);

                Log::info("Despatch: confirmed shipment for order {$referenceNumber}");
            } else {
                $attempt = $dbOrder->sync_attempt + 1;
                $errMsg  = "Shipment push to Target Plus failed for order {$referenceNumber}";

                $dbOrder->update([
                    'sync_attempt'    => $attempt,
                    'last_sync_error' => $errMsg,
                ]);

                $this->alerts->notifyOrderSyncFailure($tenant->id, $referenceNumber, $errMsg, $attempt);
                $errors[] = $errMsg;
            }
        }

        return response()->json([
            'Error' => empty($errors) ? null : implode('; ', $errors),
        ]);
    }

    /**
     * Build the bulk_fulfillments_update items array from all unshipped order lines.
     * Strategy A: include all lines not yet shipped with the same tracking number.
     */
    private function buildFulfillmentItems(TpOrder $order, string $trackingNumber, string $shippingMethod): array
    {
        $lines = $order->target_payload['order_lines'] ?? [];
        $shippedDate = Carbon::now()->toIso8601String();
        $items = [];

        foreach ($lines as $line) {
            // Skip lines already in a terminal status
            $lineStatuses = array_column($line['order_line_statuses'] ?? [], 'status');
            if (array_intersect($lineStatuses, ['SHIPPED', 'CANCELED', 'REFUNDED'])) {
                continue;
            }

            $items[] = [
                'id'                 => $line['order_line_number'],
                'order_line_number'  => $line['order_line_number'],
                'quantity'           => (int) ($line['quantity'] ?? 1),
                'tracking_number'    => $trackingNumber,
                'shipping_method'    => $shippingMethod,
                'shipped_date'       => $shippedDate,
                'level_of_service'   => $order->target_payload['level_of_service'] ?? '',
            ];
        }

        return $items;
    }
}
