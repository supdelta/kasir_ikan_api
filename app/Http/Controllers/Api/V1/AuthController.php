<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
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
            'name' => $data['name'],
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'password' => Hash::make($data['password']),
        ]);

        $business = Business::create([
            'user_id' => $user->id,
            'name' => $data['business_name'],
        ]);

        $token = $user->createToken('mobile')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'avatar_url' => $user->avatar ? asset('storage/' . $user->avatar) : null,
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
        $business = $user->businesses()->first();

        return response()->json([
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'avatar_url' => $user->avatar ? asset('storage/' . $user->avatar) : null,
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
}
