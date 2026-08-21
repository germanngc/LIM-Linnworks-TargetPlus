<?php

use App\Http\Controllers\MockTargetPlusController;
use Illuminate\Support\Facades\Route;

Route::prefix('mock-target-plus')->group(function () {
    Route::delete('/sellers/{sellerId}/_state', [MockTargetPlusController::class, 'reset']);
    Route::get('/orders', [MockTargetPlusController::class, 'orders']);
    Route::get('/sellers/{sellerId}/orders', [MockTargetPlusController::class, 'orders']);
    Route::get('/sellers/{sellerId}/order_addresses/{orderId}', [MockTargetPlusController::class, 'orderAddress']);
    Route::put('/sellers/{sellerId}/order_statuses/{orderId}', [MockTargetPlusController::class, 'orderStatus']);
    Route::put('/sellers/{sellerId}/orders/{orderId}/bulk_fulfillments_update', [MockTargetPlusController::class, 'bulkFulfillmentsUpdate']);
    Route::get('/sellers/{sellerId}/products', [MockTargetPlusController::class, 'products']);
    Route::post('/sellers/{sellerId}/products', [MockTargetPlusController::class, 'createProduct']);
    Route::put('/sellers/{sellerId}/products/{tcin}', [MockTargetPlusController::class, 'updateProduct']);
    Route::delete('/sellers/{sellerId}/products/{tcin}', [MockTargetPlusController::class, 'deleteProduct']);
    Route::get('/sellers/{sellerId}/products/{tcin}/inventory', [MockTargetPlusController::class, 'productInventory']);
    Route::put('/sellers/{sellerId}/products/{tcin}/inventory', [MockTargetPlusController::class, 'updateProductInventory']);
    Route::put('/sellers/{sellerId}/products/{tcin}/price', [MockTargetPlusController::class, 'updateProductPrice']);
    Route::put('/sellers/{sellerId}/orders/{orderId}/cancel', [MockTargetPlusController::class, 'cancelOrder']);
    Route::post('/sellers/{sellerId}/orders/{orderId}/refund', [MockTargetPlusController::class, 'refundOrder']);
});
