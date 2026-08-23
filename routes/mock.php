<?php

use App\Http\Controllers\MockVendorController;
use Illuminate\Support\Facades\Route;

Route::prefix('mock-vendor')->group(function () {
    Route::delete('/accounts/{accountId}/_state', [MockVendorController::class, 'reset']);
    Route::get('/orders', [MockVendorController::class, 'orders']);
    Route::get('/accounts/{accountId}/orders', [MockVendorController::class, 'orders']);
    Route::get('/accounts/{accountId}/order_addresses/{orderId}', [MockVendorController::class, 'orderAddress']);
    Route::put('/accounts/{accountId}/order_statuses/{orderId}', [MockVendorController::class, 'orderStatus']);
    Route::put('/accounts/{accountId}/orders/{orderId}/bulk_fulfillments_update', [MockVendorController::class, 'bulkFulfillmentsUpdate']);
    Route::get('/accounts/{accountId}/products', [MockVendorController::class, 'products']);
    Route::post('/accounts/{accountId}/products', [MockVendorController::class, 'createProduct']);
    Route::put('/accounts/{accountId}/products/{vendor_reference}', [MockVendorController::class, 'updateProduct']);
    Route::delete('/accounts/{accountId}/products/{vendor_reference}', [MockVendorController::class, 'deleteProduct']);
    Route::get('/accounts/{accountId}/products/{vendor_reference}/inventory', [MockVendorController::class, 'productInventory']);
    Route::put('/accounts/{accountId}/products/{vendor_reference}/inventory', [MockVendorController::class, 'updateProductInventory']);
    Route::put('/accounts/{accountId}/products/{vendor_reference}/price', [MockVendorController::class, 'updateProductPrice']);
    Route::put('/accounts/{accountId}/orders/{orderId}/cancel', [MockVendorController::class, 'cancelOrder']);
    Route::post('/accounts/{accountId}/orders/{orderId}/refund', [MockVendorController::class, 'refundOrder']);
});
