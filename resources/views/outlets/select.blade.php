@extends('layouts.dashboard')
@section('page-title', 'Pilih outlet')

@section('content')
    <div class="row justify-content-center"><div class="col-12 col-xl-7"><div class="card"><div class="card-header"><h4 class="card-title">Pilih outlet kerja</h4></div><div class="card-body">
        <p class="text-muted">Pilih outlet yang diberikan kepada akun Anda. Aktivitas akan mengikuti gudang dan shift outlet ini.</p>
        @if ($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
        @forelse ($outlets as $outlet)
            <form method="POST" action="{{ route('outlet.switch') }}" class="border rounded p-3 mb-3 d-flex flex-wrap justify-content-between align-items-center gap-3">@csrf<input type="hidden" name="outlet_id" value="{{ $outlet->id }}"><div><strong>{{ $outlet->name }}</strong><div class="small text-muted">{{ $outlet->address }}</div></div><button class="btn btn-primary">Gunakan outlet</button></form>
        @empty
            <div class="alert alert-light-warning">Belum ada outlet aktif yang diberikan. Hubungi administrator untuk meminta akses.</div>
        @endforelse
    </div></div></div></div>
@endsection
