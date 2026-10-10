<div>
    @if (session('status'))<div class="alert alert-light-success" role="status">{{ session('status') }}</div>@endif

    <div class="d-flex flex-wrap align-items-end justify-content-between gap-3 mb-3">
        <p class="text-muted mb-0">Finance meninjau permintaan di sini. Halaman ini mencatat tindak lanjut dan tidak mengubah data transaksi.</p>
        <div>
            <label for="correction-status-filter" class="form-label">Tampilkan</label>
            <select id="correction-status-filter" wire:model.live="statusFilter" class="form-select">
                <option value="open">Perlu ditindaklanjuti</option>
                <option value="resolved">Selesai</option>
                <option value="rejected">Ditolak</option>
                <option value="all">Semua permintaan</option>
            </select>
        </div>
    </div>

    @forelse($requests as $request)
        <section class="card mb-3" aria-labelledby="correction-request-{{ $request->id }}">
            <div class="card-header d-flex flex-wrap justify-content-between align-items-start gap-2">
                <div>
                    <h2 id="correction-request-{{ $request->id }}" class="h6 mb-1">{{ $request->transaction->number }}</h2>
                    <span class="small text-muted">{{ $request->requester->name }} · {{ $request->created_at->format('d/m/Y H:i') }}</span>
                </div>
                <span class="badge {{ $request->status === 'pending' ? 'bg-light-warning text-warning' : ($request->status === 'in_review' ? 'bg-light-primary text-primary' : ($request->status === 'resolved' ? 'bg-light-success text-success' : 'bg-light-danger text-danger')) }}">{{ ['pending' => 'Menunggu Finance', 'in_review' => 'Sedang ditinjau', 'resolved' => 'Selesai ditangani', 'rejected' => 'Ditolak'][$request->status] ?? $request->status }}</span>
            </div>
            <div class="card-body">
                <div class="d-flex flex-wrap justify-content-between gap-2 mb-3"><span>Nilai transaksi: <strong>Rp {{ \App\Support\DisplayNumber::format($request->transaction->grand_total, 0, ',', '.') }}</strong></span><span class="text-muted">{{ $request->transaction->occurred_at?->format('d/m/Y H:i') }}</span></div>
                <h3 class="small fw-bold">Alasan kasir</h3><p class="mb-3">{{ $request->reason }}</p>
                @if($request->finance_note)
                    <div class="rounded border p-3 mb-3"><span class="small fw-bold">Catatan Finance · {{ $request->handler?->name ?? 'Finance' }}</span><p class="mb-0 mt-1">{{ $request->finance_note }}</p></div>
                @endif
                @if(in_array($request->status, ['pending', 'in_review'], true) && auth()->user()->can('transaction-corrections-update'))
                    <div class="border-top pt-3">
                        <label for="finance-note-{{ $request->id }}" class="form-label">Catatan tindak lanjut</label>
                        <textarea id="finance-note-{{ $request->id }}" wire:model="notes.{{ $request->id }}" class="form-control" rows="2" maxlength="2000" placeholder="Tuliskan hasil peninjauan dan tindak lanjut Finance"></textarea>
                        @error('notes.'.$request->id)<small class="text-danger">{{ $message }}</small>@enderror
                        <div class="d-flex flex-wrap gap-2 mt-2">
                            <button type="button" wire:click="updateStatus({{ $request->id }}, 'in_review')" wire:loading.attr="disabled" class="btn btn-outline-primary">Tandai sedang ditinjau</button>
                            <button type="button" wire:click="updateStatus({{ $request->id }}, 'resolved')" wire:loading.attr="disabled" class="btn btn-primary">Tandai selesai</button>
                            <button type="button" wire:click="updateStatus({{ $request->id }}, 'rejected')" wire:loading.attr="disabled" class="btn btn-outline-danger">Tolak permintaan</button>
                        </div>
                    </div>
                @endif
            </div>
        </section>
    @empty
        <div class="card"><div class="card-body text-center text-muted py-5">Tidak ada permintaan koreksi untuk filter ini.</div></div>
    @endforelse

    @if($requests->hasPages())<div class="card-footer">{{ $requests->links() }}</div>@endif
</div>
