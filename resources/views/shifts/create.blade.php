@extends('layouts.dashboard')
@section('page-title', 'Buka shift')

@section('content')
    <div class="row justify-content-center"><div class="col-12 col-xl-7"><div class="card"><div class="card-header"><h4 class="card-title">Buka shift · {{ $outlet->name }}</h4></div><div class="card-body">
        @if ($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
        @if ($outlets->count() > 1 && ! $shift)<form method="GET" action="{{ route('shifts.create') }}" class="mb-3"><label for="shift-outlet" class="form-label">Outlet tempat bekerja</label><div class="input-group"><select id="shift-outlet" name="outlet" class="form-select">@foreach ($outlets as $option)<option value="{{ $option->id }}" @selected($option->id === $outlet->id)>{{ $option->name }}</option>@endforeach</select><button class="btn btn-outline-primary" type="submit">Pilih outlet</button></div></form>@endif
        @if ($shift)<div class="alert alert-light-success">Shift kasir sudah aktif pada gudang <strong>{{ $shift->warehouse->name }}</strong>. Anda bisa langsung melanjutkan transaksi.</div><a class="btn btn-primary" href="{{ route('pos.index') }}">Lanjut ke kasir</a>
        @elseif ($warehouses->isEmpty())<div class="alert alert-light-warning">Outlet ini belum memiliki gudang penjualan aktif.</div>
        @else<form method="POST" action="{{ route('shifts.store') }}">@csrf<input type="hidden" name="outlet_id" value="{{ $outlet->id }}">
            <div class="mb-3"><label class="form-label" for="warehouse_id">Gudang penjualan</label><select id="warehouse_id" name="warehouse_id" class="form-select" required>@foreach ($warehouses as $warehouse)<option value="{{ $warehouse->id }}">{{ $warehouse->name }}</option>@endforeach</select></div>
            <div class="alert alert-light-info">Petty cash awal Rp50.000 ditetapkan otomatis pada gudang ini. Saldo atau utang dari shift sebelumnya diteruskan, dan penjualan tunai berikutnya akan mengurangi utang petty cash terlebih dahulu.</div>
            <button class="btn btn-primary"><i class="bi bi-play-fill me-1"></i>Mulai shift</button>
        </form>@endif
    </div></div></div></div>
@endsection
