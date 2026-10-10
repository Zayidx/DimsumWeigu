<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate(['email' => ['required', 'email:rfc'], 'password' => ['required', 'string'], 'device_name' => ['required', 'string', 'max:100']]);
        $key = Str::lower($data['email']).'|'.$request->ip();
        abort_if(RateLimiter::tooManyAttempts('api-login:'.$key, 8), 429);

        $user = User::query()->where('email', $data['email'])->where('is_active', true)->first();
        if (! $user || ! Hash::check($data['password'], $user->password)) {
            RateLimiter::hit('api-login:'.$key, 60);

            return response()->json(['message' => __('Email atau kata sandi tidak sesuai.')], 422);
        }

        RateLimiter::clear('api-login:'.$key);
        $user->forceFill(['last_login_at' => now()])->save();
        $abilities = $user->getAllPermissions()->pluck('name')->push('user:read')->unique()->values()->all();
        $token = $user->createToken($data['device_name'], $abilities);

        return response()->json(['token' => $token->plainTextToken, 'token_type' => 'Bearer', 'user' => $user->only(['id', 'name', 'email', 'locale'])]);
    }

    public function register(Request $request): JsonResponse
    {
        abort_unless((bool) config('auth.public_registration'), 404);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'], 'email' => ['required', 'email:rfc', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', PasswordRule::min(12)->letters()->mixedCase()->numbers()->symbols()],
            'device_name' => ['required', 'string', 'max:100'],
        ]);
        $key = Str::lower($data['email']).'|'.$request->ip();
        abort_if(RateLimiter::tooManyAttempts('api-register:'.$key, 4), 429);
        RateLimiter::hit('api-register:'.$key, 3600);

        $user = User::query()->create(['name' => $data['name'], 'email' => $data['email'], 'password' => Hash::make($data['password']), 'locale' => 'id', 'is_active' => true]);
        $user->assignRole('customer');
        $token = $user->createToken($data['device_name'], ['user:read']);

        return response()->json(['token' => $token->plainTextToken, 'token_type' => 'Bearer', 'user' => $user->only(['id', 'name', 'email', 'locale'])], 201);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['user' => $request->user()->only(['id', 'name', 'email', 'phone', 'locale', 'is_active'])]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json(['message' => __('Token berhasil dicabut.')]);
    }
}
