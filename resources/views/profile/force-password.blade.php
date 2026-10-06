<x-layouts.auth title="Perbarui kata sandi">
    <div class="row h-100"><div class="col-lg-5 col-12"><div id="auth-left">
        <div class="auth-logo"><a href="{{ route('home') }}"><img src="{{ asset('assets/logo/logo-no-background.png') }}" alt="Weigu Dimsum & Gyoza"></a></div>
        <h1 class="auth-title">Perbarui kata sandi</h1>
        <p class="auth-subtitle mb-5">Kata sandi awal harus diganti sebelum Anda membuka workspace.</p>
        <form method="POST" action="{{ route('profile.password.update') }}">@csrf @method('PUT')
            <div class="form-group mb-4"><label for="password_username" class="form-label">Email akun</label><input id="password_username" type="email" name="username" class="form-control form-control-xl" value="{{ auth()->user()->email }}" autocomplete="username" readonly></div>
            <div class="form-group position-relative has-icon-left mb-4"><input type="password" name="current_password" class="form-control form-control-xl" placeholder="Kata sandi saat ini" autocomplete="current-password" required><div class="form-control-icon"><i class="bi bi-shield-lock"></i></div></div>
            <div class="form-group position-relative has-icon-left mb-4"><input type="password" name="password" class="form-control form-control-xl" placeholder="Kata sandi baru" autocomplete="new-password" required><div class="form-control-icon"><i class="bi bi-shield-check"></i></div></div>
            <div class="form-group position-relative has-icon-left mb-4"><input type="password" name="password_confirmation" class="form-control form-control-xl" placeholder="Ulangi kata sandi baru" autocomplete="new-password" required><div class="form-control-icon"><i class="bi bi-shield-check"></i></div></div>
            @if ($errors->any())<div class="text-danger mb-3">{{ $errors->first() }}</div>@endif
            <button class="btn btn-primary btn-block btn-lg shadow-lg mt-4">Simpan dan lanjutkan</button>
        </form>
    </div></div><div class="col-lg-7 d-none d-lg-block"><div id="auth-right"></div></div></div>
</x-layouts.auth>
