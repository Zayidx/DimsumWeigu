@extends('layouts.dashboard')
@section('page-title', 'Shift kasir')

@section('content')
    @if (session('status'))<div class="alert alert-light-success" role="status">{{ session('status') }}</div>@endif
    @if ($errors->any())<div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>@endif

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <p class="text-muted mb-0">Outlet aktif: {{ $outlet->name }}</p>
        @if($openShift)
            @can('pos-access')<a href="{{ route('pos.index') }}" class="btn btn-primary"><i class="bi bi-shop me-1" aria-hidden="true"></i>Lanjut ke kasir</a>@endcan
        @else
            @can('cashier-shifts-open')<a href="{{ route('shifts.create') }}" class="btn btn-primary"><i class="bi bi-play-circle me-1" aria-hidden="true"></i>Buka shift</a>@endcan
        @endif
    </div>

    @if ($openShift)
        <section class="card border-success mb-4" aria-labelledby="active-shift-title">
            <div class="card-body">
                <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
                    <div>
                        <h2 id="active-shift-title" class="h5 mb-1">Shift aktif · {{ $openShift->opened_at->format('d M Y H:i') }}</h2>
                        <p class="text-muted mb-0">Posisi petty cash saat shift dibuka: Rp {{ \App\Support\DisplayNumber::format($openShift->opening_cash, 2, ',', '.') }} · Gudang {{ $openShift->warehouse->name ?? 'aktif' }}</p>
                    </div>
                    <button type="button" class="btn btn-outline-success" data-bs-toggle="collapse" data-bs-target="#closeShift" aria-expanded="false" aria-controls="closeShift">Tutup shift</button>
                </div>

                <div class="collapse mt-4" id="closeShift">
                    <div class="rounded border p-3 mb-3" data-shift-reconciliation data-expected-cents="{{ $shiftSummary['expected_cents'] }}">
                        <div class="d-flex flex-wrap justify-content-between align-items-baseline gap-2 border-bottom pb-2 mb-2">
                            <span class="text-muted">Kas tunai yang seharusnya</span>
                            <strong class="h5 mb-0">Rp {{ \App\Support\DisplayNumber::format($shiftSummary['expected_cents'] / 100, 2, ',', '.') }}</strong>
                        </div>
                        <div class="row g-2 small">
                            <div class="col-6 col-lg-3"><span class="text-muted d-block">Kas awal</span><strong>Rp {{ \App\Support\DisplayNumber::format($openShift->opening_cash, 2, ',', '.') }}</strong></div>
                            <div class="col-6 col-lg-3"><span class="text-muted d-block">Penjualan tunai</span><strong>Rp {{ \App\Support\DisplayNumber::format($shiftSummary['cash_sales_cents'] / 100, 2, ',', '.') }}</strong></div>
                            <div class="col-6 col-lg-3"><span class="text-muted d-block">Kas masuk / keluar</span><strong>Rp {{ \App\Support\DisplayNumber::format(($shiftSummary['cash_in_cents'] - $shiftSummary['cash_out_cents']) / 100, 2, ',', '.') }}</strong></div>
                            <div class="col-6 col-lg-3"><span class="text-muted d-block">QRIS manual</span><strong>Rp {{ \App\Support\DisplayNumber::format($shiftSummary['qris_sales_cents'] / 100, 2, ',', '.') }}</strong></div>
                        </div>
                        <p class="small text-muted mb-0 mt-2">QRIS dicatat terpisah dan tidak termasuk uang tunai yang dihitung.</p>
                    </div>

                    <form method="POST" action="{{ route('shifts.close') }}" data-shift-close-form>
                        @csrf
                        <div class="row g-3">
                            <div class="col-12 col-md-4">
                                <label class="form-label" for="actual_cash">Kas fisik yang dihitung</label>
                                <input id="actual_cash" name="actual_cash" type="number" min="0" step="0.01" inputmode="decimal" value="{{ old('actual_cash') }}" class="form-control" required data-actual-cash>
                                @error('actual_cash')<span class="text-danger small">{{ $message }}</span>@enderror
                            </div>
                            <div class="col-12 col-md-8">
                                <label class="form-label" for="note">Catatan selisih</label>
                                <input id="note" name="note" class="form-control" maxlength="1000" data-variance-note>
                                <small class="text-muted">Wajib diisi jika kas fisik berbeda dari saldo yang seharusnya.</small>
                                @error('note')<span class="text-danger small d-block">{{ $message }}</span>@enderror
                            </div>
                            <div class="col-12"><output class="small text-muted" data-variance-output aria-live="polite">Masukkan kas fisik untuk melihat selisih.</output></div>
                            <div class="col-12"><button class="btn btn-danger" type="submit">Simpan rekonsiliasi dan tutup shift</button></div>
                        </div>
                    </form>
                </div>
            </div>
        </section>
    @endif

    <section class="card" aria-labelledby="shift-history-title">
        <div class="card-header"><h2 id="shift-history-title" class="h5 card-title mb-0">Riwayat shift</h2></div>
        <div class="table-responsive"><table class="table table-hover align-middle mb-0">
            <thead><tr><th>Kasir</th><th>Gudang</th><th>Dibuka</th><th>Ditutup</th><th>Status</th><th class="text-end">Selisih</th></tr></thead>
            <tbody>
                @forelse ($shifts as $shift)
                    <tr>
                        <td>{{ $shift->user->name }}</td>
                        <td>{{ $shift->warehouse->name }}</td>
                        <td>{{ $shift->opened_at->format('d M Y H:i') }}</td>
                        <td>{{ $shift->closed_at?->format('d M Y H:i') ?? '—' }}</td>
                        <td>
                            <span class="badge {{ $shift->status === 'open' ? 'bg-light-success' : 'bg-light-secondary' }}">{{ ucfirst($shift->status) }}</span>
                            @if($shift->is_force_closed)<small class="d-block text-danger">{{ __('Ditutup paksa') }}</small>@endif
                            @can('cashier-shifts-force-close')
                                @if($shift->status === 'open')<a href="{{ route('shifts.force-form', $shift->id) }}" class="btn btn-sm btn-outline-danger mt-1">{{ __('Tutup paksa') }}</a>@endif
                            @endcan
                        </td>
                        <td class="text-end">{{ $shift->cash_difference === null ? '—' : 'Rp '.\App\Support\DisplayNumber::format($shift->cash_difference, 2, ',', '.') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center py-5 text-muted">Belum ada riwayat shift di outlet ini.</td></tr>
                @endforelse
            </tbody>
        </table></div>
        <div class="card-footer">{{ $shifts->links('pagination::bootstrap-5') }}</div>
    </section>
@endsection

@push('scripts')
    <script>
        (() => {
            const money = new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 2 });
            document.querySelectorAll('[data-shift-reconciliation]').forEach((summary) => {
                const form = summary.parentElement.querySelector('[data-shift-close-form]');
                const input = form?.querySelector('[data-actual-cash]');
                const note = form?.querySelector('[data-variance-note]');
                const output = form?.querySelector('[data-variance-output]');
                if (!input || !note || !output) return;

                const update = () => {
                    if (input.value === '') {
                        output.textContent = 'Masukkan kas fisik untuk melihat selisih.';
                        note.required = false;
                        return;
                    }
                    const actualCents = Math.round(Number(input.value) * 100);
                    const difference = actualCents - Number(summary.dataset.expectedCents);
                    output.textContent = difference === 0
                        ? 'Kas sesuai dengan saldo yang seharusnya.'
                        : `Selisih ${difference > 0 ? 'lebih' : 'kurang'}: ${money.format(Math.abs(difference) / 100)}.`;
                    note.required = difference !== 0;
                };

                input.addEventListener('input', update);
                update();
            });
        })();
    </script>
@endpush
