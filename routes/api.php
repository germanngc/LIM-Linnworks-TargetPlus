<?php

use App\Http\Controllers\DespatchController;
use App\Http\Controllers\OrdersController;
use Illuminate\Support\Facades\Route;

/*
 * Linnworks Channel Integration endpoints.
 * All routes are protected by the lw.auth middleware which resolves
 * AuthorizationToken → Tenant and attaches the Tenant to the request.
 */
Route::middleware('lw.auth')->group(function () {
    Route::post('/orders', [OrdersController::class, 'index']);
    Route::post('/despatch', [DespatchController::class, 'store']);
});
