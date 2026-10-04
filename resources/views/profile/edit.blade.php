@extends('layouts.dashboard')
@section('page-title', 'Profil saya')

@section('content')
    @if (session('status'))<div class="alert alert-light-success" role="status">{{ session('status') }}</div>@endif
    <div class="row g-4">
        <div class="col-12 col-xl-7"><div class="card"><div class="card-header"><h4 class="card-title">Data profil</h4></div><div class="card-body">
            <form method="POST" action="{{ route('profile.update') }}">@csrf @method('PATCH')
                <div class="form-group mb-3"><label for="name">Nama</label><input id="name" name="name" class="form-control" value="{{ old('name', auth()->user()->name) }}" required maxlength="120"></div>
                <div class="form-group mb-3"><label for="email">Email</label><input id="email" class="form-control" value="{{ auth()->user()->email }}" readonly><small class="text-muted">Perubahan email dilakukan administrator untuk menjaga identitas akun.</small></div>
                <div class="form-group mb-3"><label for="phone">Telepon</label><input id="phone" name="phone" class="form-control" value="{{ old('phone', auth()->user()->phone) }}" maxlength="32"></div>
                <div class="form-group mb-3"><label for="locale">Bahasa</label><select id="locale" name="locale" class="form-select"><option value="id" @selected(auth()->user()->locale === 'id')>Bahasa Indonesia</option><option value="en" @selected(auth()->user()->locale === 'en')>English</option></select></div>
                @error('name')<div class="text-danger">{{ $message }}</div>@enderror @error('phone')<div class="text-danger">{{ $message }}</div>@enderror @error('locale')<div class="text-danger">{{ $message }}</div>@enderror
                <button class="btn btn-primary mt-3">Simpan profil</button>
            </form>
            <hr class="my-4"><h5>Foto profil</h5>@if (auth()->user()->avatar_path)<img src="{{ asset('storage/'.auth()->user()->avatar_path) }}" width="72" height="72" class="rounded-circle object-fit-cover mb-3" alt="Foto profil">@endif
            <form method="POST" action="{{ route('profile.avatar') }}" enctype="multipart/form-data">@csrf<label for="avatar" class="form-label">Pilih foto profil</label><input id="avatar" type="file" name="avatar" class="form-control" accept="image/png,image/jpeg,image/webp" required><button class="btn btn-outline-primary mt-2">Unggah foto</button></form>
        </div></div></div>
        <div class="col-12 col-xl-5"><div class="card"><div class="card-header"><h4 class="card-title">Kata sandi</h4></div><div class="card-body"><form method="POST" action="{{ route('profile.password.update') }}">@csrf @method('PUT')
            <div class="form-group mb-3"><label for="current_password">Kata sandi saat ini</label><input id="current_password" type="password" name="current_password" class="form-control" autocomplete="current-password" required></div>
            <div class="form-group mb-3"><label for="password">Kata sandi baru</label><input id="password" type="password" name="password" class="form-control" autocomplete="new-password" required></div>
            <div class="form-group mb-3"><label for="password_confirmation">Ulangi kata sandi baru</label><input id="password_confirmation" type="password" name="password_confirmation" class="form-control" autocomplete="new-password" required></div>
            @if ($errors->any())<div class="text-danger mb-2">{{ $errors->first() }}</div>@endif<button class="btn btn-primary">Perbarui kata sandi</button>
        </form></div></div>
        <div class="card"><div class="card-header"><h4 class="card-title">Sesi perangkat</h4></div><div class="card-body"><p class="text-muted small">Keluar dari sesi yang tidak Anda kenali. Sesi ini tetap aktif.</p>
            @foreach ($sessions as $session)
                <div class="border rounded p-3 mb-2"><div class="fw-semibold">{{ $session->is_current ? 'Perangkat ini' : 'Perangkat lain' }}</div><div class="small text-muted">{{ $session->ip_address ?: 'IP tidak tersedia' }} · {{ \Illuminate\Support\Carbon::createFromTimestamp($session->last_activity)->diffForHumans() }}</div><div class="small text-truncate text-muted">{{ $session->user_agent }}</div>
                    @if (! $session->is_current && config('session.driver') === 'database')<form method="POST" action="{{ route('profile.sessions.destroy', $session->id) }}" class="mt-2">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">Cabut sesi ini</button></form>@endif
                </div>
            @endforeach
        </div></div>
        <div class="card border-danger"><div class="card-header"><h4 class="card-title text-danger">Nonaktifkan akun</h4></div><div class="card-body"><p class="small text-muted">Akun akan dinonaktifkan dan data profil pribadi dihapus dari akun. Catatan transaksi dan audit tetap mempertahankan referensi pelaku untuk integritas keuangan.</p><form method="POST" action="{{ route('profile.destroy') }}">@csrf @method('DELETE')<div class="form-group mb-2"><label for="delete_password">Kata sandi</label><input id="delete_password" type="password" name="password" class="form-control" autocomplete="current-password" required></div><div class="form-group mb-3"><label for="confirmation">Ketik HAPUS untuk melanjutkan</label><input id="confirmation" name="confirmation" class="form-control" required></div><button class="btn btn-danger">Nonaktifkan akun</button></form></div></div></div>
    </div>
@endsection
