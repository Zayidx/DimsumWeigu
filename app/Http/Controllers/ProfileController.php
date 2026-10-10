<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        $sessions = collect();
        if (config('session.driver') === 'database') {
            $sessions = \DB::table(config('session.table', 'sessions'))->where('user_id', $request->user()->id)
                ->orderByDesc('last_activity')->get(['id', 'ip_address', 'user_agent', 'last_activity'])
                ->map(fn ($session) => (object) [...(array) $session, 'is_current' => hash_equals($request->session()->getId(), $session->id)]);
        } else {
            $sessions = collect([(object) ['id' => $request->session()->getId(), 'ip_address' => $request->ip(), 'user_agent' => $request->userAgent(), 'last_activity' => now()->timestamp, 'is_current' => true]]);
        }

        return view('profile.edit', compact('sessions'));
    }

    public function update(Request $request, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:120'], 'phone' => ['nullable', 'string', 'max:32'], 'locale' => ['required', 'in:id,en']]);
        $user = $request->user();
        $before = $user->only(['name', 'phone', 'locale']);
        $user->fill($data)->save();
        $audit->record('profile.updated', $user, $before, $user->only(['name', 'phone', 'locale']));

        return back()->with('status', __('Profil berhasil disimpan.'));
    }

    public function updatePassword(Request $request, AuditLogger $audit): RedirectResponse
    {
        $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', PasswordRule::min(12)->letters()->mixedCase()->numbers()->symbols()],
        ]);

        $user = $request->user();
        $user->forceFill(['password' => Hash::make($request->string('password')->toString()), 'remember_token' => Str::random(60)])->save();
        $user->tokens()->delete();
        if (config('session.driver') === 'database') {
            \DB::table(config('session.table', 'sessions'))->where('user_id', $user->id)->where('id', '!=', $request->session()->getId())->delete();
        }
        $request->session()->put('password_hash_web', $user->password);
        $audit->record('profile.password_changed', $user);
        $request->session()->regenerate();

        return redirect()->route('dashboard')->with('status', __('Kata sandi berhasil diperbarui.'));
    }

    public function updateAvatar(Request $request, AuditLogger $audit): RedirectResponse
    {
        $request->validate(['avatar' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048']]);
        $user = $request->user();
        $old = $user->avatar_path;
        $path = $request->file('avatar')->storePublicly('avatars/'.$user->id, 'public');
        $user->forceFill(['avatar_path' => $path])->save();
        if ($old) {
            Storage::disk('public')->delete($old);
        }
        $audit->record('profile.avatar_changed', $user, ['avatar_path' => $old], ['avatar_path' => $path]);

        return back()->with('status', __('Foto profil berhasil diperbarui.'));
    }

    public function destroy(Request $request, AuditLogger $audit): RedirectResponse
    {
        $request->validate(['password' => ['required', 'current_password'], 'confirmation' => ['required', 'in:HAPUS']]);
        $user = $request->user();
        abort_if($user->hasRole('super-admin') && User::role('super-admin')->whereKeyNot($user->id)->where('is_active', true)->doesntExist(), 422, __('Super-admin terakhir tidak dapat menghapus akun.'));

        $audit->record('profile.account_deactivated', $user, $user->only(['name', 'email', 'phone', 'avatar_path']), ['is_active' => false]);
        if ($user->avatar_path) {
            Storage::disk('public')->delete($user->avatar_path);
        }
        $user->outlets()->detach();
        $user->tokens()->delete();
        $user->forceFill([
            'name' => 'Akun dinonaktifkan', 'email' => 'deleted+'.Str::uuid().'@invalid.local',
            'phone' => null, 'avatar_path' => null, 'is_active' => false,
            'password' => Hash::make(Str::random(64)),
        ])->save();
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home')->with('status', __('Akun dinonaktifkan dan data profil dihapus.'));
    }

    public function destroySession(Request $request, string $sessionId): RedirectResponse
    {
        abort_unless(config('session.driver') === 'database', 422, __('Pencabutan sesi per perangkat memerlukan SESSION_DRIVER=database.'));
        abort_if(hash_equals($request->session()->getId(), $sessionId), 422, __('Gunakan tombol keluar untuk mengakhiri sesi ini.'));

        $deleted = \DB::table(config('session.table', 'sessions'))->where('id', $sessionId)->where('user_id', $request->user()->id)->delete();
        abort_unless($deleted === 1, 404);

        return back()->with('status', __('Sesi perangkat berhasil dicabut.'));
    }
}
