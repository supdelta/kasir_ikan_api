<?php

use App\Http\Controllers\Api\V1\AdminController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\ReceivableController;
use App\Http\Controllers\Api\V1\ReportController;
use App\Http\Controllers\Api\V1\ScanController;
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

        // Scan bon (AI) — key di server
        Route::post('scan-bon', [ScanController::class, 'bon']);

        // ===== Panel Super Admin (developer) =====
        Route::get('admin/stats', [AdminController::class, 'stats']);
        Route::get('admin/users', [AdminController::class, 'users']);
        Route::post('admin/users/{user}/premium', [AdminController::class, 'grantPremium']);
        Route::delete('admin/users/{user}/premium', [AdminController::class, 'revokePremium']);
        Route::delete('admin/users/{user}', [AdminController::class, 'deleteUser']);

        // Status langganan
        Route::get('subscription', function (\Illuminate\Http\Request $req) {
            $u = $req->user();
            return [
                'is_premium' => $u->isPremium(),
                'premium_until' => $u->premium_until,
            ];
        });

        // ===== Notifikasi in-app =====
        Route::get('notifications', function (\Illuminate\Http\Request $req) {
            return \App\Models\AppNotification::where('user_id', $req->user()->id)
                ->orderByDesc('created_at')->limit(50)->get();
        });
        Route::get('notifications/unread-count', function (\Illuminate\Http\Request $req) {
            return ['count' => \App\Models\AppNotification::where('user_id', $req->user()->id)
                ->whereNull('read_at')->count()];
        });
        Route::post('notifications/{id}/read', function (\Illuminate\Http\Request $req, $id) {
            \App\Models\AppNotification::where('user_id', $req->user()->id)->where('id', $id)
                ->update(['read_at' => now()]);
            return ['message' => 'ok'];
        });
        Route::post('notifications/read-all', function (\Illuminate\Http\Request $req) {
            \App\Models\AppNotification::where('user_id', $req->user()->id)->whereNull('read_at')
                ->update(['read_at' => now()]);
            return ['message' => 'ok'];
        });

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
                'avatar_url' => $user->avatar_url,
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

        // Businesses — semua usaha yang bisa diakses (owner + staff), dengan role
        Route::get('businesses', function () {
            $userId = auth()->id();
            return \App\Models\Business::whereHas('members', fn($q) => $q->where('user_id', $userId))
                ->get()
                ->map(function ($b) use ($userId) {
                    $m = $b->memberFor($userId);
                    $b->setAttribute('role', $m?->role ?? 'staff');
                    $b->setAttribute('can_view_reports', (bool) ($m?->can_view_reports ?? false));
                    return $b;
                });
        });
        Route::post('businesses', function (\Illuminate\Http\Request $req) {
            $user = auth()->user();
            // Gratis hanya boleh 1 usaha; multi-usaha khusus Premium
            if (!$user->isPremium() && $user->businesses()->count() >= 1) {
                return response()->json([
                    'message' => 'Multi-usaha khusus Premium. Upgrade untuk menambah usaha.',
                    'premium_required' => true,
                ], 403);
            }
            $data = $req->validate(['name' => 'required|string', 'category' => 'nullable|string']);
            $business = auth()->user()->businesses()->create($data);
            $business->members()->create([
                'user_id' => auth()->id(), 'role' => 'owner', 'can_view_reports' => true,
            ]);
            $business->setAttribute('role', 'owner');
            $business->setAttribute('can_view_reports', true);
            return $business;
        });
        // Update nama usaha — owner saja
        Route::patch('businesses/{business}', function (\Illuminate\Http\Request $req, \App\Models\Business $business) {
            $m = $business->memberFor(auth()->id());
            abort_if(!$m || !$m->isOwner(), 403, 'Hanya pemilik yang bisa mengubah usaha.');
            $data = $req->validate(['name' => 'required|string|max:255', 'category' => 'nullable|string']);
            $business->update($data);
            return $business;
        });
        // Upload logo usaha — owner saja
        Route::post('businesses/{business}/logo', function (\Illuminate\Http\Request $req, \App\Models\Business $business) {
            $m = $business->memberFor(auth()->id());
            abort_if(!$m || !$m->isOwner(), 403, 'Hanya pemilik yang bisa mengubah usaha.');
            $req->validate(['photo' => 'required|image|max:4096']);
            if ($business->logo) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($business->logo);
            }
            $path = $req->file('photo')->store('logos', 'public');
            $business->update(['logo' => $path]);
            return ['logo_url' => asset('storage/' . $path)];
        });

        // QRIS — ambil setting (anggota boleh lihat)
        Route::get('businesses/{business}/qris', function (\Illuminate\Http\Request $req, \App\Models\Business $business) {
            abort_if(!$business->memberFor(auth()->id()), 403, 'Akses ditolak.');
            $q = $business->qrisSetting;
            if (!$q) return response()->json(null);
            return [
                'merchant_name' => $q->merchant_name,
                'image_url' => asset('storage/' . $q->image_path),
            ];
        });
        // QRIS — upload / ganti gambar QR (owner saja)
        Route::post('businesses/{business}/qris', function (\Illuminate\Http\Request $req, \App\Models\Business $business) {
            $m = $business->memberFor(auth()->id());
            abort_if(!$m || !$m->isOwner(), 403, 'Hanya pemilik yang bisa mengubah QRIS.');
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

        // ===== Kelola Karyawan (owner saja) =====
        Route::get('businesses/{business}/members', function (\App\Models\Business $business) {
            $m = $business->memberFor(auth()->id());
            abort_if(!$m || !$m->isOwner(), 403, 'Hanya pemilik yang bisa kelola karyawan.');
            return $business->members()->with('user:id,name,email,avatar')->orderBy('role')->get()
                ->map(fn($mem) => [
                    'user_id' => $mem->user_id,
                    'name' => $mem->user->name,
                    'email' => $mem->user->email,
                    'role' => $mem->role,
                    'can_view_reports' => (bool) $mem->can_view_reports,
                    'avatar_url' => $mem->user->avatar_url,
                ]);
        });
        Route::post('businesses/{business}/members', function (\Illuminate\Http\Request $req, \App\Models\Business $business) {
            $m = $business->memberFor(auth()->id());
            abort_if(!$m || !$m->isOwner(), 403, 'Hanya pemilik yang bisa kelola karyawan.');
            if (!auth()->user()->isPremium()) {
                return response()->json([
                    'message' => 'Kelola karyawan khusus Premium. Upgrade untuk menambah staff.',
                    'premium_required' => true,
                ], 403);
            }
            $data = $req->validate([
                'email' => 'required|email',
                'name' => 'nullable|string|max:255',
                'password' => 'nullable|string|min:6',
                'can_view_reports' => 'boolean',
            ]);
            $user = \App\Models\User::where('email', $data['email'])->first();
            if (!$user) {
                if (empty($data['name']) || empty($data['password'])) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'email' => ['Email belum terdaftar. Isi nama & password untuk membuatkan akun staff.'],
                    ]);
                }
                $user = \App\Models\User::create([
                    'name' => $data['name'],
                    'email' => $data['email'],
                    'password' => \Illuminate\Support\Facades\Hash::make($data['password']),
                ]);
            }
            if ($business->memberFor($user->id)) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'email' => ['Orang ini sudah jadi anggota usaha.'],
                ]);
            }
            $business->members()->create([
                'user_id' => $user->id,
                'role' => 'staff',
                'can_view_reports' => $data['can_view_reports'] ?? false,
            ]);
            return response()->json(['message' => 'Staff ditambahkan.'], 201);
        });
        Route::patch('businesses/{business}/members/{userId}', function (\Illuminate\Http\Request $req, \App\Models\Business $business, $userId) {
            $m = $business->memberFor(auth()->id());
            abort_if(!$m || !$m->isOwner(), 403, 'Hanya pemilik yang bisa kelola karyawan.');
            $mem = $business->members()->where('user_id', $userId)->where('role', 'staff')->firstOrFail();
            $data = $req->validate(['can_view_reports' => 'required|boolean']);
            $mem->update(['can_view_reports' => $data['can_view_reports']]);
            return response()->json(['message' => 'Akses staff diperbarui.']);
        });
        Route::delete('businesses/{business}/members/{userId}', function (\App\Models\Business $business, $userId) {
            $m = $business->memberFor(auth()->id());
            abort_if(!$m || !$m->isOwner(), 403, 'Hanya pemilik yang bisa kelola karyawan.');
            $business->members()->where('user_id', $userId)->where('role', 'staff')->delete();
            return response()->json(['message' => 'Staff dihapus dari usaha.']);
        });

        // Products
        Route::prefix('businesses/{business}')->group(function () {
            Route::get('products', [ProductController::class, 'index']);
            Route::post('products', [ProductController::class, 'store']);
            Route::post('products/import', [ProductController::class, 'import']);
            Route::post('products/{product}/photo', [ProductController::class, 'uploadPhoto']);
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
