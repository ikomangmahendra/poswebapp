<?php

use App\Http\Controllers\CategoryController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\TransactionController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::apiResource('categories', CategoryController::class);
Route::apiResource('products', ProductController::class);
Route::apiResource('transactions', TransactionController::class)->only(['index', 'store', 'show']);
Route::get('/dashboard', [DashboardController::class, 'index']);
Route::get('/dashboard/low-stock', [DashboardController::class, 'lowStock']);
Route::get('/dashboard/categories', [DashboardController::class, 'categoryBreakdown']);
Route::get('/dashboard/recent-products', [DashboardController::class, 'recentProducts']);
