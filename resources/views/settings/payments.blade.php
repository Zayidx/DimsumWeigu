@extends('layouts.dashboard')
@section('page-title','Pembayaran')
@section('content')
    @if(session('status'))<div class="alert alert-light-success">{{ session('status') }}</div>@endif
    <div class="alert alert-light-warning">Kredensial dienkripsi saat disimpan dan tidak pernah ditampilkan kembali. Kolom kosong mempertahankan nilai yang sudah tersimpan. Aktifkan provider hanya setelah webhook resmi dikonfigurasi.</div>
    <form method="POST" action="{{ route('settings.payments.update') }}" autocomplete="off">
        @csrf
        @foreach(['midtrans'=>['server_key'=>'Server key','client_key'=>'Client key'],'xendit'=>['secret_key'=>'Secret key','public_key'=>'Public key','webhook_token'=>'Webhook token']] as $provider=>$keys)
            @php($row=$providers[$provider])
            <div class="card mb-3"><div class="card-header d-flex justify-content-between align-items-center"><div><h5 class="card-title mb-0">{{ str($provider)->headline() }}</h5><small class="text-muted">Kredensial tersedia: {{ $row['credentials_present'] ? implode(', ',$row['credentials_present']) : 'belum ada' }}</small></div><label class="form-check form-switch mb-0"><input type="checkbox" name="providers[{{ $provider }}][is_enabled]" value="1" class="form-check-input" @checked(old("providers.$provider.is_enabled",$row['is_enabled']))><span class="form-check-label">Aktif</span></label></div><div class="card-body"><div class="row g-3"><div class="col-md-4"><label class="form-label" for="{{ $provider }}-mode">Mode</label><select id="{{ $provider }}-mode" name="providers[{{ $provider }}][mode]" class="form-select"><option value="sandbox" @selected(old("providers.$provider.mode",$row['mode'])==='sandbox')>Sandbox</option><option value="production" @selected(old("providers.$provider.mode",$row['mode'])==='production')>Production</option></select></div>@foreach($keys as $key=>$label)<div class="col-md-4"><label class="form-label" for="{{ $provider }}-{{ $key }}">{{ $label }}</label><input id="{{ $provider }}-{{ $key }}" type="password" name="providers[{{ $provider }}][credentials][{{ $key }}]" class="form-control" autocomplete="new-password" placeholder="{{ in_array($key,$row['credentials_present'],true)?'Tersimpan; isi untuk mengganti':'Masukkan '.$label }}"><small class="text-muted">Tidak ditampilkan setelah simpan.</small>@error("providers.$provider.credentials.$key")<small class="text-danger d-block">{{ $message }}</small>@enderror</div>@endforeach</div>@error("providers.$provider.is_enabled")<div class="text-danger small mt-3">{{ $message }}</div>@enderror</div></div>
        @endforeach
        <div class="text-end"><button class="btn btn-primary">Simpan pengaturan</button></div>
    </form>
@endsection
