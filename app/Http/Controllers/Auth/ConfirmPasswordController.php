<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ConfirmPasswordController extends Controller
{
    public function create(): View
    {
        return view('auth.confirm-password');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate(['password' => ['required', 'string']]);
        if (! Hash::check($request->string('password')->toString(), $request->user()->password)) {
            throw ValidationException::withMessages(['password' => __('Kata sandi tidak sesuai.')]);
        }
        $request->session()->put('auth.password_confirmed_at', now()->unix());
        return redirect()->intended(route('dashboard'));
    }
}
