<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\TpOrder;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class LinnworksOrderPushService
{
    public function __construct(private OrderMapperService $mapper) {}

    /**
     * Push a single TP order payload to Linnworks.
     */
    public function pushOrder(Tenant $tenant, TpOrder $order): bool
    {
        $path = (string) config('services.linnworks.order_push_path', '/api/Orders/CreateOrder');
        $url = rtrim($tenant->linnworks_server, '/') . $path;

        try {
            $response = $this->httpClient($tenant)->post($url, $this->mapper->toLinworks($order));

            if ($response->successful()) {
                return true;
            }

            $body = $response->json();
            $errorMessage = is_array($body)
                ? ($body['Message'] ?? $body['Error'] ?? $response->body())
                : $response->body();

            Log::warning('Linnworks order push failed', [
                'tenant' => $tenant->id,
                'order_id' => $order->target_order_id,
                'status' => $response->status(),
                'error' => $errorMessage,
            ]);

            $order->update([
                'sync_status' => TpOrder::SYNC_FAILED,
                'sync_attempt' => min(255, (int) $order->sync_attempt + 1),
                'last_sync_error' => mb_substr((string) $errorMessage, 0, 2000),
            ]);

            return false;
        } catch (\Throwable $e) {
            Log::error('Linnworks order push exception', [
                'tenant' => $tenant->id,
                'order_id' => $order->target_order_id,
                'error' => $e->getMessage(),
            ]);

            $order->update([
                'sync_status' => TpOrder::SYNC_FAILED,
                'sync_attempt' => min(255, (int) $order->sync_attempt + 1),
                'last_sync_error' => mb_substr($e->getMessage(), 0, 2000),
            ]);

            return false;
        }
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
