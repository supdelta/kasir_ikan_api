<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\BusinessMember;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminController extends Controller
{
    private function ensureAdmin(Request $request): void
    {
        abort_unless($request->user()->is_super_admin, 403, 'Khusus Super Admin.');
    }

    public function stats(Request $request): JsonResponse
    {
        $this->ensureAdmin($request);
        $now = now();

        return response()->json([
            'total_users' => User::count(),
            'premium_users' => User::where('premium_until', '>', $now)->count(),
            'free_users' => User::where(function ($q) use ($now) {
                $q->whereNull('premium_until')->orWhere('premium_until', '<=', $now);
            })->count(),
            'expiring_soon' => User::whereBetween('premium_until', [$now, $now->copy()->addDays(7)])->count(),
            'new_today' => User::whereDate('created_at', today())->count(),
            'new_week' => User::where('created_at', '>=', $now->copy()->subDays(7))->count(),
            'total_businesses' => \App\Models\Business::count(),
        ]);
    }

    public function users(Request $request): JsonResponse
    {
        $this->ensureAdmin($request);

        $q = User::query()->orderByDesc('created_at');
        if ($s = $request->get('search')) {
            $q->where(function ($qq) use ($s) {
                $qq->where('email', 'like', "%$s%")
                    ->orWhere('name', 'like', "%$s%")
                    ->orWhere('phone', 'like', "%$s%");
            });
        }

        return response()->json(
            $q->limit(50)->get()->map(fn($u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'phone' => $u->phone,
                'is_premium' => $u->isPremium(),
                'premium_until' => $u->premium_until,
                'is_super_admin' => (bool) $u->is_super_admin,
                'created_at' => $u->created_at,
            ])
        );
    }

    /// Masuk ke akun user manapun (impersonate) — khusus developer/super admin.
    /// Mengembalikan token & data user seperti login biasa.
    public function impersonate(Request $request, User $user): JsonResponse
    {
        $this->ensureAdmin($request);

        $token = $user->createToken('impersonate')->plainTextToken;
        $business = \App\Models\Business::whereHas('members', fn($q) => $q->where('user_id', $user->id))->first();

        return response()->json([
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'avatar_url' => $user->avatar_url,
                'is_premium' => $user->isPremium(),
                'premium_until' => $user->premium_until,
                'is_super_admin' => (bool) $user->is_super_admin,
                'has_password' => (bool) $user->password_set,
            ],
            'business' => $business ? [
                'id' => $business->id,
                'name' => $business->name,
                'logo_url' => $business->logo ? asset('storage/' . $business->logo) : null,
            ] : null,
        ]);
    }

    /// Kirim pengumuman (notifikasi in-app) ke semua user sekaligus.
    public function broadcast(Request $request): JsonResponse
    {
        $this->ensureAdmin($request);
        $data = $request->validate([
            'title' => 'required|string|max:120',
            'body'  => 'required|string|max:500',
        ]);

        $now = now();
        $rows = User::pluck('id')->map(fn($id) => [
            'user_id'    => $id,
            'type'       => 'broadcast',
            'title'      => $data['title'],
            'body'       => $data['body'],
            'read_at'    => null,
            'created_at' => $now,
            'updated_at' => $now,
        ])->all();

        foreach (array_chunk($rows, 500) as $chunk) {
            \App\Models\AppNotification::insert($chunk);
        }

        return response()->json([
            'message' => 'Pengumuman terkirim ke ' . count($rows) . ' user.',
            'count'   => count($rows),
        ]);
    }

    public function grantPremium(Request $request, User $user): JsonResponse
    {
        $this->ensureAdmin($request);
        $days = (int) $request->validate(['days' => 'required|integer|min:1|max:3650'])['days'];

        $base = ($user->premium_until && $user->premium_until->isFuture())
            ? $user->premium_until
            : now();
        $user->premium_until = $base->copy()->addDays($days);
        $user->save();

        return response()->json([
            'message' => "Premium aktif sampai {$user->premium_until->format('d M Y')}",
            'premium_until' => $user->premium_until,
        ]);
    }

    public function revokePremium(Request $request, User $user): JsonResponse
    {
        $this->ensureAdmin($request);
        $user->premium_until = null;
        $user->save();

        return response()->json(['message' => 'Premium dicabut.']);
    }

    public function deleteUser(Request $request, User $user): JsonResponse
    {
        $this->ensureAdmin($request);

        if ($user->id === $request->user()->id) {
            return response()->json(['message' => 'Tidak bisa menghapus akun sendiri.'], 422);
        }
        if ($user->is_super_admin) {
            return response()->json(['message' => 'Tidak bisa menghapus sesama Super Admin.'], 422);
        }

        DB::transaction(function () use ($user) {
            // Hapus usaha milik user -> cascade ke produk/transaksi/piutang/qris/anggota
            $user->businesses()->get()->each(fn($b) => $b->delete());
            // Bersihkan keanggotaan staff di usaha lain
            BusinessMember::where('user_id', $user->id)->delete();
            // Cabut token login
            $user->tokens()->delete();
            // Hapus user (transaksi di usaha lain -> user_id jadi null)
            $user->delete();
        });

        return response()->json(['message' => 'User dan semua datanya dihapus.']);
    }

    public function clearUserData(Request $request, User $user): JsonResponse
    {
        $this->ensureAdmin($request);

        if ($user->id === $request->user()->id) {
            return response()->json(['message' => 'Tidak bisa hapus data akun sendiri.'], 422);
        }

        DB::transaction(function () use ($user) {
            foreach ($user->businesses as $business) {
                $business->transactions()->delete();
                $business->receivables()->delete();
                $business->payables()->delete();
                $business->products()->delete();
                $business->customers()->delete();
                $business->suppliers()->delete();
            }
        });

        return response()->json(['message' => 'Data transaksi & stok dihapus. Akun & usaha tetap ada.']);
    }
}
