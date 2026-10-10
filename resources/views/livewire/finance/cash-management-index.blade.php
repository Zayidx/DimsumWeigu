<div>
    <header class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
        <div>
            <h2 class="h4 mb-1">Manajemen kas</h2>
            <p class="text-muted mb-0">Pantau kas outlet, penerimaan di gudang bersama, dan perpindahan uang.</p>
        </div>
        @can('cash-management-create')
            <button type="button" wire:click="create" class="btn btn-primary">Buat serah-terima</button>
        @endcan
    </header>

    @if ($canViewAllOutletCash)
        <section class="card mb-4" aria-labelledby="cash-overview-title">
            <div class="card-header">
                <h3 id="cash-overview-title" class="h5 mb-1">Ringkasan kas outlet</h3>
                <p class="small text-muted mb-0">Saldo outlet dihitung dari mutasi terakhir tiap gudang. Penerimaan pusat adalah akumulasi serah-terima yang sudah dikonfirmasi.</p>
            </div>
            <div class="card-body">
                <div class="row g-3 mb-4">
                    <div class="col-12 col-md-4">
                        <div class="h-100 p-3 bg-light rounded">
                            <span class="small text-muted d-block">Saldo kas di seluruh outlet</span>
                            <strong class="h5">Rp {{ \App\Support\DisplayNumber::format($outletCashBalanceCents / 100, 0, ',', '.') }}</strong>
                        </div>
                    </div>
                    <div class="col-12 col-md-4">
                        <div class="h-100 p-3 bg-light rounded">
                            <span class="small text-muted d-block">Total diterima gudang bersama</span>
                            <strong class="h5">Rp {{ \App\Support\DisplayNumber::format($collectedOutletCashCents / 100, 0, ',', '.') }}</strong>
                            <small class="text-muted d-block">Akumulasi penerimaan terkonfirmasi, sebelum pemakaian atau setoran.</small>
                        </div>
                    </div>
                    @if ($centralWarehouse)
                        <div class="col-12 col-md-4">
                            <div class="h-100 p-3 bg-light rounded">
                                <span class="small text-muted d-block">Saldo kas gudang bersama saat ini</span>
                                <strong class="h5">Rp {{ \App\Support\DisplayNumber::format($centralCashBalanceCents / 100, 0, ',', '.') }}</strong>
                            </div>
                        </div>
                    @endif
                </div>

                <h4 class="h6 mb-3">Rincian per outlet</h4>
                <div class="list-group list-group-flush">
                    @forelse ($outletCashSummary as $outlet)
                        <article class="list-group-item px-0" wire:key="cash-outlet-summary-{{ $outlet->id }}">
                            <div class="d-flex flex-wrap justify-content-between gap-3">
                                <div>
                                    <strong>{{ $outlet->name }}</strong>
                                    @if ($outlet->outlet_balance_cents < 0)
                                        <small class="text-warning d-block">Talangan operasional belum terganti: Rp {{ \App\Support\DisplayNumber::format(abs($outlet->outlet_balance_cents) / 100, 0, ',', '.') }}</small>
                                    @else
                                        <small class="text-muted d-block">Saldo kas yang tercatat di gudang outlet</small>
                                    @endif
                                </div>
                                <div class="d-flex flex-wrap gap-4">
                                    <div>
                                        <small class="text-muted d-block">Saldo outlet</small>
                                        <strong>Rp {{ \App\Support\DisplayNumber::format(max(0, $outlet->outlet_balance_cents) / 100, 0, ',', '.') }}</strong>
                                    </div>
                                    <div>
                                        <small class="text-muted d-block">Diterima pusat</small>
                                        <strong>Rp {{ \App\Support\DisplayNumber::format($outlet->collected_cents / 100, 0, ',', '.') }}</strong>
                                    </div>
                                </div>
                            </div>
                        </article>
                    @empty
                        <p class="text-muted mb-0">Belum ada data outlet.</p>
                    @endforelse
                </div>
            </div>
        </section>
    @else
        <section class="card mb-4" aria-labelledby="cash-outlet-summary-title">
            <div class="card-body">
                <h3 id="cash-outlet-summary-title" class="h6 mb-1">Gudang outlet aktif</h3>
                <p class="text-muted mb-0">Riwayat dan saldo di bawah hanya menampilkan gudang outlet yang sedang dipilih.</p>
            </div>
        </section>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger" role="alert">
            <ul class="mb-0">@foreach ($errors->all() as $message)<li>{{ $message }}</li>@endforeach</ul>
        </div>
    @endif

    @if ($showForm)
        <section class="card mb-4" aria-labelledby="handover-form-title">
            <div class="card-header"><h3 id="handover-form-title" class="h5 mb-0">Permintaan serah-terima kas</h3></div>
            <div class="card-body">
                <p class="text-muted">Permintaan belum mengubah saldo. Kas sumber berkurang setelah disetujui, lalu gudang tujuan mencatatnya setelah uang fisik diterima.</p>
                <form wire:submit="requestHandover">
                    <div class="row g-3">
                        <div class="col-12 col-md-6"><label for="cash-source" class="form-label">Gudang sumber</label><select id="cash-source" wire:model="sourceId" class="form-select"><option value="">Pilih sumber</option>@foreach ($sources as $source)<option value="{{ $source->id }}">{{ $source->name }}</option>@endforeach</select></div>
                        <div class="col-12 col-md-6"><label for="cash-destination" class="form-label">Gudang tujuan</label><select id="cash-destination" wire:model="destinationId" class="form-select"><option value="">Pilih tujuan</option>@foreach ($destinations as $destination)<option value="{{ $destination->id }}">{{ $destination->name }} · {{ $destination->outlet?->name ?? 'Gudang bersama' }}</option>@endforeach</select></div>
                        <div class="col-12 col-md-4"><label for="cash-amount" class="form-label">Jumlah (Rp)</label><input id="cash-amount" type="number" inputmode="decimal" min="0.01" step="0.01" wire:model="amount" class="form-control"></div>
                        <div class="col-12 col-md-8"><label for="cash-notes" class="form-label">Catatan</label><textarea id="cash-notes" wire:model="notes" maxlength="2000" rows="2" class="form-control"></textarea></div>
                    </div>
                    <div class="d-flex flex-wrap gap-2 mt-3"><button class="btn btn-primary" wire:loading.attr="disabled">Kirim permintaan</button><button type="button" wire:click="cancelForm" class="btn btn-light-secondary">Batal</button></div>
                </form>
            </div>
        </section>
    @endif

    @can('cash-management-create')
        @if ($shift)
            <details class="card mb-4">
                <summary class="card-header">Pergerakan kas shift · saldo Rp {{ \App\Support\DisplayNumber::format(max(0, $shiftCashPositionCents) / 100, 0, ',', '.') }}</summary>
                <div class="card-body">
                    <p class="text-muted">Kas masuk menambah petty cash. Kas keluar otomatis masuk ke laporan Pengeluaran kategori Pembelanjaan Operasional Outlet. Jika kas kurang, saldo minus dicatat sebagai talangan untuk diganti dari omzet tunai berikutnya.</p>
                    @if ($shiftCashPositionCents < 0)
                        <div class="alert alert-light-warning" role="status">Talangan operasional shift ini Rp {{ \App\Support\DisplayNumber::format(abs($shiftCashPositionCents) / 100, 0, ',', '.') }}. Omzet tunai berikutnya akan mengurangi nilai talangan tersebut.</div>
                    @endif
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

    @if (auth()->user()->canAny(['cash-management-create', 'bank-accounts-access']))
        @if ($centralWarehouse && $bankAccounts->isNotEmpty())
            <section class="card mb-4" aria-labelledby="cash-deposit-title">
                <div class="card-header"><h3 id="cash-deposit-title" class="h5 mb-0">Setor kas gudang bersama ke rekening</h3></div>
                <div class="card-body">
                    <p class="text-muted">Setoran mengurangi saldo kas gudang bersama dan menambah saldo rekening dalam satu proses.</p>
                    <form wire:submit="depositCash">
                        <div class="row g-3">
                            <div class="col-12 col-md-5"><label for="deposit-account" class="form-label">Rekening tujuan</label><select id="deposit-account" wire:model="depositAccountId" class="form-select" required><option value="">Pilih rekening</option>@foreach ($bankAccounts as $account)<option value="{{ $account->id }}">{{ $account->bank_name }} · {{ $account->account_name }} ({{ $account->account_number }}) · Saldo Rp {{ \App\Support\DisplayNumber::format(($bankBalances[$account->id] ?? 0) / 100, 0, ',', '.') }}</option>@endforeach</select></div>
                            <div class="col-12 col-md-3"><label for="deposit-amount" class="form-label">Jumlah setor (Rp)</label><input id="deposit-amount" type="number" min="0.01" step="0.01" inputmode="decimal" wire:model="depositAmount" class="form-control" required></div>
                            <div class="col-12 col-md-4"><label for="deposit-note" class="form-label">Catatan / bukti setor</label><input id="deposit-note" wire:model="depositNote" maxlength="500" class="form-control" required></div>
                        </div>
                        <button type="submit" class="btn btn-primary mt-3" wire:confirm="Pastikan uang sudah disetorkan ke rekening." wire:loading.attr="disabled">Catat setoran</button>
                    </form>
                </div>
            </section>
        @endif
    @endif

    <section class="card mb-4" aria-labelledby="cash-handovers-title">
        <div class="card-header"><h3 id="cash-handovers-title" class="h5 mb-0">Serah-terima kas</h3></div>
        <div class="list-group list-group-flush">
            @forelse ($handovers as $handover)
                <article class="list-group-item p-3" wire:key="handover-{{ $handover->id }}">
                    <div class="d-flex flex-wrap justify-content-between gap-3">
                        <div>
                            <strong>{{ $handover->number }} · Rp {{ \App\Support\DisplayNumber::format((float) $handover->amount, 0, ',', '.') }}</strong>
                            <div>{{ $handover->source?->name }} ke {{ $handover->destination?->name }}</div>
                            <small class="text-muted">{{ $handover->created_at->format('d/m/Y H:i') }} · {{ $handover->requester?->name }} · {{ $handover->notes }}</small>
                            @if ($handover->approver || $handover->receiver)
                                <small class="text-muted d-block">Disetujui {{ $handover->approver?->name ?? '—' }} · Diterima {{ $handover->receiver?->name ?? '—' }}</small>
                            @endif
                        </div>
                        <span class="badge align-self-start {{ $handover->status === 'confirmed' ? 'bg-light-success' : ($handover->status === 'cancelled' ? 'bg-light-danger' : 'bg-light-secondary') }}">{{ __(['pending' => 'Menunggu persetujuan', 'approved' => 'Menunggu penerimaan', 'confirmed' => 'Diterima', 'cancelled' => 'Dibatalkan'][$handover->status] ?? $handover->status) }}</span>
                    </div>
                    <div class="d-flex flex-wrap gap-2 mt-3">
                        @if ($handover->status === 'pending' && $currentIds->contains($handover->source_warehouse_id))
                            @can('cash-management-approve')<button wire:click="approve({{ $handover->id }})" wire:confirm="Setujui dan keluarkan uang dari kas sumber?" class="btn btn-sm btn-primary">Setujui dan kirim</button>@endcan
                            @can('cash-management-create')@if ($handover->requested_by === auth()->id() || auth()->user()->can('cash-management-approve'))<button wire:click="cancelHandover({{ $handover->id }})" wire:confirm="Batalkan permintaan ini?" class="btn btn-sm btn-outline-danger">Batalkan</button>@endif @endcan
                        @endif
                        @if ($handover->status === 'approved' && $currentIds->contains($handover->destination_warehouse_id) && auth()->user()->canAny(['cash-management-create', 'cash-handovers-confirm']))
                            <button wire:click="confirm({{ $handover->id }})" wire:confirm="Konfirmasi setelah uang fisik diterima lengkap?" class="btn btn-sm btn-success">Konfirmasi penerimaan</button>
                        @endif
                    </div>
                </article>
            @empty
                <p class="text-muted p-3 mb-0">Belum ada serah-terima kas.</p>
            @endforelse
        </div>
        @if ($handovers->hasPages())<div class="card-footer">{{ $handovers->links() }}</div>@endif
    </section>

    <details class="card mb-4">
        <summary class="card-header">Riwayat mutasi kas</summary>
        <div class="card-body">
            <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-3">
                <div class="flex-grow-1"><label for="ledger-warehouse" class="form-label">Gudang yang ditampilkan</label><select id="ledger-warehouse" wire:model.live="ledgerWarehouse" class="form-select"><option value="">Semua gudang dalam akses Anda</option>@foreach ($warehouses as $warehouse)<option value="{{ $warehouse->id }}">{{ $warehouse->name }}</option>@endforeach</select></div>
                @can('reports-export')<button wire:click="export" class="btn btn-outline-primary">Ekspor Excel</button>@endcan
            </div>
            <div class="list-group list-group-flush">
                @forelse ($ledger as $row)
                    <article class="list-group-item px-0" wire:key="cash-ledger-{{ $row->id }}">
                        <div class="d-flex flex-wrap justify-content-between gap-3">
                            <div><strong>{{ __(['shift_opening' => 'Saldo awal shift', 'sale_cash' => 'Penjualan tunai', 'cash_in' => 'Kas masuk', 'cash_out' => 'Kas keluar', 'handover_out' => 'Serah-terima dikirim', 'handover_in' => 'Serah-terima diterima', 'supplier_payment' => 'Pembayaran pemasok', 'supplier_refund' => 'Pengembalian pemasok', 'expense' => 'Pengeluaran operasional', 'sale_refund' => 'Pengembalian penjualan', 'bank_deposit' => 'Setoran ke rekening'][$row->type] ?? $row->type) }}</strong><div>{{ $row->created_at->format('d/m/Y H:i') }} · {{ $row->warehouse?->name }}</div><small class="text-muted">{{ $row->note }} · {{ $row->user?->name }}</small></div>
                            <div><strong>Saldo Rp {{ \App\Support\DisplayNumber::format((float) $row->balance_after, 0, ',', '.') }}</strong><small class="d-block text-muted">Mutasi Rp {{ \App\Support\DisplayNumber::format((float) $row->amount, 0, ',', '.') }}</small></div>
                        </div>
                    </article>
                @empty
                    <p class="text-muted mb-0">Belum ada mutasi kas.</p>
                @endforelse
            </div>
            @if ($ledger->hasPages())<div class="mt-3">{{ $ledger->links() }}</div>@endif
        </div>
    </details>

    @if (auth()->user()->can('bank-accounts-access'))
        <section class="card" aria-labelledby="bank-ledger-title">
            <div class="card-header"><h3 id="bank-ledger-title" class="h5 mb-0">Mutasi rekening</h3></div>
            <div class="list-group list-group-flush">
                @forelse ($bankLedger as $row)
                    <article class="list-group-item p-3" wire:key="bank-ledger-{{ $row->id }}">
                        <div class="d-flex flex-wrap justify-content-between gap-3">
                            <div><strong>{{ $row->account?->bank_name }} · {{ $row->account?->account_name }}</strong><div>{{ $row->account?->account_number }}</div><small class="text-muted">{{ $row->created_at->format('d/m/Y H:i') }} · {{ $row->note }} · {{ $row->user?->name }}</small></div>
                            <div>{{ ['qris_sale' => 'Penjualan QRIS', 'cash_deposit' => 'Setoran dari kas'][$row->type] ?? $row->type }}<strong class="d-block">Rp {{ \App\Support\DisplayNumber::format((float) $row->amount, 0, ',', '.') }}</strong><small class="text-muted">Saldo Rp {{ \App\Support\DisplayNumber::format((float) $row->balance_after, 0, ',', '.') }}</small></div>
                        </div>
                    </article>
                @empty
                    <p class="text-muted p-3 mb-0">Belum ada mutasi rekening.</p>
                @endforelse
            </div>
            @if ($bankLedger->hasPages())<div class="card-footer">{{ $bankLedger->links() }}</div>@endif
        </section>
    @endif
</div>
