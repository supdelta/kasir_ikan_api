<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\ReceivableController;
use App\Http\Controllers\Api\V1\ReportController;
use App\Http\Controllers\Api\V1\TransactionController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {

    // Auth — publik
    Route::post('auth/register', [AuthController::class, 'register']);
    Route::post('auth/login', [AuthController::class, 'login']);

    // Protected
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('auth/logout', [AuthController::class, 'logout']);

        // Businesses
        Route::get('businesses', fn() => auth()->user()->businesses);
        Route::post('businesses', function (\Illuminate\Http\Request $req) {
            $data = $req->validate(['name' => 'required|string', 'category' => 'nullable|string']);
            return auth()->user()->businesses()->create($data);
        });

        // Products
        Route::prefix('businesses/{business}')->group(function () {
            Route::get('products', [ProductController::class, 'index']);
            Route::post('products', [ProductController::class, 'store']);
            Route::put('products/{product}', [ProductController::class, 'update']);
            Route::delete('products/{product}', [ProductController::class, 'destroy']);

            // Transactions
            Route::get('transactions', [TransactionController::class, 'index']);
            Route::post('transactions', [TransactionController::class, 'store']);
            Route::post('transactions/bulk-sync', [TransactionController::class, 'bulkSync']);
            Route::delete('transactions/{transaction}', [TransactionController::class, 'destroy']);

            // Receivables
            Route::get('receivables', [ReceivableController::class, 'index']);
            Route::post('receivables', [ReceivableController::class, 'store']);
            Route::post('receivables/{receivable}/pay', [ReceivableController::class, 'pay']);
            Route::delete('receivables/{receivable}', [ReceivableController::class, 'destroy']);

            // Reports
            Route::get('reports/daily', [ReportController::class, 'daily']);
        });
    });
});
