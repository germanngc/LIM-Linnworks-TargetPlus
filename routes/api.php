<?php

use App\Http\Controllers\ChannelIntegrationController;
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

/*
 * Discovery routes matching common Channel Integration manifest endpoints.
 * Keep these while validating payloads and behaviour from Linnworks.
 */
Route::post('/Config/AddNewUser', [ChannelIntegrationController::class, 'addNewUser']);
Route::post('/oauth/authorize', [ChannelIntegrationController::class, 'oauthAuthorize']);
Route::post('/Listing/GetConfiguratorSettings', [ChannelIntegrationController::class, 'getConfiguratorSettings']);
Route::match(['get', 'post'], '/Config/UserConfig', [ChannelIntegrationController::class, 'userConfig']);
Route::match(['get', 'post'], '/Config/GetConfig', [ChannelIntegrationController::class, 'userConfig']);
Route::match(['get', 'post'], '/ChannelIntegration/GetConfig', [ChannelIntegrationController::class, 'userConfig']);

Route::middleware('lw.auth')->group(function () {
    Route::post('/Config/ConfigTest', [ChannelIntegrationController::class, 'configTest']);
    Route::post('/Config/SaveUserConfig', [ChannelIntegrationController::class, 'saveUserConfig']);
    Route::post('/Config/SaveConfig', [ChannelIntegrationController::class, 'saveUserConfig']);
    Route::post('/ChannelIntegration/SaveConfig', [ChannelIntegrationController::class, 'saveUserConfig']);
    Route::post('/Config/ShippingTags', [ChannelIntegrationController::class, 'shippingTags']);
    Route::post('/Config/PaymentTags', [ChannelIntegrationController::class, 'paymentTags']);
    Route::post('/Config/ConfigDeleted', [ChannelIntegrationController::class, 'configDeleted']);

    Route::post('/Order/Orders', [ChannelIntegrationController::class, 'orders']);
    Route::post('/Order/Despatch', [ChannelIntegrationController::class, 'despatch']);
    Route::post('/Order/Cancel', [ChannelIntegrationController::class, 'cancel']);
    Route::post('/Order/Refund', [ChannelIntegrationController::class, 'refund']);
    Route::post('/Order/PostSaleOptions', [ChannelIntegrationController::class, 'postSaleOptions']);

    Route::post('/Product/Products', [ChannelIntegrationController::class, 'products']);
    Route::post('/Product/InventoryUpdate', [ChannelIntegrationController::class, 'inventoryUpdate']);
    Route::post('/Product/PriceUpdate', [ChannelIntegrationController::class, 'priceUpdate']);

    Route::post('/Listing/GetCategories', [ChannelIntegrationController::class, 'getCategories']);
    Route::post('/Listing/GetAttributesByCategory', [ChannelIntegrationController::class, 'getAttributesByCategory']);
    Route::post('/Listing/GetVariationsByCategory', [ChannelIntegrationController::class, 'getVariationsByCategory']);
    Route::post('/Listing/ListingUpdate', [ChannelIntegrationController::class, 'listingUpdate']);
    Route::post('/Listing/ListingDelete', [ChannelIntegrationController::class, 'listingDelete']);
    Route::post('/Listing/CheckFeed', [ChannelIntegrationController::class, 'checkFeed']);
});
