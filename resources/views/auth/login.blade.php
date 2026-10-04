<x-layouts.auth title="Masuk">
    <div class="row h-100">
        <div class="col-lg-5 col-12">
            <div id="auth-left">
                <div class="auth-logo"><a href="{{ route('home') }}"><img src="{{ asset('assets/logo/logo-no-background.png') }}" alt="Weigu Dimsum & Gyoza"></a></div>
                <h1 class="auth-title">Selamat datang</h1>
                <p class="auth-subtitle mb-5">Masuk untuk melanjutkan ke ruang kerja Dikasir.</p>
                @if (session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
                <form method="POST" action="{{ route('login.store') }}">
                    @csrf
                    <input type="hidden" name="form_nonce" value="{{ $nonce }}">
                    <label class="visually-hidden" for="website">Jangan isi kolom ini</label>
                    <input id="website" name="website" class="position-absolute opacity-0" style="left:-10000px" tabindex="-1" autocomplete="off" aria-hidden="true">
                    <div class="form-group position-relative has-icon-left mb-4">
                        <label class="visually-hidden" for="email">Email</label>
                        <input id="email" type="email" name="email" value="{{ old('email') }}" class="form-control form-control-xl @error('email') is-invalid @enderror" placeholder="Email" autocomplete="username" required autofocus>
                        <div class="form-control-icon"><i class="bi bi-envelope"></i></div>
                    </div>
                    <div class="form-group position-relative has-icon-left mb-4">
                        <label class="visually-hidden" for="password">Kata sandi</label>
                        <input id="password" type="password" name="password" class="form-control form-control-xl" placeholder="Kata sandi" autocomplete="current-password" required>
                        <div class="form-control-icon"><i class="bi bi-shield-lock"></i></div>
                    </div>
                    @error('email')<div class="text-danger mb-3" role="alert">{{ $message }}</div>@enderror
                    @error('password')<div class="text-danger mb-3" role="alert">{{ $message }}</div>@enderror
                    <div class="form-check form-check-lg d-flex align-items-end">
                        <input class="form-check-input me-2" type="checkbox" name="remember" value="1" id="remember">
                        <label class="form-check-label text-gray-600" for="remember">Ingat saya</label>
                    </div>
                    <button class="btn btn-primary btn-block btn-lg shadow-lg mt-5">Masuk</button>
                </form>
                <div class="text-center mt-5 text-lg fs-4">
                    <p><a class="font-bold" href="{{ route('password.request') }}">Lupa kata sandi?</a></p>
                    @if (config('auth.public_registration'))<p class="text-gray-600">Belum punya akun? <a class="font-bold" href="{{ route('register') }}">Daftar</a></p>@endif
                </div>
            </div>
        </div>
        <div class="col-lg-7 d-none d-lg-block"><div id="auth-right"></div></div>
    </div>
</x-layouts.auth>
