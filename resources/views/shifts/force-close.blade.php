@extends('layouts.dashboard')
@section('page-title', __('Tutup paksa shift'))

@section('content')
<div class="card"><div class="card-header"><h4>{{ __('Tutup paksa shift') }}</h4></div><div class="card-body"><p>{{ $shift->user?->name }} · {{ $shift->warehouse?->name }} · {{ $shift->opened_at->format('d/m/Y H:i') }}</p><p>{{ __('Kas yang diharapkan saat ini') }}: <strong>Rp {{ \App\Support\DisplayNumber::format($expected, 2, ',', '.') }}</strong></p><p class="text-muted">{{ __('Hitung kas fisik sebelum melanjutkan. Keranjang aktif dan hold pada shift ini akan kedaluwarsa. Transaksi penjualan tetap disimpan.') }}</p>
@if($errors->any())<div class="alert alert-danger" role="alert"><ul>@foreach($errors->all() as $message)<li>{{ $message }}</li>@endforeach</ul></div>@endif
<form method="POST" action="{{ route('shifts.force-close', $shift->id) }}">@csrf<div class="mb-3"><label class="form-label" for="forced-cash">{{ __('Kas fisik (Rp)') }}</label><input id="forced-cash" name="actual_cash" value="{{ old('actual_cash') }}" type="number" min="0" step="0.01" class="form-control" required></div><div class="mb-3"><label for="forced-reason" class="form-label">{{ __('Alasan penutupan paksa dan catatan rekonsiliasi') }}</label><textarea id="forced-reason" name="reason" minlength="3" maxlength="2000" class="form-control" required>{{ old('reason') }}</textarea></div><button class="btn btn-danger">{{ __('Rekonsiliasi dan tutup paksa') }}</button> <a href="{{ route('shifts.index') }}" class="btn btn-light-secondary">{{ __('Batal') }}</a></form>
</div></div>
@endsection
