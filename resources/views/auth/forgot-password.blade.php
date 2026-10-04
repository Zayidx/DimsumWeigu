<x-layouts.auth title="Reset kata sandi">
    <div class="row h-100">
        <div class="col-lg-5 col-12"><div id="auth-left">
            <div class="auth-logo"><a href="{{ route('home') }}"><img src="{{ asset('assets/logo/logo-no-background.png') }}" alt="Weigu Dimsum & Gyoza"></a></div>
            <h1 class="auth-title">Reset kata sandi</h1>
            <p class="auth-subtitle mb-5">Masukkan email akun. Jika terdaftar, kami akan mengirim tautan reset.</p>
            @if (session('status'))<div class="alert alert-success" role="status">{{ session('status') }}</div>@endif
            <form method="POST" action="{{ route('password.email') }}">
                @csrf
                <input type="hidden" name="form_nonce" value="{{ session('auth.form.nonce') }}">
                <label class="visually-hidden" for="website">Jangan isi kolom ini</label><input id="website" name="website" class="position-absolute opacity-0" style="left:-10000px" tabindex="-1" autocomplete="off">
                <label class="visually-hidden" for="email">Email akun</label>
                <div class="form-group position-relative has-icon-left mb-4"><input id="email" type="email" name="email" value="{{ old('email') }}" class="form-control form-control-xl" placeholder="Email" autocomplete="email" required><div class="form-control-icon"><i class="bi bi-envelope"></i></div></div>
                @error('email')<div class="text-danger mb-3">{{ $message }}</div>@enderror
                <button class="btn btn-primary btn-block btn-lg shadow-lg mt-5">Kirim tautan reset</button>
            </form>
            <p class="text-center mt-5"><a class="font-bold" href="{{ route('login') }}">Kembali ke masuk</a></p>
        </div></div>
        <div class="col-lg-7 d-none d-lg-block"><div id="auth-right"></div></div>
    </div>
</x-layouts.auth>
