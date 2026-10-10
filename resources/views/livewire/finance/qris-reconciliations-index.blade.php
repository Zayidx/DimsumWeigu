<div x-on:qris-reconciliation-form-ready.window="$nextTick(() => document.getElementById('qris-reconciliation-form-title')?.scrollIntoView({ behavior: 'smooth', block: 'start' }))">
    <div class="alert alert-light-info" role="note">
        QRIS dibayar di aplikasi merchant di luar POS. Masukkan ringkasan settlement harian di sini untuk membandingkannya dengan pembayaran QRIS yang dicatat kasir.
    </div>

    <div class="row g-3 mb-4">
        <div class="col-12 col-sm-6"><section class="card h-100"><div class="card-body"><span class="dashboard-metric-label">Bruto settlement pada halaman ini</span><h2 class="h4 mb-0">Rp{{ \App\Support\DisplayNumber::format($grossTotal / 100, 2, ',', '.') }}</h2></div></section></div>
        <div class="col-12 col-sm-6"><section class="card h-100"><div class="card-body"><span class="dashboard-metric-label">Nilai bersih settlement</span><h2 class="h4 mb-0">Rp{{ \App\Support\DisplayNumber::format($netTotal / 100, 2, ',', '.') }}</h2></div></section></div>
    </div>

    <section class="card mb-4" aria-labelledby="qris-reconciliation-form-title">
        <div class="card-header"><h2 id="qris-reconciliation-form-title" class="card-title mb-0">Catat settlement QRIS</h2></div>
        <div class="card-body">
            <form wire:submit="save" class="row g-3 align-items-end">
                <div class="col-12 col-md-6 col-xl-3"><label for="qris-outlet" class="form-label">Outlet</label><select id="qris-outlet" wire:model="outletId" class="form-select" required><option value="">Pilih outlet</option>@foreach($outlets as $option)<option value="{{ $option->id }}">{{ $option->name }}</option>@endforeach</select>@error('outletId')<small class="text-danger">{{ $message }}</small>@enderror</div>
                <div class="col-6 col-md-3 col-xl-2"><label for="qris-sales-date" class="form-label">Tanggal penjualan</label><input id="qris-sales-date" type="date" max="{{ today()->toDateString() }}" wire:model="salesDate" class="form-control" required>@error('salesDate')<small class="text-danger">{{ $message }}</small>@enderror</div>
                <div class="col-6 col-md-3 col-xl-2"><label for="qris-settlement-date" class="form-label">Tanggal settlement</label><input id="qris-settlement-date" type="date" wire:model="settlementDate" class="form-control" required>@error('settlementDate')<small class="text-danger">{{ $message }}</small>@enderror</div>
                <div class="col-6 col-md-4 col-xl-2"><label for="qris-gross" class="form-label">Bruto dari merchant (Rp)</label><input id="qris-gross" type="number" min="0" step="0.01" inputmode="decimal" wire:model="reportedGross" class="form-control" required>@error('reportedGross')<small class="text-danger">{{ $message }}</small>@enderror</div>
                <div class="col-6 col-md-4 col-xl-2"><label for="qris-fee" class="form-label">Biaya merchant (Rp)</label><input id="qris-fee" type="number" min="0" step="0.01" inputmode="decimal" wire:model="feeAmount" class="form-control" required>@error('feeAmount')<small class="text-danger">{{ $message }}</small>@enderror</div>
                <div class="col-6 col-md-4 col-xl-2"><label for="qris-net" class="form-label">Bersih masuk rekening (Rp)</label><input id="qris-net" type="number" min="0" step="0.01" inputmode="decimal" wire:model="netReceived" class="form-control" required><small class="form-text text-muted">Isi sesuai nilai settlement.</small>@error('netReceived')<small class="text-danger">{{ $message }}</small>@enderror</div>
                <div class="col-12 col-md-4 col-xl-3"><label for="qris-reference" class="form-label">Referensi settlement</label><input id="qris-reference" wire:model="merchantReference" maxlength="160" class="form-control"></div>
                <div class="col-12 col-xl-6"><label for="qris-notes" class="form-label">Catatan</label><input id="qris-notes" wire:model="notes" maxlength="2000" class="form-control"></div>
                <div class="col-12 col-xl-3"><button type="submit" wire:loading.attr="disabled" class="btn btn-primary w-100"><span wire:loading.remove wire:target="save">Simpan rekonsiliasi</span><span wire:loading wire:target="save">Menyimpan…</span></button></div>
            </form>
        </div>
    </section>

    <section class="card mb-4" aria-labelledby="qris-unreconciled-title">
        <div class="card-header"><h2 id="qris-unreconciled-title" class="card-title mb-0">Tanggal QRIS yang belum dicocokkan</h2><small class="text-muted">Sumbernya transaksi POS berstatus selesai dengan tender QRIS terbayar.</small></div>
        <div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>Tanggal</th><th>Outlet</th><th class="text-end">QRIS POS</th><th></th></tr></thead><tbody>
            @forelse($unreconciledDays as $day)
                <tr wire:key="qris-missing-{{ $day->outlet_id }}-{{ $day->sales_date }}"><td>{{ \Illuminate\Support\Carbon::parse($day->sales_date)->format('d/m/Y') }}</td><td>{{ $day->outlet_name }}</td><td class="text-end">Rp{{ \App\Support\DisplayNumber::format($day->total, 2, ',', '.') }}</td><td class="text-end"><button type="button" wire:click="useUnreconciledDay({{ $day->outlet_id }}, '{{ $day->sales_date }}')" class="btn btn-sm btn-outline-primary">Isi tanggal ini</button></td></tr>
            @empty<tr><td colspan="4" class="text-center text-muted py-3">Tidak ada tanggal QRIS yang belum dicocokkan dalam periode ini.</td></tr>@endforelse
        </tbody></table></div>
    </section>

    <section class="card" aria-labelledby="qris-history-title">
        <div class="card-header"><h2 id="qris-history-title" class="card-title mb-0">Riwayat rekonsiliasi</h2></div>
        <div class="card-body border-bottom"><div class="row g-3 align-items-end">
            <div class="col-12 col-md-4"><label for="qris-filter-outlet" class="form-label">Outlet</label><select id="qris-filter-outlet" wire:model.change="outletId" class="form-select"><option value="">Semua outlet</option>@foreach($outlets as $option)<option value="{{ $option->id }}">{{ $option->name }}</option>@endforeach</select></div>
            <div class="col-6 col-md-3"><label for="qris-filter-from" class="form-label">Tanggal penjualan dari</label><input id="qris-filter-from" type="date" wire:model.change="from" class="form-control"></div>
            <div class="col-6 col-md-3"><label for="qris-filter-to" class="form-label">Sampai</label><input id="qris-filter-to" type="date" wire:model.change="to" class="form-control"></div>
        </div></div>
        <div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>Tanggal penjualan / outlet</th><th>Settlement</th><th class="text-end">QRIS POS saat ini</th><th class="text-end">Bruto merchant</th><th class="text-end">Selisih</th><th class="text-end">Biaya / bersih</th><th>Referensi / petugas</th></tr></thead><tbody>
            @forelse($records as $record)
                <tr wire:key="qris-record-{{ $record->id }}"><td>{{ $record->sales_date->format('d/m/Y') }}<small class="d-block text-muted">{{ $record->outlet?->name }}</small></td><td>{{ $record->settlement_date->format('d/m/Y') }}</td><td class="text-end">Rp{{ \App\Support\DisplayNumber::format($record->current_pos_gross_cents / 100, 2, ',', '.') }}<small class="d-block text-muted">Saat dicatat: Rp{{ \App\Support\DisplayNumber::format($record->pos_gross_at_reconciliation, 2, ',', '.') }}</small></td><td class="text-end">Rp{{ \App\Support\DisplayNumber::format($record->reported_gross, 2, ',', '.') }}</td><td class="text-end"><span class="badge {{ $record->difference_cents === 0 && $record->net_difference_cents === 0 ? 'bg-light-success text-success' : 'bg-light-warning text-warning' }}">{{ $record->difference_cents === 0 && $record->net_difference_cents === 0 ? 'Cocok' : 'Perlu dicek' }}</span>@if($record->difference_cents !== 0)<small class="d-block text-warning">{{ $record->difference_cents > 0 ? '+' : '−' }}Rp{{ \App\Support\DisplayNumber::format(abs($record->difference_cents) / 100, 2, ',', '.') }}</small>@endif</td><td class="text-end">Biaya Rp{{ \App\Support\DisplayNumber::format($record->fee_amount, 2, ',', '.') }}<small class="d-block text-muted">Bersih masuk Rp{{ \App\Support\DisplayNumber::format($record->net_received, 2, ',', '.') }}</small>@if($record->net_difference_cents !== 0)<small class="d-block text-warning">Bersih {{ $record->net_difference_cents > 0 ? 'lebih' : 'kurang' }} dari bruto-biaya sebesar Rp{{ \App\Support\DisplayNumber::format(abs($record->net_difference_cents) / 100, 2, ',', '.') }}</small>@endif</td><td>{{ $record->merchant_reference ?: 'Tanpa referensi' }}<small class="d-block text-muted">{{ $record->recorder?->name }}</small>@if($record->notes)<small class="d-block text-muted">{{ $record->notes }}</small>@endif</td></tr>
            @empty<tr><td colspan="7" class="text-center text-muted py-5">Belum ada rekonsiliasi QRIS untuk periode ini.</td></tr>@endforelse
        </tbody></table></div>
        @if($records->hasPages())<div class="card-footer">{{ $records->links() }}</div>@endif
    </section>
</div>
