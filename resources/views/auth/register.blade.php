<x-layouts.auth title="Buat akun">
    <div class="row h-100"><div class="col-lg-5 col-12"><div id="auth-left">
        <div class="auth-logo"><a href="{{ route('home') }}"><img src="{{ asset('assets/logo/logo-no-background.png') }}" alt="Weigu Dimsum & Gyoza"></a></div>
        <h1 class="auth-title">Buat akun</h1><p class="auth-subtitle mb-5">Buat akun pelanggan untuk mengakses profil Anda.</p>
        <form method="POST" action="{{ route('register.store') }}">@csrf
            <input type="hidden" name="form_nonce" value="{{ $nonce }}"><label class="visually-hidden" for="website">Jangan isi kolom ini</label><input id="website" name="website" class="position-absolute opacity-0" style="left:-10000px" tabindex="-1" autocomplete="off">
            <div class="form-group mb-4"><input name="name" value="{{ old('name') }}" class="form-control form-control-xl" placeholder="Nama" required autocomplete="name"></div>
            <div class="form-group mb-4"><input type="email" name="email" value="{{ old('email') }}" class="form-control form-control-xl" placeholder="Email" required autocomplete="email"></div>
            <div class="form-group mb-4"><input type="password" name="password" class="form-control form-control-xl" placeholder="Kata sandi" required autocomplete="new-password"></div>
            <div class="form-group mb-4"><input type="password" name="password_confirmation" class="form-control form-control-xl" placeholder="Ulangi kata sandi" required autocomplete="new-password"></div>
            @if ($errors->any())<div class="text-danger mb-3">{{ $errors->first() }}</div>@endif
            <button class="btn btn-primary btn-block btn-lg shadow-lg mt-4">Daftar</button>
        </form><p class="text-center mt-5"><a href="{{ route('login') }}">Sudah punya akun? Masuk</a></p>
    </div></div><div class="col-lg-7 d-none d-lg-block"><div id="auth-right"></div></div></div>
</x-layouts.auth>
