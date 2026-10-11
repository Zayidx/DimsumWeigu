<div>
    <div class="mb-3"><h4 class="mb-1">Kas Warehouse ke Finance</h4><p class="text-muted mb-0">Pengajuan dan penerimaan fisik dicatat sebagai perpindahan tanggung jawab kas.</p></div>
    <div class="row g-2 mb-3">
        <div class="col-6 col-lg-3"><div class="card h-100"><div class="card-body"><span class="small text-muted d-block">Saldo Warehouse</span><strong>Rp {{ \App\Support\DisplayNumber::format($balanceCents / 100, 2, ',', '.') }}</strong></div></div></div>
        <div class="col-6 col-lg-3"><div class="card h-100"><div class="card-body"><span class="small text-muted d-block">Dalam proses</span><strong>Rp {{ \App\Support\DisplayNumber::format($pendingCents / 100, 2, ',', '.') }}</strong></div></div></div>
        <div class="col-6 col-lg-3"><div class="card h-100"><div class="card-body"><span class="small text-muted d-block">Tersedia diajukan</span><strong>Rp {{ \App\Support\DisplayNumber::format($availableCents / 100, 2, ',', '.') }}</strong></div></div></div>
        <div class="col-6 col-lg-3"><div class="card h-100"><div class="card-body"><span class="small text-muted d-block">Diterima Finance</span><strong>Rp {{ \App\Support\DisplayNumber::format((float) $financeBalance, 2, ',', '.') }}</strong></div></div></div>
    </div>
    @can('warehouse-finance-handovers-create')
        <div class="card mb-3"><div class="card-header"><h5 class="card-title mb-0">Ajukan penyerahan uang</h5></div><div class="card-body"><form wire:submit="requestHandover" wire:confirm="Ajukan nominal ini kepada Finance? Dana akan ditandai dalam proses."><div class="row g-2"><div class="col-12 col-md-4"><label class="form-label" for="handover-amount">Nominal (Rp)</label><input id="handover-amount" type="number" inputmode="decimal" min="0.01" step="0.01" wire:model="amount" class="form-control" required>@error('amount')<div class="small text-danger">{{ $message }}</div>@enderror</div><div class="col-12 col-md-8"><label class="form-label" for="handover-note">Catatan</label><input id="handover-note" wire:model="notes" maxlength="2000" class="form-control" required>@error('notes')<div class="small text-danger">{{ $message }}</div>@enderror</div></div><button type="submit" class="btn btn-primary mt-3" wire:loading.attr="disabled">Ajukan ke Finance</button></form></div></div>
    @endcan
    <div class="row g-3">
        @forelse($handovers as $handover)
            <div class="col-12 col-xl-6" wire:key="finance-handover-{{ $handover->id }}"><div class="card h-100"><div class="card-body">
                <div class="d-flex justify-content-between gap-2 mb-1"><strong>{{ $handover->number }}</strong><span class="badge {{ $handover->status === 'confirmed' ? 'bg-light-success text-success' : 'bg-light-warning text-warning' }}">{{ ['pending' => 'Menunggu Finance', 'confirmed' => 'Diterima Finance', 'cancelled' => 'Dibatalkan'][$handover->status] ?? $handover->status }}</span></div>
                <div class="small text-muted mb-2">{{ $handover->created_at->format('d/m/Y H:i') }} · {{ $handover->requester?->name }}</div>
                <div>Diajukan: <strong>Rp {{ \App\Support\DisplayNumber::format((float) $handover->requested_amount, 2, ',', '.') }}</strong></div>
                @if($handover->notes)<p class="small mt-2 mb-2">{{ $handover->notes }}</p>@endif
                @if($handover->status === 'confirmed')
                    <div class="small">Fisik diterima: Rp {{ \App\Support\DisplayNumber::format((float) $handover->actual_amount, 2, ',', '.') }} · Selisih: Rp {{ \App\Support\DisplayNumber::format((float) $handover->variance_amount, 2, ',', '.') }}</div><div class="small text-muted">{{ $handover->verification_note }} · Diterima {{ $handover->confirmer?->name }} pada {{ $handover->confirmed_at?->format('d/m/Y H:i') }}</div>
                @elseif($handover->status === 'pending')
                    @can('warehouse-finance-handovers-confirm')
                        <div class="mt-3"><label class="form-label" for="finance-actual-{{ $handover->id }}">Nominal fisik</label><input id="finance-actual-{{ $handover->id }}" type="number" inputmode="decimal" min="0.01" step="0.01" wire:model="actualAmounts.{{ $handover->id }}" class="form-control">@error('actualAmounts.'.$handover->id)<div class="small text-danger">{{ $message }}</div>@enderror</div>
                        <div class="mt-2"><label class="form-label" for="finance-note-{{ $handover->id }}">Catatan pemeriksaan (wajib jika selisih)</label><textarea id="finance-note-{{ $handover->id }}" wire:model="verificationNotes.{{ $handover->id }}" rows="2" maxlength="2000" class="form-control"></textarea>@error('verificationNotes.'.$handover->id)<div class="small text-danger">{{ $message }}</div>@enderror</div>
                        <button type="button" class="btn btn-primary mt-3" wire:click="confirm({{ $handover->id }})" wire:confirm="Pastikan nominal fisik sudah dihitung. Konfirmasi penerimaan?" wire:loading.attr="disabled">Konfirmasi terima</button>
                    @endcan
                    @can('warehouse-finance-handovers-create')<button type="button" class="btn btn-outline-danger mt-3" wire:click="cancel({{ $handover->id }})" wire:confirm="Batalkan pengajuan ini?">Batalkan</button>@endcan
                @endif
            </div></div></div>
        @empty<div class="col-12"><div class="card"><div class="card-body text-center text-muted">Belum ada serah terima kas Warehouse ke Finance.</div></div></div>@endforelse
    </div>
</div>
