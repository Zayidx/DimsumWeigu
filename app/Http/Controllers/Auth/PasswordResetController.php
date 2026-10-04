<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\View\View;

class PasswordResetController extends Controller
{
    public function create(Request $request): View
    {
        $this->primeForm($request);

        return view('auth.forgot-password');
    }

    public function send(Request $request): RedirectResponse
    {
        $this->validateForm($request, ['email' => ['required', 'email:rfc', 'max:255']]);
        $status = Password::sendResetLink(['email' => $request->string('email')->toString()]);

        return back()->with('status', __('Jika alamat terdaftar, instruksi reset kata sandi akan dikirim.'));
    }

    public function edit(Request $request, string $token): View
    {
        $this->primeForm($request);

        return view('auth.reset-password', ['token' => $token, 'email' => $request->query('email', '')]);
    }

    public function update(Request $request): RedirectResponse
    {
        $this->validateForm($request, [
            'token' => ['required', 'string'],
            'email' => ['required', 'email:rfc'],
            'password' => ['required', 'confirmed', PasswordRule::min(12)->letters()->mixedCase()->numbers()->symbols()],
        ]);

        $status = Password::reset($request->only('email', 'password', 'password_confirmation', 'token'), function (User $user, string $password) {
            $user->forceFill(['password' => Hash::make($password), 'password_changed_at' => now(), 'must_change_password' => false, 'remember_token' => Str::random(60)])->save();
            $user->tokens()->delete();
            if (config('session.driver') === 'database') {
                DB::table(config('session.table', 'sessions'))->where('user_id', $user->id)->delete();
            }
            event(new PasswordReset($user));
        });

        return $status === Password::PasswordReset
            ? redirect()->route('login')->with('status', __('Kata sandi berhasil diperbarui.'))
            : back()->withErrors(['email' => __($status)])->withInput($request->only('email'));
    }

    private function primeForm(Request $request): void
    {
        $request->session()->put('auth.form.started_at', now()->timestamp);
        $request->session()->put('auth.form.nonce', Str::random(48));
    }

    private function validateForm(Request $request, array $fields): void
    {
        $request->validate($fields + [
            'website' => ['nullable', 'string', 'max:0'],
            'form_nonce' => ['required', 'string', 'size:48'],
        ]);

        $startedAt = (int) $request->session()->get('auth.form.started_at', 0);
        $nonce = (string) $request->session()->pull('auth.form.nonce', '');
        abort_unless($startedAt > 0 && now()->timestamp - $startedAt >= 2 && $nonce !== '' && hash_equals($nonce, $request->string('form_nonce')->toString()), 422);
    }
}
