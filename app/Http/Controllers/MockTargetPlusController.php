<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MockTargetPlusController extends Controller
{
    public function orders(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->query('page', 1));
        $perPage = min(100, max(1, (int) $request->query('per_page', 10)));

        $orders = [
            [
                'id' => 'TP-10001',
                'order_id' => 'TP-10001',
                'order_date' => '2026-08-12T09:30:00Z',
                'updated_at' => '2026-08-12T09:45:00Z',
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
                'order_date' => '2026-08-12T10:15:00Z',
                'updated_at' => '2026-08-12T10:20:00Z',
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
        ];

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

    public function orderAddress(string $orderId): JsonResponse
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

    public function orderStatus(string $orderId, Request $request): JsonResponse
    {
        $status = $request->input('status', 'UPDATED');

        return response()->json([
            'order_id' => $orderId,
            'status' => $status,
            'updated_at' => now()->toISOString(),
            'success' => true,
        ]);
    }

    public function bulkFulfillmentsUpdate(string $orderId, Request $request): JsonResponse
    {
        $items = $request->input('items', []);

        return response()->json([
            'order_id' => $orderId,
            'updated_items' => count($items),
            'status' => 'FULFILLED',
            'success' => true,
            'processed_at' => now()->toISOString(),
        ]);
    }

    public function products(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->query('page', 1));
        $perPage = min(100, max(1, (int) $request->query('per_page', 10)));

        $products = [
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
        ];

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

    public function productInventory(string $tcin): JsonResponse
    {
        $inventoryMap = [
            '12345678901' => ['tcin' => '12345678901', 'available_quantity' => 42, 'status' => 'ACTIVE'],
            '12345678902' => ['tcin' => '12345678902', 'available_quantity' => 18, 'status' => 'ACTIVE'],
            '12345678903' => ['tcin' => '12345678903', 'available_quantity' => 0, 'status' => 'INACTIVE'],
        ];

        $data = $inventoryMap[$tcin] ?? ['tcin' => $tcin, 'available_quantity' => 0, 'status' => 'UNKNOWN'];

        return response()->json($data);
    }
}
