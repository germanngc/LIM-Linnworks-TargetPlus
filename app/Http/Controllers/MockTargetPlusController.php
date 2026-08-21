<?php

namespace App\Http\Controllers;

use App\Services\MockTargetPlusStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MockTargetPlusController extends Controller
{
    public function __construct(private MockTargetPlusStore $store) {}

    public function reset(string $sellerId): JsonResponse
    {
        $this->store->reset($sellerId);
        return response()->json(['success' => true]);
    }

    public function orders(Request $request, string $sellerId = 'default'): JsonResponse
    {
        $page = max(1, (int) $request->query('page', 1));
        $perPage = min(100, max(1, (int) $request->query('per_page', 10)));
        $orders = $this->store->orders($sellerId);
        /*
        $orders = [
            [
                'id' => 'TP-10001',
                'order_id' => 'TP-10001',
                'order_date' => $now->copy()->subMinutes(30)->toIso8601String(),
                'last_modified' => $now->copy()->subMinutes(15)->toIso8601String(),
                'status' => 'NEW',
                'customer' => [
                    'first_name' => 'Jane',
                    'last_name' => 'Doe',
                    'email' => 'jane@example.com',
                ],
                'total_amount' => 89.99,
                'currency' => 'USD',
                'order_lines' => [
                    [
                        'order_line_number' => 1,
                        'tcin' => '12345678901',
                        'sku' => 'SKU-1001',
                        'quantity' => 1,
                        'price' => 79.99,
                        'status' => 'PENDING',
                        'order_line_statuses' => [['status' => 'PENDING']],
                    ],
                ],
                'shipping_address' => [
                    'first_name' => 'Jane',
                    'last_name' => 'Doe',
                    'address1' => '123 Market Street',
                    'city' => 'Chicago',
                    'state' => 'IL',
                    'postal_code' => '60601',
                    'country' => 'US',
                ],
            ],
            [
                'id' => 'TP-10002',
                'order_id' => 'TP-10002',
                'order_date' => $now->copy()->subMinutes(10)->toIso8601String(),
                'last_modified' => $now->copy()->subMinutes(5)->toIso8601String(),
                'status' => 'PROCESSING',
                'customer' => [
                    'first_name' => 'John',
                    'last_name' => 'Smith',
                    'email' => 'john@example.com',
                ],
                'total_amount' => 149.50,
                'currency' => 'USD',
                'order_lines' => [
                    [
                        'order_line_number' => 1,
                        'tcin' => '12345678902',
                        'sku' => 'SKU-1002',
                        'quantity' => 2,
                        'price' => 62.25,
                        'status' => 'PROCESSING',
                        'order_line_statuses' => [['status' => 'PROCESSING']],
                    ],
                ],
                'shipping_address' => [
                    'first_name' => 'John',
                    'last_name' => 'Smith',
                    'address1' => '45 Pine Ave',
                    'city' => 'Dallas',
                    'state' => 'TX',
                    'postal_code' => '75201',
                    'country' => 'US',
                ],
            ],
        ]; */

        $start = ($page - 1) * $perPage;
        $pageItems = array_slice($orders, $start, $perPage);

        return response()->json([
            'data' => $pageItems,
            'page' => $page,
            'per_page' => $perPage,
            'total' => count($orders),
            'has_more' => $start + $perPage < count($orders),
        ]);
    }

    public function orderAddress(string $sellerId, string $orderId): JsonResponse
    {
        return response()->json([
            'order_id' => $orderId,
            'address' => [
                'first_name' => 'Jane',
                'last_name' => 'Doe',
                'address1' => '123 Market Street',
                'address2' => '',
                'city' => 'Chicago',
                'state' => 'IL',
                'postal_code' => '60601',
                'country' => 'US',
            ],
        ]);
    }

    public function orderStatus(string $sellerId, string $orderId, Request $request): JsonResponse
    {
        $status = $request->input('status', 'UPDATED');
        $this->store->mutateOrder($sellerId, $orderId, ['status' => $status]);

        return response()->json([
            'order_id' => $orderId,
            'status' => $status,
            'updated_at' => now()->toISOString(),
            'success' => true,
        ]);
    }

    public function bulkFulfillmentsUpdate(string $sellerId, string $orderId, Request $request): JsonResponse
    {
        $items = $request->input('items', []);
        $this->store->mutateOrder($sellerId, $orderId, ['status' => 'FULFILLED', 'fulfillments' => $items]);

        return response()->json([
            'order_id' => $orderId,
            'updated_items' => count($items),
            'status' => 'FULFILLED',
            'success' => true,
            'processed_at' => now()->toISOString(),
        ]);
    }

    public function products(Request $request, string $sellerId): JsonResponse
    {
        $page = max(1, (int) $request->query('page', 1));
        $perPage = min(100, max(1, (int) $request->query('per_page', 10)));

        $products = $this->store->products($sellerId);
        /* $products = [
            [
                'tcin' => '12345678901',
                'external_id' => 'TP-PROD-1001',
                'sku' => 'SKU-1001',
                'title' => 'Classic Cotton Tee',
                'status' => 'ACTIVE',
                'available_quantity' => 42,
                'listing_active' => true,
            ],
            [
                'tcin' => '12345678902',
                'external_id' => 'TP-PROD-1002',
                'sku' => 'SKU-1002',
                'title' => 'Premium Hoodie',
                'status' => 'ACTIVE',
                'available_quantity' => 18,
                'listing_active' => true,
            ],
            [
                'tcin' => '12345678903',
                'external_id' => 'TP-PROD-1003',
                'sku' => 'SKU-1003',
                'title' => 'Travel Bottle',
                'status' => 'INACTIVE',
                'available_quantity' => 0,
                'listing_active' => false,
            ],
        ]; */

        $start = ($page - 1) * $perPage;
        $pageItems = array_slice($products, $start, $perPage);

        return response()->json([
            'data' => $pageItems,
            'page' => $page,
            'per_page' => $perPage,
            'total' => count($products),
            'has_more' => $start + $perPage < count($products),
        ]);
    }

    public function productInventory(string $sellerId, string $tcin): JsonResponse
    {
        $inventoryMap = [
            '12345678901' => ['tcin' => '12345678901', 'available_quantity' => 42, 'status' => 'ACTIVE'],
            '12345678902' => ['tcin' => '12345678902', 'available_quantity' => 18, 'status' => 'ACTIVE'],
            '12345678903' => ['tcin' => '12345678903', 'available_quantity' => 0, 'status' => 'INACTIVE'],
        ];

        $product = collect($this->store->products($sellerId))->first(fn ($item) => ($item['tcin'] ?? null) === $tcin);
        $data = $product ? ['tcin' => $tcin, 'available_quantity' => $product['available_quantity'], 'status' => $product['status']] : ['tcin' => $tcin, 'available_quantity' => 0, 'status' => 'UNKNOWN'];

        return response()->json($data);
    }

    public function createProduct(string $sellerId, Request $request): JsonResponse
    {
        $sku = (string) $request->input('sku');
        $tcin = 'MOCK-' . strtoupper(substr(md5($sku), 0, 11));

        $product = $this->store->upsertProduct($sellerId, $tcin, array_merge($request->all(), [
            'tcin' => $tcin,
            'external_id' => $tcin,
            'status' => 'ACTIVE',
            'listing_active' => true,
        ]));
        return response()->json($product, 201);
    }

    public function updateProduct(string $sellerId, string $tcin, Request $request): JsonResponse
    {
        $product = $this->store->mutateProduct($sellerId, $tcin, array_merge($request->all(), ['status' => 'ACTIVE', 'listing_active' => true]));
        return $product ? response()->json($product) : response()->json(['error' => 'Product not found'], 404);
    }

    public function deleteProduct(string $sellerId, string $tcin): JsonResponse
    {
        $product = $this->store->mutateProduct($sellerId, $tcin, ['status' => 'INACTIVE', 'listing_active' => false]);
        return $product ? response()->json(['tcin' => $tcin, 'success' => true]) : response()->json(['error' => 'Product not found'], 404);
    }

    public function updateProductInventory(string $sellerId, string $tcin, Request $request): JsonResponse
    {
        $quantity = max(0, (int) $request->input('available_quantity', 0));

        $product = $this->store->mutateProduct($sellerId, $tcin, ['available_quantity' => $quantity, 'status' => 'ACTIVE']);
        return response()->json([
            'tcin' => $tcin,
            'available_quantity' => $quantity,
            'status' => 'ACTIVE',
            'success' => $product !== null,
            'updated_at' => now()->toISOString(),
        ]);
    }

    public function updateProductPrice(string $sellerId, string $tcin, Request $request): JsonResponse
    {
        $price = (float) $request->input('price');
        $product = $this->store->mutateProduct($sellerId, $tcin, ['price' => $price]);
        return response()->json(['tcin' => $tcin, 'price' => $price, 'success' => $product !== null]);
    }

    public function cancelOrder(string $sellerId, string $orderId, Request $request): JsonResponse
    {
        $order = $this->store->mutateOrder($sellerId, $orderId, ['status' => 'CANCELED', 'cancellation' => $request->all()]);
        return response()->json(['order_id' => $orderId, 'status' => 'CANCELED', 'items' => $request->input('Items', []), 'success' => $order !== null]);
    }

    public function refundOrder(string $sellerId, string $orderId, Request $request): JsonResponse
    {
        $order = $this->store->mutateOrder($sellerId, $orderId, ['status' => 'REFUNDED', 'refund' => $request->all()]);
        return response()->json(['order_id' => $orderId, 'refund_reference' => 'MOCK-REF-' . strtoupper(substr(md5($orderId . json_encode($request->all())), 0, 10)), 'success' => $order !== null]);
    }
}
