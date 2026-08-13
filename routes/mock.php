<?php

use App\Http\Controllers\MockTargetPlusController;
use Illuminate\Support\Facades\Route;

Route::prefix('mock-target-plus')->group(function () {
    Route::get('/orders', [MockTargetPlusController::class, 'orders']);
    Route::get('/sellers/{sellerId}/orders', [MockTargetPlusController::class, 'orders']);
    Route::get('/sellers/{sellerId}/order_addresses/{orderId}', [MockTargetPlusController::class, 'orderAddress']);
    Route::put('/sellers/{sellerId}/order_statuses/{orderId}', [MockTargetPlusController::class, 'orderStatus']);
    Route::put('/sellers/{sellerId}/orders/{orderId}/bulk_fulfillments_update', [MockTargetPlusController::class, 'bulkFulfillmentsUpdate']);
    Route::get('/sellers/{sellerId}/products', [MockTargetPlusController::class, 'products']);
    Route::get('/sellers/{sellerId}/products/{tcin}/inventory', [MockTargetPlusController::class, 'productInventory']);
});
