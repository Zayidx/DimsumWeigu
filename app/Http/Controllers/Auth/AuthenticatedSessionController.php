<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    public function create(Request $request): View
    {
        $request->session()->put('auth.login.started_at', now()->timestamp);
        $request->session()->put('auth.login.nonce', Str::random(48));

        return view('auth.login', ['nonce' => $request->session()->get('auth.login.nonce')]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email:rfc', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
            'remember' => ['nullable', 'boolean'],
            'website' => ['nullable', 'string', 'max:0'],
            'form_nonce' => ['required', 'string', 'size:48'],
        ]);

        $startedAt = (int) $request->session()->get('auth.login.started_at', 0);
        $expectedNonce = (string) $request->session()->pull('auth.login.nonce', '');
        $key = Str::lower($data['email']).'|'.$request->ip();
        $loginFormValid = $startedAt > 0 && now()->timestamp - $startedAt >= 2 && $expectedNonce !== '' && hash_equals($expectedNonce, $data['form_nonce']);

        if (! $loginFormValid) {
            RateLimiter::hit('login:'.$key, 60);

            return $this->failedLogin($request);
        }

        if (RateLimiter::tooManyAttempts('login:'.$key, 8)) {
            throw ValidationException::withMessages(['email' => __('Terlalu banyak percobaan. Coba lagi dalam :seconds detik.', ['seconds' => RateLimiter::availableIn('login:'.$key)])]);
        }

        if (! Auth::attempt(['email' => $data['email'], 'password' => $data['password'], 'is_active' => true], $request->boolean('remember'))) {
            RateLimiter::hit('login:'.$key, 60);

            return $this->failedLogin($request);
        }

        RateLimiter::clear('login:'.$key);
        $request->session()->regenerate();
        $request->session()->put('auth.authenticated_at', now()->timestamp);
        $request->session()->forget('auth.login.started_at');

        $user = $request->user();
        $user->forceFill(['last_login_at' => now()])->save();
        $request->session()->put('locale', $user->locale ?: 'id');

        $defaultOutletId = $user->outlets()->wherePivot('is_default', true)->value('outlets.id');
        if ($defaultOutletId) {
            $request->session()->put('active_outlet_id', $defaultOutletId);
        }

        return redirect()->intended(route('dashboard'));
    }

    private function failedLogin(Request $request): RedirectResponse
    {
        $request->session()->put('auth.login.started_at', now()->timestamp);
        $request->session()->put('auth.login.nonce', Str::random(48));

        throw ValidationException::withMessages(['email' => __('Email atau kata sandi tidak sesuai.')]);
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
