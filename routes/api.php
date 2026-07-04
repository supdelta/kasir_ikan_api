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
    Route::post('auth/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('auth/reset-password', [AuthController::class, 'resetPassword']);
    Route::post('auth/google', [AuthController::class, 'google']);

    // Protected
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('auth/logout', [AuthController::class, 'logout']);

        // Profil akun — update nama
        Route::patch('profile', function (\Illuminate\Http\Request $req) {
            $user = $req->user();
            $data = $req->validate(['name' => 'required|string|max:255']);
            $user->update($data);
            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'avatar_url' => $user->avatar ? asset('storage/' . $user->avatar) : null,
            ];
        });

        // Upload foto profil
        Route::post('profile/avatar', function (\Illuminate\Http\Request $req) {
            $req->validate(['photo' => 'required|image|max:4096']); // maks 4MB
            $user = $req->user();
            if ($user->avatar) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($user->avatar);
            }
            $path = $req->file('photo')->store('avatars', 'public');
            $user->update(['avatar' => $path]);
            return ['avatar_url' => asset('storage/' . $path)];
        });

        // Businesses
        Route::get('businesses', fn() => auth()->user()->businesses);
        Route::post('businesses', function (\Illuminate\Http\Request $req) {
            $data = $req->validate(['name' => 'required|string', 'category' => 'nullable|string']);
            return auth()->user()->businesses()->create($data);
        });
        // Update nama usaha (anti-IDOR: cek kepemilikan)
        Route::patch('businesses/{business}', function (\Illuminate\Http\Request $req, \App\Models\Business $business) {
            abort_if($business->user_id !== auth()->id(), 403, 'Akses ditolak.');
            $data = $req->validate(['name' => 'required|string|max:255', 'category' => 'nullable|string']);
            $business->update($data);
            return $business;
        });
        // Upload logo usaha
        Route::post('businesses/{business}/logo', function (\Illuminate\Http\Request $req, \App\Models\Business $business) {
            abort_if($business->user_id !== auth()->id(), 403, 'Akses ditolak.');
            $req->validate(['photo' => 'required|image|max:4096']);
            if ($business->logo) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($business->logo);
            }
            $path = $req->file('photo')->store('logos', 'public');
            $business->update(['logo' => $path]);
            return ['logo_url' => asset('storage/' . $path)];
        });

        // QRIS — ambil setting
        Route::get('businesses/{business}/qris', function (\Illuminate\Http\Request $req, \App\Models\Business $business) {
            abort_if($business->user_id !== auth()->id(), 403, 'Akses ditolak.');
            $q = $business->qrisSetting;
            if (!$q) return response()->json(null);
            return [
                'merchant_name' => $q->merchant_name,
                'image_url' => asset('storage/' . $q->image_path),
            ];
        });
        // QRIS — upload / ganti gambar QR
        Route::post('businesses/{business}/qris', function (\Illuminate\Http\Request $req, \App\Models\Business $business) {
            abort_if($business->user_id !== auth()->id(), 403, 'Akses ditolak.');
            $req->validate([
                'photo' => 'required|image|max:4096',
                'merchant_name' => 'nullable|string|max:255',
            ]);
            $q = $business->qrisSetting;
            if ($q && $q->image_path) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($q->image_path);
            }
            $path = $req->file('photo')->store('qris', 'public');
            $business->qrisSetting()->updateOrCreate(
                ['business_id' => $business->id],
                ['image_path' => $path, 'merchant_name' => $req->merchant_name ?: $business->name]
            );
            return [
                'merchant_name' => $business->qrisSetting()->first()->merchant_name,
                'image_url' => asset('storage/' . $path),
            ];
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
