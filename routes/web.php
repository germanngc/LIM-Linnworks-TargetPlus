<?php

use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

// Linnworks opens this page with ?token= to start the setup flow
Route::get('/', [AuthController::class, 'show'])->name('auth.show');
Route::post('/setup', [AuthController::class, 'save'])->name('auth.save');
