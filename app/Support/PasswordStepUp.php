<?php

namespace App\Support;

class PasswordStepUp
{
    public static function requireRecent(): void
    {
        if (request()->attributes->get('api_password_confirmed_user_id') === auth()->id() && auth()->id() !== null) {
            return;
        }
        $confirmed = (int) session('auth.password_confirmed_at', 0);
        abort_if($confirmed <= 0 || now()->timestamp - $confirmed > (int) config('auth.password_timeout', 10800), 423, __('Konfirmasi kata sandi diperlukan. Muat ulang halaman untuk mengonfirmasi.'));
    }
}
