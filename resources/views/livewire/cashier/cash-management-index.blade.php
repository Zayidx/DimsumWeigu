<div>
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
        <div>
            <h2 class="h4 mb-1">Kas outlet</h2>
            <p class="text-muted mb-0">Kelola uang tunai shift dan lihat catatannya.</p>
        </div>
        @if ($shift && $centralWarehouse)
            @can('cash-management-create')<button wire:click="create" class="btn btn-primary">Buat serah-terima</button>@endcan
        @endif
    </div>

    @if ($shift)
        <section class="card mb-3" aria-labelledby="cash-shift-summary-title">
            <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-3">
                <div>
                    <h3 id="cash-shift-summary-title" class="h6 mb-1">Kas shift aktif</h3>
                    <p class="text-muted mb-0">{{ $shift->warehouse?->name ?? 'Gudang aktif' }} · Dibuka {{ $shift->opened_at->format('d M Y, H:i') }}</p>
                </div>
                <div class="d-flex flex-wrap gap-4">
                    <div><small class="text-muted d-block">Kas tersedia</small><strong class="h5 mb-0">Rp {{ \App\Support\DisplayNumber::format(max(0, $pettyCashPositionCents) / 100, 0, ',', '.') }}</strong></div>
                    @if ($pettyCashPositionCents < 0)
                        <div><small class="text-muted d-block">Utang petty cash</small><strong class="h5 mb-0">Rp {{ \App\Support\DisplayNumber::format(abs($pettyCashPositionCents) / 100, 0, ',', '.') }}</strong></div>
                    @endif
                </div>
            </div>
        </section>
    @else
        <div class="alert alert-light-info" role="status">Belum ada shift aktif. Buka shift untuk mencatat pergerakan kas.</div>
    @endif

    @if ($shift && ! $centralWarehouse)
        <div class="alert alert-light-warning" role="status">Gudang bersama belum tersedia. Permintaan serah-terima belum dapat dibuat.</div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger" role="alert"><ul class="mb-0">@foreach ($errors->all() as $message)<li>{{ $message }}</li>@endforeach</ul></div>
    @endif

    @if ($showForm)
        <section class="card mb-3" aria-labelledby="cash-handover-form-title">
            <div class="card-header"><h3 id="cash-handover-form-title" class="h5 mb-0">Permintaan serah-terima kas</h3></div>
            <div class="card-body">
                <p class="text-muted">Kas dikirim dari gudang outlet pada shift aktif ke gudang bersama. Kas baru berpindah setelah finance menyetujui permintaan.</p>
                <form wire:submit="requestHandover">
                    <div class="row g-3">
                        <div class="col-12 col-md-6"><span class="form-label d-block">Gudang sumber</span><div class="form-control-plaintext">{{ $shift->warehouse?->name }} · Outlet aktif</div></div>
                        <div class="col-12 col-md-6"><span class="form-label d-block">Gudang tujuan</span><div class="form-control-plaintext">{{ $centralWarehouse->name }}</div></div>
                        <div class="col-12 col-md-4"><label for="cash-amount" class="form-label">Jumlah (Rp)</label><input id="cash-amount" type="number" inputmode="decimal" min="0.01" step="0.01" wire:model="amount" class="form-control"></div>
                        <div class="col-12 col-md-8"><label for="cash-notes" class="form-label">Catatan</label><textarea id="cash-notes" wire:model="notes" maxlength="2000" class="form-control" rows="2"></textarea></div>
                    </div>
                    <div class="d-flex flex-wrap gap-2 mt-3 workspace-form-actions"><button class="btn btn-primary" wire:loading.attr="disabled">Kirim permintaan</button><button type="button" wire:click="cancelForm" class="btn btn-light-secondary">Batal</button></div>
                </form>
            </div>
        </section>
    @endif

    @can('cash-management-create')
        @if ($shift)
            <details class="card mb-3">
                <summary class="card-header">Catat pergerakan kas shift</summary>
                <div class="card-body">
                    <p class="text-muted">Kas masuk menambah petty cash. Kas keluar otomatis masuk ke laporan Pengeluaran kategori Pembelanjaan Operasional Outlet. Jika kas kurang, selisih dicatat sebagai talangan dan dibayar dari omzet tunai berikutnya.</p>
                    <form wire:submit="recordMovement">
                        <div class="row g-3">
                            <div class="col-12 col-md-3"><label for="movement-type" class="form-label">Jenis</label><select id="movement-type" wire:model="movementType" class="form-select"><option value="in">Kas masuk ke petty cash</option><option value="out">Kas keluar operasional</option></select></div>
                            <div class="col-12 col-md-3"><label for="movement-amount" class="form-label">Jumlah (Rp)</label><input id="movement-amount" wire:model="movementAmount" type="number" inputmode="decimal" min="0.01" step="0.01" class="form-control"></div>
                            <div class="col-12 col-md-6"><label for="movement-note" class="form-label">Catatan</label><input id="movement-note" wire:model="movementNote" maxlength="2000" class="form-control"></div>
                        </div>
                        <button class="btn btn-outline-primary mt-3" wire:confirm="Simpan pergerakan kas ini?" wire:loading.attr="disabled">Simpan pergerakan</button>
                    </form>
                </div>
            </details>
        @endif
    @endcan

    <section class="card mb-3" aria-labelledby="cash-handovers-title" wire:poll.15s>
        <div class="card-header"><h3 id="cash-handovers-title" class="h5 mb-0">Serah-terima kas</h3></div>
        <div class="list-group list-group-flush">
            @forelse ($handovers as $handover)
                <article class="list-group-item p-3" wire:key="cashier-handover-{{ $handover->id }}">
                    <div class="d-flex flex-wrap justify-content-between gap-2">
                        <div>
                            <strong>{{ $handover->number }} · Rp {{ \App\Support\DisplayNumber::format((float) $handover->amount, 0, ',', '.') }}</strong>
                            <div>{{ $handover->source?->name }} ke {{ $handover->destination?->name }}</div>
                            <small class="text-muted">{{ $handover->created_at->format('d/m/Y H:i') }} · {{ $handover->requester?->name }} · {{ $handover->notes }}</small>
                        </div>
                        @if ($handover->status === 'cancelled')
                            <span class="badge bg-light-danger align-self-start">Dibatalkan</span>
                        @else
                            <span class="badge align-self-start {{ $handover->status === 'confirmed' ? 'bg-light-success' : 'bg-light-warning' }}">{{ __(['pending' => 'Menunggu persetujuan finance', 'approved' => 'Kas dikirim · menunggu diterima', 'confirmed' => 'Selesai · kas diterima'][$handover->status] ?? $handover->status) }}</span>
                        @endif
                    </div>
                    @if ($handover->status !== 'cancelled')
                        @php($progress = ['pending' => 1, 'approved' => 2, 'confirmed' => 3][$handover->status] ?? 1)
                        <ol class="d-flex flex-wrap gap-2 list-unstyled small mt-3 mb-0" aria-label="Progres serah-terima kas">
                            @foreach (['Permintaan dibuat', 'Disetujui dan dikirim', 'Diterima gudang bersama'] as $step => $label)
                                <li class="px-2 py-1 rounded {{ $step + 1 <= $progress ? 'bg-light-success text-success' : 'bg-light text-muted' }}" @if ($step + 1 === $progress) aria-current="step" @endif>{{ $step + 1 }}. {{ $label }}</li>
                            @endforeach
                        </ol>
                        @if ($handover->status === 'pending')
                            <small class="text-muted d-block mt-2">Saldo petty cash belum berubah. Saldo outlet berkurang setelah finance menyetujui dan mengirim kas.</small>
                        @elseif ($handover->status === 'approved')
                            <small class="text-muted d-block mt-2">Saldo outlet sudah berkurang. Gudang bersama mencatat saldo setelah penerimaan dikonfirmasi.</small>
                        @endif
                    @endif
                    <div class="d-flex flex-wrap gap-2 mt-2">
                        @if ($handover->status === 'pending')
                            @can('cash-management-create')<button wire:click="cancelHandover({{ $handover->id }})" wire:confirm="Batalkan permintaan serah-terima?" class="btn btn-sm btn-outline-danger">Batalkan</button>@endcan
                        @endif
                    </div>
                </article>
            @empty
                <p class="text-muted p-3 mb-0">Belum ada serah-terima kas.</p>
            @endforelse
        </div>
        @if ($handovers->hasPages())<div class="card-footer">{{ $handovers->links() }}</div>@endif
    </section>

    <details class="card">
        <summary class="card-header">Riwayat mutasi kas</summary>
        <div class="card-body">
            <div class="list-group list-group-flush">
                @forelse ($ledger as $row)
                    <article class="list-group-item px-0" wire:key="cashier-ledger-{{ $row->id }}">
                        <div class="d-flex flex-wrap justify-content-between gap-2">
                            <div><strong>{{ __(['shift_opening' => 'Kas awal', 'sale_cash' => 'Penjualan tunai', 'cash_in' => 'Kas masuk', 'cash_out' => 'Kas keluar', 'handover_out' => 'Serah-terima dikirim', 'handover_in' => 'Serah-terima diterima', 'supplier_payment' => 'Pembayaran pemasok', 'supplier_refund' => 'Refund pemasok', 'expense' => 'Pengeluaran', 'sale_refund' => 'Refund penjualan'][$row->type] ?? $row->type) }}</strong><div>{{ $row->created_at->format('d/m/Y H:i') }} · {{ $row->warehouse?->name }}</div><small class="text-muted">{{ $row->note }}</small></div>
                            @php($delta = round(((float) $row->balance_after - (float) $row->balance_before) * 100))
                            <div class="text-md-end"><strong>{{ $delta < 0 ? '−' : '+' }} Rp {{ \App\Support\DisplayNumber::format(abs($delta) / 100, 0, ',', '.') }}</strong><small class="d-block text-muted">Saldo Rp {{ \App\Support\DisplayNumber::format((float) $row->balance_after, 0, ',', '.') }}</small></div>
                        </div>
                    </article>
                @empty
                    <p class="text-muted mb-0">Belum ada mutasi kas.</p>
                @endforelse
            </div>
            @if ($ledger->hasPages())<div class="mt-3">{{ $ledger->links() }}</div>@endif
        </div>
    </details>
</div>
