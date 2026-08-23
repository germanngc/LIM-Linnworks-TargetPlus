<?php

namespace App\Services;

use App\Models\Tenant;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;

class VendorApiService
{
    private Tenant $tenant;
    private string $baseUrl;
    private ?array $lastFailure = null;

    public function __construct(Tenant $tenant)
    {
        $this->tenant = $tenant;
        $this->baseUrl = $tenant->vendorBaseUrl();
    }

    public function lastFailure(): ?array { return $this->lastFailure; }
    public function lastFailureMessage(): string { return (string) ($this->lastFailure['message'] ?? 'Unknown Vendor failure.'); }

    /**
     * GET /accounts/{account_id}/orders
     * Returns raw array of orders. Accepts any subset of the supported query params.
     */
    public function getOrders(array $params = []): ?array
    {
        if ($this->usesInAppMock()) {
            return app(MockVendorStore::class)->orders($this->tenant->vendor_account_id);
        }

        $accountId = $this->tenant->vendor_account_id;

        $response = $this->get("/accounts/{$accountId}/orders", $params);

        if ($response === null) {
            return null;
        }

        return $this->extractList($response);
    }

    public function testConnection(): bool
    {
        return $this->getOrders(['page' => 1, 'per_page' => 1]) !== null;
    }

    /**
     * GET /accounts/{account_id}/order_addresses/{order_id}
     */
    public function getOrderAddress(string $orderId): ?array
    {
        if ($this->usesInAppMock()) {
            $order = collect(app(MockVendorStore::class)->orders($this->tenant->vendor_account_id))
                ->first(fn ($item) => ($item['id'] ?? null) === $orderId || ($item['order_id'] ?? null) === $orderId);

            return [
                'order_id' => $orderId,
                'address' => $order['shipping_address'] ?? [
                    'first_name' => 'Mock',
                    'last_name' => 'Customer',
                    'address1' => '123 Market Street',
                    'address2' => '',
                    'city' => 'Chicago',
                    'state' => 'IL',
                    'postal_code' => '60601',
                    'country' => 'US',
                ],
            ];
        }

        $accountId = $this->tenant->vendor_account_id;

        return $this->get("/accounts/{$accountId}/order_addresses/{$orderId}");
    }

    /**
     * PUT /accounts/{account_id}/orders/{order_id}/bulk_fulfillments_update
     * $items: array of fulfillment line objects matching the TP spec.
     */
    public function confirmShipment(string $orderId, array $items): bool
    {
        if ($this->usesInAppMock()) {
            $order = app(MockVendorStore::class)->mutateOrder($this->tenant->vendor_account_id, $orderId, [
                'status' => 'FULFILLED',
                'fulfillments' => $items,
            ]);

            Log::info('Vendor in-app mock shipment confirmation', [
                'tenant_id' => $this->tenant->id,
                'account_id' => $this->tenant->vendor_account_id,
                'order_id' => $orderId,
                'item_count' => count($items),
                'found' => $order !== null,
            ]);

            return $order !== null;
        }

        $accountId = $this->tenant->vendor_account_id;

        $response = $this->put(
            "/accounts/{$accountId}/orders/{$orderId}/bulk_fulfillments_update",
            ['items' => $items]
        );

        return $response !== null;
    }

    /**
     * PUT /accounts/{account_id}/order_statuses/{order_id}
     */
    public function updateOrderStatus(string $orderId, string $status): bool
    {
        if ($this->usesInAppMock()) {
            $order = app(MockVendorStore::class)->mutateOrder($this->tenant->vendor_account_id, $orderId, [
                'status' => $status,
            ]);

            Log::info('Vendor in-app mock order status update', [
                'tenant_id' => $this->tenant->id,
                'account_id' => $this->tenant->vendor_account_id,
                'order_id' => $orderId,
                'status' => $status,
                'found' => $order !== null,
            ]);

            return $order !== null;
        }

        $accountId = $this->tenant->vendor_account_id;

        $response = $this->put(
            "/accounts/{$accountId}/order_statuses/{$orderId}",
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
        if ($this->usesInAppMock()) {
            return app(MockVendorStore::class)->products($this->tenant->vendor_account_id);
        }

        $accountId = $this->tenant->vendor_account_id;
        $response = $this->get("/accounts/{$accountId}/products", $params);

        return $response ? $this->extractList($response) : null;
    }

    /**
     * Fetch stock info for a single product.
     */
    public function getProductInventory(string $vendor_reference): ?array
    {
        if ($this->usesInAppMock()) {
            $product = collect(app(MockVendorStore::class)->products($this->tenant->vendor_account_id))
                ->first(fn ($item) => ($item['vendor_reference'] ?? null) === $vendor_reference || ($item['external_id'] ?? null) === $vendor_reference || ($item['sku'] ?? null) === $vendor_reference);

            return $product ? [
                'vendor_reference' => (string) ($product['vendor_reference'] ?? $vendor_reference),
                'available_quantity' => (int) ($product['available_quantity'] ?? 0),
                'status' => (string) ($product['status'] ?? 'UNKNOWN'),
            ] : null;
        }

        $accountId = $this->tenant->vendor_account_id;
        $response = $this->get("/accounts/{$accountId}/products/{$vendor_reference}/inventory");

        return is_array($response) ? $response : null;
    }

    /**
     * PUT /accounts/{account_id}/products/{product_identifier}/inventory
     * Push an available quantity update to Vendor.
     */
    public function updateProductInventory(string $productIdentifier, int $quantity): bool
    {
        if ($this->usesInAppMock()) {
            $product = app(MockVendorStore::class)->mutateProduct($this->tenant->vendor_account_id, $productIdentifier, [
                'available_quantity' => max(0, $quantity),
                'status' => 'ACTIVE',
            ]);

            Log::info('Vendor in-app mock inventory update', [
                'tenant_id' => $this->tenant->id,
                'account_id' => $this->tenant->vendor_account_id,
                'identifier' => $productIdentifier,
                'quantity' => max(0, $quantity),
                'found' => $product !== null,
            ]);

            return $product !== null;
        }

        $accountId = $this->tenant->vendor_account_id;

        $response = $this->put(
            "/accounts/{$accountId}/products/{$productIdentifier}/inventory",
            ['available_quantity' => max(0, $quantity)]
        );

        return $response !== null;
    }

    public function updateProductPrice(string $productIdentifier, float $price): bool
    {
        if ($this->usesInAppMock()) {
            $product = app(MockVendorStore::class)->mutateProduct($this->tenant->vendor_account_id, $productIdentifier, [
                'price' => max(0, $price),
            ]);

            Log::info('Vendor in-app mock price update', [
                'tenant_id' => $this->tenant->id,
                'account_id' => $this->tenant->vendor_account_id,
                'identifier' => $productIdentifier,
                'price' => max(0, $price),
                'found' => $product !== null,
            ]);

            return $product !== null;
        }

        return $this->put("/accounts/{$this->tenant->vendor_account_id}/products/{$productIdentifier}/price", ['price' => max(0, $price)]) !== null;
    }

    public function cancelOrder(string $orderId, array $cancellation): bool
    {
        if ($this->usesInAppMock()) {
            $order = app(MockVendorStore::class)->mutateOrder($this->tenant->vendor_account_id, $orderId, [
                'status' => 'CANCELED',
                'cancellation' => $cancellation,
            ]);

            Log::info('Vendor in-app mock order cancel', [
                'tenant_id' => $this->tenant->id,
                'account_id' => $this->tenant->vendor_account_id,
                'order_id' => $orderId,
                'found' => $order !== null,
            ]);

            return $order !== null;
        }

        return $this->put("/accounts/{$this->tenant->vendor_account_id}/orders/{$orderId}/cancel", $cancellation) !== null;
    }

    public function refundOrder(string $orderId, array $refund): ?array
    {
        if ($this->usesInAppMock()) {
            $order = app(MockVendorStore::class)->mutateOrder($this->tenant->vendor_account_id, $orderId, [
                'status' => 'REFUNDED',
                'refund' => $refund,
            ]);
            $reference = 'MOCK-REF-' . strtoupper(substr(md5($orderId . json_encode($refund)), 0, 10));

            Log::info('Vendor in-app mock order refund', [
                'tenant_id' => $this->tenant->id,
                'account_id' => $this->tenant->vendor_account_id,
                'order_id' => $orderId,
                'refund_reference' => $reference,
                'found' => $order !== null,
            ]);

            return $order === null ? null : [
                'order_id' => $orderId,
                'refund_reference' => $reference,
                'success' => true,
            ];
        }

        return $this->post("/accounts/{$this->tenant->vendor_account_id}/orders/{$orderId}/refund", $refund);
    }

    public function createProduct(array $product): ?array
    {
        if ($this->usesInAppMock()) {
            $sku = (string) ($product['sku'] ?? '');
            $vendor_reference = 'MOCK-' . strtoupper(substr(md5($sku), 0, 11));

            $created = app(MockVendorStore::class)->upsertProduct($this->tenant->vendor_account_id, $vendor_reference, array_merge($product, [
                'vendor_reference' => $vendor_reference,
                'external_id' => $vendor_reference,
                'status' => 'ACTIVE',
                'listing_active' => true,
            ]));

            Log::info('Vendor in-app mock product create', [
                'tenant_id' => $this->tenant->id,
                'account_id' => $this->tenant->vendor_account_id,
                'sku' => $sku,
                'vendor_reference' => $vendor_reference,
                'title' => (string) ($product['title'] ?? ''),
                'quantity' => (int) ($product['available_quantity'] ?? 0),
                'price' => (float) ($product['price'] ?? 0),
            ]);

            return $created;
        }

        return $this->post("/accounts/{$this->tenant->vendor_account_id}/products", $product);
    }

    public function updateProduct(string $productIdentifier, array $product): ?array
    {
        if ($this->usesInAppMock()) {
            $updated = app(MockVendorStore::class)->mutateProduct($this->tenant->vendor_account_id, $productIdentifier, array_merge($product, [
                'status' => 'ACTIVE',
                'listing_active' => true,
            ]));

            Log::info('Vendor in-app mock product update', [
                'tenant_id' => $this->tenant->id,
                'account_id' => $this->tenant->vendor_account_id,
                'identifier' => $productIdentifier,
                'sku' => (string) ($product['sku'] ?? ''),
                'found' => $updated !== null,
            ]);

            return $updated;
        }

        return $this->put("/accounts/{$this->tenant->vendor_account_id}/products/{$productIdentifier}", $product);
    }

    public function deleteProduct(string $productIdentifier): bool
    {
        if ($this->usesInAppMock()) {
            $product = app(MockVendorStore::class)->mutateProduct($this->tenant->vendor_account_id, $productIdentifier, [
                'status' => 'INACTIVE',
                'listing_active' => false,
            ]);

            Log::info('Vendor in-app mock product delete', [
                'tenant_id' => $this->tenant->id,
                'account_id' => $this->tenant->vendor_account_id,
                'identifier' => $productIdentifier,
                'found' => $product !== null,
            ]);

            return $product !== null;
        }

        return $this->delete("/accounts/{$this->tenant->vendor_account_id}/products/{$productIdentifier}") !== null;
    }

    // -------------------------------------------------------------------------

    private function headers(): array
    {
        return [
            'x-api-key'       => $this->tenant->vendor_api_key,
            'x-vendor-account-id'     => $this->tenant->vendor_account_id,
            'x-vendor-account-token'  => $this->tenant->vendor_account_token,
            'Accept'          => 'application/json',
            'Content-Type'    => 'application/json',
            'X-Correlation-ID' => (string) (Context::get('correlation_id') ?: Str::uuid()),
        ];
    }

    private function usesInAppMock(): bool
    {
        return str_contains($this->baseUrl, '/mock-vendor');
    }

    private function get(string $path, array $query = []): ?array
    {
        try {
            $response = $this->httpClient()
                ->get($this->baseUrl . $path, $query);

            return $this->handleResponse($response, 'GET', $path);
        } catch (\Throwable $e) {
            $this->captureException($e, 'GET', $path);
            Log::error("Vendor GET {$path} exception: " . $e->getMessage(), ['tenant' => $this->tenant->id]);
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
            $this->captureException($e, 'PUT', $path);
            Log::error("Vendor PUT {$path} exception: " . $e->getMessage(), ['tenant' => $this->tenant->id]);
            return null;
        }
    }

    private function post(string $path, array $body): ?array
    {
        try {
            return $this->handleResponse($this->httpClient()->post($this->baseUrl . $path, $body), 'POST', $path);
        } catch (\Throwable $e) {
            $this->captureException($e, 'POST', $path);
            Log::error("Vendor POST {$path} exception: " . $e->getMessage(), ['tenant' => $this->tenant->id]);
            return null;
        }
    }

    private function delete(string $path): ?array
    {
        try {
            return $this->handleResponse($this->httpClient()->delete($this->baseUrl . $path), 'DELETE', $path);
        } catch (\Throwable $e) {
            $this->captureException($e, 'DELETE', $path);
            Log::error("Vendor DELETE {$path} exception: " . $e->getMessage(), ['tenant' => $this->tenant->id]);
            return null;
        }
    }

    private function handleResponse(Response $response, string $method, string $path): ?array
    {
        if ($response->successful()) {
            $this->lastFailure = null;
            // Some endpoints return 204 No Content on success
            return $response->json() ?? [];
        }

        $classification = match (true) {
            in_array($response->status(), [401, 403], true) => 'authentication',
            $response->status() === 429 => 'rate_limit',
            $response->serverError() => 'server',
            default => 'validation',
        };
        $this->lastFailure = ['classification' => $classification, 'status' => $response->status(), 'message' => "{$classification} failure ({$response->status()}) on {$method} {$path}"];
        Log::warning("Vendor {$method} {$path} failed", [
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
            ->retry(3, fn (int $attempt) => 200 * (2 ** ($attempt - 1)), function (\Throwable $exception): bool {
                if ($exception instanceof ConnectionException) return true;
                if (!$exception instanceof RequestException || !$exception->response) return false;
                return $exception->response->status() === 429 || $exception->response->serverError();
            }, false);
    }

    private function captureException(\Throwable $e, string $method, string $path): void
    {
        $this->lastFailure = ['classification' => $e instanceof ConnectionException ? 'connection' : 'exception', 'status' => null, 'message' => "{$method} {$path}: {$e->getMessage()}"];
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
