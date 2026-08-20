<?php

namespace App\Services;

use App\Models\Tenant;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TargetPlusService
{
    private Tenant $tenant;
    private string $baseUrl;

    public function __construct(Tenant $tenant)
    {
        $this->tenant = $tenant;
        $this->baseUrl = $tenant->targetBaseUrl();
    }

    /**
     * GET /sellers/{seller_id}/orders
     * Returns raw array of orders. Accepts any subset of the supported query params.
     */
    public function getOrders(array $params = []): ?array
    {
        $sellerId = $this->tenant->target_seller_id;

        $response = $this->get("/sellers/{$sellerId}/orders", $params);

        if ($response === null) {
            return null;
        }

        return $this->extractList($response);
    }

    /**
     * GET /sellers/{seller_id}/order_addresses/{order_id}
     */
    public function getOrderAddress(string $orderId): ?array
    {
        $sellerId = $this->tenant->target_seller_id;

        return $this->get("/sellers/{$sellerId}/order_addresses/{$orderId}");
    }

    /**
     * PUT /sellers/{seller_id}/orders/{order_id}/bulk_fulfillments_update
     * $items: array of fulfillment line objects matching the TP spec.
     */
    public function confirmShipment(string $orderId, array $items): bool
    {
        $sellerId = $this->tenant->target_seller_id;

        $response = $this->put(
            "/sellers/{$sellerId}/orders/{$orderId}/bulk_fulfillments_update",
            ['items' => $items]
        );

        return $response !== null;
    }

    /**
     * PUT /sellers/{seller_id}/order_statuses/{order_id}
     */
    public function updateOrderStatus(string $orderId, string $status): bool
    {
        $sellerId = $this->tenant->target_seller_id;

        $response = $this->put(
            "/sellers/{$sellerId}/order_statuses/{$orderId}",
            ['status' => $status]
        );

        return $response !== null;
    }

    /**
     * Fetch catalog items for product/listing sync.
     * This is an extension for marketplace listing capability, not just order sync.
     */
    public function getProducts(array $params = []): ?array
    {
        $sellerId = $this->tenant->target_seller_id;
        $response = $this->get("/sellers/{$sellerId}/products", $params);

        return $response ? $this->extractList($response) : null;
    }

    /**
     * Fetch stock info for a single product.
     */
    public function getProductInventory(string $tcin): ?array
    {
        $sellerId = $this->tenant->target_seller_id;
        $response = $this->get("/sellers/{$sellerId}/products/{$tcin}/inventory");

        return is_array($response) ? $response : null;
    }

    // -------------------------------------------------------------------------

    private function headers(): array
    {
        return [
            'x-api-key'       => $this->tenant->target_api_key,
            'x-seller-id'     => $this->tenant->target_seller_id,
            'x-seller-token'  => $this->tenant->target_seller_token,
            'Accept'          => 'application/json',
            'Content-Type'    => 'application/json',
        ];
    }

    private function get(string $path, array $query = []): ?array
    {
        try {
            $response = $this->httpClient()
                ->get($this->baseUrl . $path, $query);

            return $this->handleResponse($response, 'GET', $path);
        } catch (\Throwable $e) {
            Log::error("TargetPlus GET {$path} exception: " . $e->getMessage(), ['tenant' => $this->tenant->id]);
            return null;
        }
    }

    private function put(string $path, array $body): ?array
    {
        try {
            $response = $this->httpClient()
                ->put($this->baseUrl . $path, $body);

            return $this->handleResponse($response, 'PUT', $path);
        } catch (\Throwable $e) {
            Log::error("TargetPlus PUT {$path} exception: " . $e->getMessage(), ['tenant' => $this->tenant->id]);
            return null;
        }
    }

    private function handleResponse(Response $response, string $method, string $path): ?array
    {
        if ($response->successful()) {
            // Some endpoints return 204 No Content on success
            return $response->json() ?? [];
        }

        Log::warning("TargetPlus {$method} {$path} failed", [
            'tenant' => $this->tenant->id,
            'status' => $response->status(),
            'body'   => $response->body(),
        ]);

        return null;
    }

    private function httpClient(): PendingRequest
    {
        return Http::withHeaders($this->headers())
            ->connectTimeout(8)
            ->timeout(20)
            ->retry(2, 200);
    }

    /**
     * Some TP endpoints return raw arrays; others return paginated objects with a data key.
     */
    private function extractList(array $payload): array
    {
        if (isset($payload['data']) && is_array($payload['data'])) {
            return $payload['data'];
        }

        return array_is_list($payload) ? $payload : [];
    }
}
