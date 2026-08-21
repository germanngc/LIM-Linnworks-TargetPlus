<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

class MockTargetPlusStore
{
    public function products(string $sellerId): array
    {
        return Cache::rememberForever($this->key($sellerId, 'products'), fn () => [
            ['tcin' => '12345678901', 'external_id' => 'TP-PROD-1001', 'sku' => 'SKU-1001', 'title' => 'Classic Cotton Tee', 'status' => 'ACTIVE', 'available_quantity' => 42, 'price' => 79.99, 'listing_active' => true],
            ['tcin' => '12345678902', 'external_id' => 'TP-PROD-1002', 'sku' => 'SKU-1002', 'title' => 'Premium Hoodie', 'status' => 'ACTIVE', 'available_quantity' => 18, 'price' => 62.25, 'listing_active' => true],
            ['tcin' => '12345678903', 'external_id' => 'TP-PROD-1003', 'sku' => 'SKU-1003', 'title' => 'Travel Bottle', 'status' => 'INACTIVE', 'available_quantity' => 0, 'price' => 24.95, 'listing_active' => false],
        ]);
    }

    public function saveProducts(string $sellerId, array $products): void
    {
        Cache::forever($this->key($sellerId, 'products'), array_values($products));
    }

    public function upsertProduct(string $sellerId, string $identifier, array $data): array
    {
        $products = $this->products($sellerId);
        $index = collect($products)->search(fn ($product) => ($product['tcin'] ?? null) === $identifier || ($product['sku'] ?? null) === ($data['sku'] ?? null));
        $product = array_merge($index === false ? [] : $products[$index], $data, [
            'tcin' => $identifier,
            'external_id' => $data['external_id'] ?? $identifier,
        ]);
        $index === false ? $products[] = $product : $products[$index] = $product;
        $this->saveProducts($sellerId, $products);
        return $product;
    }

    public function mutateProduct(string $sellerId, string $identifier, array $changes): ?array
    {
        $products = $this->products($sellerId);
        foreach ($products as $index => $product) {
            if (($product['tcin'] ?? null) === $identifier || ($product['external_id'] ?? null) === $identifier || ($product['sku'] ?? null) === $identifier) {
                $products[$index] = array_merge($product, $changes);
                $this->saveProducts($sellerId, $products);
                return $products[$index];
            }
        }
        return null;
    }

    public function orders(string $sellerId): array
    {
        return Cache::rememberForever($this->key($sellerId, 'orders'), fn () => $this->defaultOrders());
    }

    public function mutateOrder(string $sellerId, string $orderId, array $changes): ?array
    {
        $orders = $this->orders($sellerId);
        foreach ($orders as $index => $order) {
            if (($order['id'] ?? null) === $orderId) {
                $orders[$index] = array_merge($order, $changes, ['last_modified' => now()->utc()->toIso8601String()]);
                Cache::forever($this->key($sellerId, 'orders'), $orders);
                return $orders[$index];
            }
        }
        return null;
    }

    public function reset(string $sellerId): void
    {
        Cache::forget($this->key($sellerId, 'products'));
        Cache::forget($this->key($sellerId, 'orders'));
    }

    private function key(string $sellerId, string $resource): string
    {
        return "mock-target-plus:{$sellerId}:{$resource}";
    }

    private function defaultOrders(): array
    {
        $now = now()->utc();
        return [
            ['id' => 'TP-10001', 'order_id' => 'TP-10001', 'order_number' => 'TP-10001', 'order_date' => $now->copy()->subMinutes(30)->toIso8601String(), 'last_modified' => $now->copy()->subMinutes(15)->toIso8601String(), 'status' => 'NEW', 'currency' => 'USD', 'order_lines' => [['order_line_number' => 1, 'tcin' => '12345678901', 'sku' => 'SKU-1001', 'quantity' => 1, 'price' => 79.99, 'status' => 'PENDING']]],
            ['id' => 'TP-10002', 'order_id' => 'TP-10002', 'order_number' => 'TP-10002', 'order_date' => $now->copy()->subMinutes(10)->toIso8601String(), 'last_modified' => $now->copy()->subMinutes(5)->toIso8601String(), 'status' => 'PROCESSING', 'currency' => 'USD', 'order_lines' => [['order_line_number' => 1, 'tcin' => '12345678902', 'sku' => 'SKU-1002', 'quantity' => 2, 'price' => 62.25, 'status' => 'PROCESSING']]],
        ];
    }
}
