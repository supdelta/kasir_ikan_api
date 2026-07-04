<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    private function ensureAdmin(Request $request): void
    {
        abort_unless($request->user()->is_super_admin, 403, 'Khusus Super Admin.');
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
}
