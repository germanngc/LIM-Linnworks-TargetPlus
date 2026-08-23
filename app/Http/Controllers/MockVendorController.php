<?php

namespace App\Http\Controllers;

use App\Services\MockVendorStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MockVendorController extends Controller
{
    public function __construct(private MockVendorStore $store) {}

    public function reset(string $accountId): JsonResponse
    {
        $this->store->reset($accountId);
        return response()->json(['success' => true]);
    }

    public function orders(Request $request, string $accountId = 'default'): JsonResponse
    {
        $page = max(1, (int) $request->query('page', 1));
        $perPage = min(100, max(1, (int) $request->query('per_page', 10)));
        $orders = $this->store->orders($accountId);

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

    public function orderAddress(string $accountId, string $orderId): JsonResponse
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

    public function orderStatus(string $accountId, string $orderId, Request $request): JsonResponse
    {
        $status = $request->input('status', 'UPDATED');
        $this->store->mutateOrder($accountId, $orderId, ['status' => $status]);

        return response()->json([
            'order_id' => $orderId,
            'status' => $status,
            'updated_at' => now()->toISOString(),
            'success' => true,
        ]);
    }

    public function bulkFulfillmentsUpdate(string $accountId, string $orderId, Request $request): JsonResponse
    {
        $items = $request->input('items', []);
        $this->store->mutateOrder($accountId, $orderId, ['status' => 'FULFILLED', 'fulfillments' => $items]);

        return response()->json([
            'order_id' => $orderId,
            'updated_items' => count($items),
            'status' => 'FULFILLED',
            'success' => true,
            'processed_at' => now()->toISOString(),
        ]);
    }

    public function products(Request $request, string $accountId): JsonResponse
    {
        $page = max(1, (int) $request->query('page', 1));
        $perPage = min(100, max(1, (int) $request->query('per_page', 10)));

        $products = $this->store->products($accountId);

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

    public function productInventory(string $accountId, string $vendor_reference): JsonResponse
    {
        $product = collect($this->store->products($accountId))->first(fn ($item) => ($item['vendor_reference'] ?? null) === $vendor_reference);
        $data = $product ? ['vendor_reference' => $vendor_reference, 'available_quantity' => $product['available_quantity'], 'status' => $product['status']] : ['vendor_reference' => $vendor_reference, 'available_quantity' => 0, 'status' => 'UNKNOWN'];

        return response()->json($data);
    }

    public function createProduct(string $accountId, Request $request): JsonResponse
    {
        $sku = (string) $request->input('sku');
        $vendor_reference = 'MOCK-' . strtoupper(substr(md5($sku), 0, 11));

        $product = $this->store->upsertProduct($accountId, $vendor_reference, array_merge($request->all(), [
            'vendor_reference' => $vendor_reference,
            'external_id' => $vendor_reference,
            'status' => 'ACTIVE',
            'listing_active' => true,
        ]));
        return response()->json($product, 201);
    }

    public function updateProduct(string $accountId, string $vendor_reference, Request $request): JsonResponse
    {
        $product = $this->store->mutateProduct($accountId, $vendor_reference, array_merge($request->all(), ['status' => 'ACTIVE', 'listing_active' => true]));
        return $product ? response()->json($product) : response()->json(['error' => 'Product not found'], 404);
    }

    public function deleteProduct(string $accountId, string $vendor_reference): JsonResponse
    {
        $product = $this->store->mutateProduct($accountId, $vendor_reference, ['status' => 'INACTIVE', 'listing_active' => false]);
        return $product ? response()->json(['vendor_reference' => $vendor_reference, 'success' => true]) : response()->json(['error' => 'Product not found'], 404);
    }

    public function updateProductInventory(string $accountId, string $vendor_reference, Request $request): JsonResponse
    {
        $quantity = max(0, (int) $request->input('available_quantity', 0));

        $product = $this->store->mutateProduct($accountId, $vendor_reference, ['available_quantity' => $quantity, 'status' => 'ACTIVE']);
        return response()->json([
            'vendor_reference' => $vendor_reference,
            'available_quantity' => $quantity,
            'status' => 'ACTIVE',
            'success' => $product !== null,
            'updated_at' => now()->toISOString(),
        ]);
    }

    public function updateProductPrice(string $accountId, string $vendor_reference, Request $request): JsonResponse
    {
        $price = (float) $request->input('price');
        $product = $this->store->mutateProduct($accountId, $vendor_reference, ['price' => $price]);
        return response()->json(['vendor_reference' => $vendor_reference, 'price' => $price, 'success' => $product !== null]);
    }

    public function cancelOrder(string $accountId, string $orderId, Request $request): JsonResponse
    {
        $order = $this->store->mutateOrder($accountId, $orderId, ['status' => 'CANCELED', 'cancellation' => $request->all()]);
        return response()->json(['order_id' => $orderId, 'status' => 'CANCELED', 'items' => $request->input('Items', []), 'success' => $order !== null]);
    }

    public function refundOrder(string $accountId, string $orderId, Request $request): JsonResponse
    {
        $order = $this->store->mutateOrder($accountId, $orderId, ['status' => 'REFUNDED', 'refund' => $request->all()]);
        return response()->json(['order_id' => $orderId, 'refund_reference' => 'MOCK-REF-' . strtoupper(substr(md5($orderId . json_encode($request->all())), 0, 10)), 'success' => $order !== null]);
    }
}
