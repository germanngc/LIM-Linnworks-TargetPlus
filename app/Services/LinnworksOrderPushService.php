<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\VendorOrder;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class LinnworksOrderPushService
{
    public function __construct(private OrderMapperService $mapper) {}

    /**
     * Push a single TP order payload to Linnworks.
     */
    public function pushOrder(Tenant $tenant, VendorOrder $order): string
    {
        $paths = array_values(array_unique([
            (string) config('services.linnworks.order_push_path', '/api/Orders/CreateOrders'),
            '/api/Orders/CreateOrders',
        ]));

        $lastStatus = null;
        $lastError = null;
        $allUnsupported = true;
        $payload = [
            'orders' => [$this->mapper->toLinworks($order)],
        ];

        foreach ($paths as $path) {
            $url = rtrim($tenant->linnworks_server, '/') . $path;

            try {
                $response = $this->httpClient($tenant)->post($url, $payload);

                if ($response->successful()) {
                    return VendorOrder::SYNC_SYNCED;
                }

                $body = $response->json();
                $errorMessage = is_array($body)
                    ? ($body['Message'] ?? $body['Error'] ?? $response->body())
                    : $response->body();

                $isUnsupportedEndpoint = $response->status() === 404
                    && str_contains((string) $errorMessage, 'No HTTP resource was found');

                if (!$isUnsupportedEndpoint) {
                    $allUnsupported = false;
                }

                $lastStatus = $response->status();
                $lastError = (string) $errorMessage;

                if ($isUnsupportedEndpoint) {
                    continue;
                }

                Log::warning('Linnworks order push failed', [
                    'tenant' => $tenant->id,
                    'order_id' => $order->vendor_order_id,
                    'status' => $response->status(),
                    'error' => $errorMessage,
                    'path' => $path,
                ]);

                $order->update([
                    'sync_status' => VendorOrder::SYNC_FAILED,
                    'sync_attempt' => min(255, (int) $order->sync_attempt + 1),
                    'last_sync_error' => mb_substr((string) $errorMessage, 0, 2000),
                ]);

                return VendorOrder::SYNC_FAILED;
            } catch (\Throwable $e) {
                $allUnsupported = false;
                $lastStatus = 0;
                $lastError = $e->getMessage();
            }
        }

        $status = $allUnsupported ? VendorOrder::SYNC_BLOCKED : VendorOrder::SYNC_FAILED;
        $friendlyError = $allUnsupported
            ? 'Direct Linnworks order push endpoint is unavailable for this account/path. Use channel pull endpoint integration or set LINNWORKS_ORDER_PUSH_PATH to a valid resource.'
            : (string) ($lastError ?: 'Unknown Linnworks push error.');

        Log::warning('Linnworks order push failed', [
            'tenant' => $tenant->id,
            'order_id' => $order->vendor_order_id,
            'status' => $lastStatus,
            'error' => $friendlyError,
            'paths_tried' => $paths,
        ]);

        $order->update([
            'sync_status' => $status,
            'sync_attempt' => min(255, (int) $order->sync_attempt + 1),
            'last_sync_error' => mb_substr($friendlyError, 0, 2000),
        ]);

        return $status;
    }

    private function httpClient(Tenant $tenant): PendingRequest
    {
        return Http::withHeaders([
            'Authorization' => $tenant->linnworks_token,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ])
            ->connectTimeout(8)
            ->timeout(20)
            ->retry(1, 250);
    }
}
