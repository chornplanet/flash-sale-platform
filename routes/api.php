<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\SalesEventController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::get('/sales-events', [SalesEventController::class, 'index']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/products', [ProductController::class, 'index']);
    Route::get('/products/search', [ProductController::class, 'search']);
    Route::get('/products/{product}', [ProductController::class, 'show']);
    Route::get('/orders/dashboard', [OrderController::class, 'dashboard']);
    Route::get('/orders/dashboard/sale-events/{salesEventId}/summary', [OrderController::class, 'saleEventDashboardSummary']);
    Route::post('/orders/purchase', [OrderController::class, 'purchase']);
    Route::get('/orders', [OrderController::class, 'index']);
    Route::get('/orders/{order}', [OrderController::class, 'show']);
    Route::delete('/orders/{order}', [OrderController::class, 'destroy']);
    Route::delete('/products/{product}', [ProductController::class, 'destroy']);
});
