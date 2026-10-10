<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

class RegisteredUserController extends Controller
{
    public function create(Request $request): View
    {
        $request->session()->put('auth.register.started_at', now()->timestamp);
        $request->session()->put('auth.register.nonce', Str::random(48));

        return view('auth.register', ['nonce' => $request->session()->get('auth.register.nonce')]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email:rfc', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', PasswordRule::min(12)->letters()->mixedCase()->numbers()->symbols()],
            'website' => ['nullable', 'string', 'max:0'],
            'form_nonce' => ['required', 'string', 'size:48'],
        ]);

        $startedAt = (int) $request->session()->get('auth.register.started_at', 0);
        $nonce = (string) $request->session()->pull('auth.register.nonce', '');
        abort_unless($startedAt > 0 && now()->timestamp - $startedAt >= 2 && $nonce !== '' && hash_equals($nonce, $data['form_nonce']), 422);

        $key = Str::lower($data['email']).'|'.$request->ip();
        abort_if(RateLimiter::tooManyAttempts('register:'.$key, 4), 429);
        RateLimiter::hit('register:'.$key, 3600);

        $user = User::query()->create(['name' => $data['name'], 'email' => $data['email'], 'password' => Hash::make($data['password']), 'locale' => 'id', 'is_active' => true]);
        Role::query()->where('name', 'customer')->where('guard_name', 'web')->firstOrCreate(['name' => 'customer', 'guard_name' => 'web']);
        $user->assignRole('customer');
        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->put('auth.authenticated_at', now()->timestamp);

        return redirect()->route('profile.edit')->with('status', __('Akun berhasil dibuat.'));
    }
}
