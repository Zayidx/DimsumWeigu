<div>
    <div class="mb-3"><h4 class="mb-1">Penerimaan kas outlet</h4><p class="text-muted mb-0">Hitung uang fisik sebelum mengonfirmasi. Saldo outlet dan Gudang Bersama berubah saat penerimaan selesai.</p></div>
    <div class="row g-2 mb-3"><div class="col-12 col-sm-6"><div class="card"><div class="card-body"><span class="small text-muted d-block">Saldo kas Gudang Bersama</span><strong>Rp {{ \App\Support\DisplayNumber::format((float) $centralBalance, 2, ',', '.') }}</strong></div></div></div><div class="col-12 col-sm-6"><div class="card"><div class="card-body"><span class="small text-muted d-block">Diajukan, belum diterima fisik</span><strong>Rp {{ \App\Support\DisplayNumber::format($pendingCents / 100, 2, ',', '.') }}</strong></div></div></div></div>
    <div class="row g-3">
        @forelse($handovers as $handover)
            <div class="col-12 col-xl-6" wire:key="cash-receipt-{{ $handover->id }}"><div class="card h-100"><div class="card-body">
                <div class="d-flex justify-content-between gap-2 mb-2"><strong>{{ $handover->number }}</strong><span class="badge {{ $handover->status === 'confirmed' ? 'bg-light-success text-success' : 'bg-light-warning text-warning' }}">{{ $handover->status === 'confirmed' ? 'Diterima' : 'Menunggu pemeriksaan' }}</span></div>
                <div class="small text-muted mb-2">{{ $handover->source?->outlet?->name }} · {{ $handover->requester?->name }} · {{ $handover->created_at->format('d/m/Y H:i') }}</div>
                <div>Nominal diajukan: <strong>Rp {{ \App\Support\DisplayNumber::format((float) $handover->amount, 2, ',', '.') }}</strong></div>
                @if($handover->notes)<p class="small mt-2 mb-2">Catatan kasir: {{ $handover->notes }}</p>@endif
                @if($handover->status === 'pending')
                    <div class="mt-3"><label class="form-label" for="physical-{{ $handover->id }}">Nominal fisik diterima</label><input id="physical-{{ $handover->id }}" type="number" inputmode="decimal" min="0.01" step="0.01" wire:model="actualAmounts.{{ $handover->id }}" class="form-control">@error('actualAmounts.'.$handover->id)<div class="small text-danger">{{ $message }}</div>@enderror</div>
                    <div class="mt-2"><label class="form-label" for="verification-{{ $handover->id }}">Catatan pemeriksaan (wajib jika selisih)</label><textarea id="verification-{{ $handover->id }}" wire:model="verificationNotes.{{ $handover->id }}" rows="2" maxlength="2000" class="form-control"></textarea>@error('verificationNotes.'.$handover->id)<div class="small text-danger">{{ $message }}</div>@enderror</div>
                    <button type="button" class="btn btn-primary mt-3" wire:click="confirmPhysical({{ $handover->id }})" wire:confirm="Pastikan uang fisik sudah dihitung. Konfirmasi penerimaan kas ini?" wire:loading.attr="disabled">Konfirmasi penerimaan fisik</button>
                @else
                    <div class="small mt-2">Fisik diterima: Rp {{ \App\Support\DisplayNumber::format((float) ($handover->actual_amount ?? $handover->amount), 2, ',', '.') }} · Selisih: Rp {{ \App\Support\DisplayNumber::format((float) ($handover->variance_amount ?? 0), 2, ',', '.') }}</div>
                    @if($handover->verification_note)<div class="small text-muted">{{ $handover->verification_note }}</div>@endif
                    <div class="small text-muted">Diperiksa: {{ $handover->receiver?->name }}</div>
                @endif
            </div></div></div>
        @empty<div class="col-12"><div class="card"><div class="card-body text-center text-muted">Belum ada serah terima kas outlet.</div></div></div>@endforelse
    </div>
</div>
