<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|unique:users,email',
            'phone' => 'nullable|string|unique:users,phone',
            'password' => 'required|string|min:6',
            'business_name' => 'required|string|max:255',
        ]);

        if (empty($data['email']) && empty($data['phone'])) {
            return response()->json(['message' => 'Email atau nomor HP wajib diisi.'], 422);
        }

        $user = User::create([
            'name'         => $data['name'],
            'email'        => $data['email'] ?? null,
            'phone'        => $data['phone'] ?? null,
            'password'     => Hash::make($data['password']),
            'password_set' => true,
        ]);

        $business = Business::create([
            'user_id' => $user->id,
            'name' => $data['business_name'],
        ]);
        $business->members()->create([
            'user_id' => $user->id, 'role' => 'owner', 'can_view_reports' => true, 'can_view_piutang' => true, 'can_view_hutang' => true,
        ]);
        $business->seedDefaultAccounts();

        $token = $user->createToken('mobile')->plainTextToken;

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
                'has_password'   => (bool) $user->password_set,
            ],
            'business' => [
                'id' => $business->id,
                'name' => $business->name,
                'logo_url' => $business->logo ? asset('storage/' . $business->logo) : null,
            ],
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'login' => 'required|string',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $request->login)
            ->orWhere('phone', $request->login)
            ->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'login' => ['Email/HP atau password salah.'],
            ]);
        }

        $token = $user->createToken('mobile')->plainTextToken;
        // Usaha yang bisa diakses (owner atau staff)
        $business = Business::whereHas('members', fn($q) => $q->where('user_id', $user->id))->first();

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
                'has_password'   => (bool) $user->password_set,
            ],
            'business' => $business ? [
                'id' => $business->id,
                'name' => $business->name,
                'logo_url' => $business->logo ? asset('storage/' . $business->logo) : null,
            ] : null,
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Berhasil keluar.']);
    }

    public function google(Request $request): JsonResponse
    {
        $request->validate(['id_token' => 'required|string']);

        $webClientId = '669597588318-qv3ifilnvckhjvl1rnud87omedovg8os.apps.googleusercontent.com';

        // Verifikasi id_token ke Google
        $resp = Http::get('https://oauth2.googleapis.com/tokeninfo', [
            'id_token' => $request->id_token,
        ]);
        if ($resp->failed()) {
            throw ValidationException::withMessages(['id_token' => ['Token Google tidak valid.']]);
        }
        $p = $resp->json();

        // Pastikan token memang untuk aplikasi kita & dari Google
        if (($p['aud'] ?? null) !== $webClientId) {
            throw ValidationException::withMessages(['id_token' => ['Token Google tidak cocok dengan aplikasi.']]);
        }
        if (!in_array($p['iss'] ?? '', ['accounts.google.com', 'https://accounts.google.com'])) {
            throw ValidationException::withMessages(['id_token' => ['Sumber token tidak valid.']]);
        }

        $email = $p['email'] ?? null;
        if (!$email) {
            throw ValidationException::withMessages(['id_token' => ['Email tidak tersedia dari Google.']]);
        }

        $user = User::firstOrCreate(
            ['email' => $email],
            [
                'name'         => $p['name'] ?? explode('@', $email)[0],
                'password'     => Hash::make(Str::random(40)),
                'password_set' => false,
            ]
        );

        // Simpan foto Google jika user belum punya avatar
        if (empty($user->avatar) && !empty($p['picture'])) {
            $user->update(['avatar' => $p['picture']]);
        }

        // Pastikan minimal punya 1 usaha (owner) atau usaha yang diikuti (staff)
        $business = Business::whereHas('members', fn($q) => $q->where('user_id', $user->id))->first();
        if (!$business) {
            $business = $user->businesses()->create(['name' => $p['name'] ?? 'Usaha Saya']);
            $business->members()->create([
                'user_id' => $user->id, 'role' => 'owner', 'can_view_reports' => true, 'can_view_piutang' => true, 'can_view_hutang' => true,
            ]);
            $business->seedDefaultAccounts();
        }

        $token = $user->createToken('mobile')->plainTextToken;

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
                'has_password'   => (bool) $user->password_set,
            ],
            'business' => [
                'id' => $business->id,
                'name' => $business->name,
                'logo_url' => $business->logo ? asset('storage/' . $business->logo) : null,
            ],
        ]);
    }

    public function changePassword(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'current_password' => 'nullable|string',
            'password'         => 'required|string|min:6|confirmed',
        ]);

        // Kalau sudah punya password sendiri, wajib verifikasi password lama
        if ($user->password_set && !empty($data['current_password'])) {
            if (!Hash::check($data['current_password'], $user->password)) {
                throw ValidationException::withMessages([
                    'current_password' => ['Password lama salah.'],
                ]);
            }
        } elseif ($user->password_set && empty($data['current_password'])) {
            throw ValidationException::withMessages([
                'current_password' => ['Masukkan password lama kamu.'],
            ]);
        }

        $user->update([
            'password'     => Hash::make($data['password']),
            'password_set' => true,
        ]);

        return response()->json(['message' => 'Password berhasil diubah.']);
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate(['email' => 'required|email']);

        $user = User::where('email', $request->email)->first();
        if ($user) {
            $code = (string) random_int(100000, 999999);
            DB::table('password_reset_tokens')->updateOrInsert(
                ['email' => $user->email],
                ['token' => Hash::make($code), 'created_at' => now()]
            );

            Mail::raw(
                "Kode reset password Delta Pos kamu: {$code}\n\n"
                    . "Kode berlaku 15 menit. Abaikan email ini jika kamu tidak meminta reset password.",
                function ($m) use ($user) {
                    $m->to($user->email)
                      ->from('pos@deltasoft.id', 'Delta Pos')
                      ->subject('Kode Reset Password - Delta Pos');
                }
            );
        }

        // Selalu balas sukses supaya tidak membocorkan apakah email terdaftar.
        return response()->json([
            'message' => 'Jika email terdaftar, kode reset sudah dikirim ke email kamu.',
        ]);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => 'required|email',
            'code' => 'required|string',
            'password' => 'required|string|min:6',
        ]);

        $row = DB::table('password_reset_tokens')->where('email', $data['email'])->first();

        if (!$row || !Hash::check($data['code'], $row->token)) {
            throw ValidationException::withMessages(['code' => ['Kode salah.']]);
        }

        if (now()->diffInMinutes($row->created_at) > 15) {
            throw ValidationException::withMessages(['code' => ['Kode kadaluarsa. Minta kode baru.']]);
        }

        $user = User::where('email', $data['email'])->first();
        if (!$user) {
            throw ValidationException::withMessages(['email' => ['Email tidak ditemukan.']]);
        }

        $user->update(['password' => Hash::make($data['password'])]);
        DB::table('password_reset_tokens')->where('email', $data['email'])->delete();

        return response()->json(['message' => 'Password berhasil diubah. Silakan login.']);
    }

    // Super admin: generate kode reset untuk user tertentu
    public function adminGenerateResetCode(Request $request, int $userId): JsonResponse
    {
        $admin = $request->user();
        if (!$admin || !$admin->is_super_admin) {
            return response()->json(['message' => 'Akses ditolak.'], 403);
        }

        $user = User::findOrFail($userId);
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $user->update([
            'reset_code' => $code,
            'reset_code_expires_at' => now()->addMinutes(15),
        ]);

        return response()->json([
            'code' => $code,
            'user_name' => $user->name,
            'expires_at' => now()->addHours(24)->toDateTimeString(),
        ]);
    }

    // User: reset password pakai kode dari admin
    public function resetPasswordWithCode(Request $request): JsonResponse
    {
        $data = $request->validate([
            'phone' => 'required|string',
            'code' => 'required|string|size:6',
            'password' => 'required|string|min:6',
        ]);

        $user = User::where('phone', $data['phone'])->first();
        if (!$user) {
            throw ValidationException::withMessages(['phone' => ['Nomor HP tidak ditemukan.']]);
        }

        if ($user->reset_code !== $data['code']) {
            throw ValidationException::withMessages(['code' => ['Kode salah.']]);
        }

        if (!$user->reset_code_expires_at || now()->isAfter($user->reset_code_expires_at)) {
            throw ValidationException::withMessages(['code' => ['Kode sudah kadaluarsa.']]);
        }

        $user->update([
            'password' => Hash::make($data['password']),
            'reset_code' => null,
            'reset_code_expires_at' => null,
        ]);

        return response()->json(['message' => 'Password berhasil diubah. Silakan login.']);
    }
}
