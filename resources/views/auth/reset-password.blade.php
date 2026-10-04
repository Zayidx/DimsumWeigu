<x-layouts.auth title="Buat kata sandi baru">
    <div class="row h-100">
        <div class="col-lg-5 col-12"><div id="auth-left">
            <div class="auth-logo"><a href="{{ route('home') }}"><img src="{{ asset('assets/logo/logo-no-background.png') }}" alt="Weigu Dimsum & Gyoza"></a></div>
            <h1 class="auth-title">Buat kata sandi baru</h1>
            <p class="auth-subtitle mb-5">Gunakan minimal 12 karakter dengan huruf besar, angka, dan simbol.</p>
            <form method="POST" action="{{ route('password.update') }}">
                @csrf
                <input type="hidden" name="token" value="{{ $token }}"><input type="hidden" name="form_nonce" value="{{ session('auth.form.nonce') }}">
                <label class="visually-hidden" for="website">Jangan isi kolom ini</label><input id="website" name="website" class="position-absolute opacity-0" style="left:-10000px" tabindex="-1" autocomplete="off">
                <label class="visually-hidden" for="email">Email akun</label>
                <div class="form-group position-relative has-icon-left mb-4"><input id="email" type="email" name="email" value="{{ old('email', $email) }}" class="form-control form-control-xl" placeholder="Email" required autocomplete="email"><div class="form-control-icon"><i class="bi bi-envelope"></i></div></div>
                <label class="visually-hidden" for="password">Kata sandi baru</label>
                <div class="form-group position-relative has-icon-left mb-4"><input id="password" type="password" name="password" class="form-control form-control-xl" placeholder="Kata sandi baru" required autocomplete="new-password"><div class="form-control-icon"><i class="bi bi-shield-lock"></i></div></div>
                <label class="visually-hidden" for="password_confirmation">Ulangi kata sandi baru</label>
                <div class="form-group position-relative has-icon-left mb-4"><input id="password_confirmation" type="password" name="password_confirmation" class="form-control form-control-xl" placeholder="Ulangi kata sandi" required autocomplete="new-password"><div class="form-control-icon"><i class="bi bi-shield-check"></i></div></div>
                @if ($errors->any())<div class="text-danger mb-3">{{ $errors->first() }}</div>@endif
                <button class="btn btn-primary btn-block btn-lg shadow-lg mt-4">Simpan kata sandi</button>
            </form>
        </div></div><div class="col-lg-7 d-none d-lg-block"><div id="auth-right"></div></div>
    </div>
</x-layouts.auth>
