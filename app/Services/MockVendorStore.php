<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

class MockVendorStore
{
    public function products(string $accountId): array
    {
        return Cache::rememberForever($this->key($accountId, 'products'), fn () => [
            ['vendor_reference' => '12345678901', 'external_id' => 'MOCK-PROD-1001', 'sku' => 'SKU-1001', 'title' => 'Classic Cotton Tee', 'status' => 'ACTIVE', 'available_quantity' => 42, 'price' => 79.99, 'listing_active' => true],
            ['vendor_reference' => '12345678902', 'external_id' => 'MOCK-PROD-1002', 'sku' => 'SKU-1002', 'title' => 'Premium Hoodie', 'status' => 'ACTIVE', 'available_quantity' => 18, 'price' => 62.25, 'listing_active' => true],
            ['vendor_reference' => '12345678903', 'external_id' => 'MOCK-PROD-1003', 'sku' => 'SKU-1003', 'title' => 'Travel Bottle', 'status' => 'INACTIVE', 'available_quantity' => 0, 'price' => 24.95, 'listing_active' => false],
        ]);
    }

    public function saveProducts(string $accountId, array $products): void
    {
        Cache::forever($this->key($accountId, 'products'), array_values($products));
    }

    public function upsertProduct(string $accountId, string $identifier, array $data): array
    {
        $products = $this->products($accountId);
        $index = collect($products)->search(fn ($product) => ($product['vendor_reference'] ?? null) === $identifier || ($product['sku'] ?? null) === ($data['sku'] ?? null));
        $product = array_merge($index === false ? [] : $products[$index], $data, [
            'vendor_reference' => $identifier,
            'external_id' => $data['external_id'] ?? $identifier,
        ]);
        $index === false ? $products[] = $product : $products[$index] = $product;
        $this->saveProducts($accountId, $products);
        return $product;
    }

    public function mutateProduct(string $accountId, string $identifier, array $changes): ?array
    {
        $products = $this->products($accountId);
        foreach ($products as $index => $product) {
            if (($product['vendor_reference'] ?? null) === $identifier || ($product['external_id'] ?? null) === $identifier || ($product['sku'] ?? null) === $identifier) {
                $products[$index] = array_merge($product, $changes);
                $this->saveProducts($accountId, $products);
                return $products[$index];
            }
        }
        return null;
    }

    public function orders(string $accountId): array
    {
        return Cache::rememberForever($this->key($accountId, 'orders'), fn () => $this->defaultOrders());
    }

    public function addOrder(string $accountId, array $data): array
    {
        $orders = $this->orders($accountId);
        $orderId = (string) ($data['id'] ?? $data['order_id'] ?? 'MOCK-ORDER-' . now()->utc()->format('YmdHis'));
        $order = array_merge([
            'id' => $orderId,
            'order_id' => $orderId,
            'order_number' => $orderId,
            'order_date' => now()->utc()->toIso8601String(),
            'last_modified' => now()->utc()->toIso8601String(),
            'status' => 'NEW',
            'currency' => 'USD',
            'order_lines' => [],
        ], $data, [
            'id' => $orderId,
            'order_id' => (string) ($data['order_id'] ?? $orderId),
            'order_number' => (string) ($data['order_number'] ?? $orderId),
            'last_modified' => now()->utc()->toIso8601String(),
        ]);

        $index = collect($orders)->search(fn ($existing) => ($existing['id'] ?? null) === $orderId);
        $index === false ? $orders[] = $order : $orders[$index] = $order;
        Cache::forever($this->key($accountId, 'orders'), $orders);

        return $order;
    }

    public function mutateOrder(string $accountId, string $orderId, array $changes): ?array
    {
        $orders = $this->orders($accountId);
        foreach ($orders as $index => $order) {
            if (($order['id'] ?? null) === $orderId) {
                $orders[$index] = array_merge($order, $changes, ['last_modified' => now()->utc()->toIso8601String()]);
                Cache::forever($this->key($accountId, 'orders'), $orders);
                return $orders[$index];
            }
        }
        return null;
    }

    public function reset(string $accountId): void
    {
        Cache::forget($this->key($accountId, 'products'));
        Cache::forget($this->key($accountId, 'orders'));
    }

    private function key(string $accountId, string $resource): string
    {
        return "mock-vendor:{$accountId}:{$resource}";
    }

    private function defaultOrders(): array
    {
        $now = now()->utc();
        return [
            ['id' => 'MOCK-ORDER-10001', 'order_id' => 'MOCK-ORDER-10001', 'order_number' => 'MOCK-ORDER-10001', 'order_date' => $now->copy()->subMinutes(30)->toIso8601String(), 'last_modified' => $now->copy()->subMinutes(15)->toIso8601String(), 'status' => 'NEW', 'currency' => 'USD', 'order_lines' => [['order_line_number' => 1, 'vendor_reference' => '12345678901', 'sku' => 'SKU-1001', 'quantity' => 1, 'price' => 79.99, 'status' => 'PENDING']]],
            ['id' => 'MOCK-ORDER-10002', 'order_id' => 'MOCK-ORDER-10002', 'order_number' => 'MOCK-ORDER-10002', 'order_date' => $now->copy()->subMinutes(10)->toIso8601String(), 'last_modified' => $now->copy()->subMinutes(5)->toIso8601String(), 'status' => 'PROCESSING', 'currency' => 'USD', 'order_lines' => [['order_line_number' => 1, 'vendor_reference' => '12345678902', 'sku' => 'SKU-1002', 'quantity' => 2, 'price' => 62.25, 'status' => 'PROCESSING']]],
        ];
    }
}
