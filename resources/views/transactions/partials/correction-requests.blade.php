@can('transaction-corrections-create')
    @php($activeCorrection = $transaction->correctionRequests->first(fn ($request) => in_array($request->status, ['pending', 'in_review'], true)))
    <section class="card mb-3" aria-labelledby="transaction-correction-title">
        <div class="card-body">
            <h2 id="transaction-correction-title" class="h6">Perlu koreksi transaksi?</h2>
            @if($activeCorrection)
                <p class="text-muted mb-0">Permintaan koreksi untuk transaksi ini sedang ditinjau Finance. Status: {{ $activeCorrection->status === 'pending' ? 'Menunggu Finance' : 'Sedang ditinjau' }}.</p>
            @else
                <p class="small text-muted">Kasir tidak dapat mengubah atau menghapus transaksi. Jelaskan koreksi yang diperlukan, lalu Finance akan meninjau permintaan ini.</p>
                <form method="POST" action="{{ route('transactions.correction-requests.store', $transaction) }}">
                    @csrf
                    <label for="correction-reason" class="form-label">Alasan dan rincian koreksi</label>
                    <textarea id="correction-reason" name="reason" class="form-control" rows="3" minlength="10" maxlength="3000" required>{{ old('reason') }}</textarea>
                    @error('reason')<small class="text-danger d-block mt-1">{{ $message }}</small>@enderror
                    <button type="submit" class="btn btn-outline-primary mt-2">Kirim permintaan ke Finance</button>
                </form>
            @endif
        </div>
    </section>
@endcan

@if($transaction->correctionRequests->isNotEmpty())
    <section class="card mb-3" aria-labelledby="transaction-correction-history-title">
        <div class="card-header"><h2 id="transaction-correction-history-title" class="h6 card-title mb-0">Riwayat permintaan koreksi</h2></div>
        <div class="list-group list-group-flush">
            @foreach($transaction->correctionRequests->sortByDesc('created_at') as $request)
                <div class="list-group-item">
                    <div class="d-flex flex-wrap justify-content-between gap-2"><strong>{{ ['pending' => 'Menunggu Finance', 'in_review' => 'Sedang ditinjau', 'resolved' => 'Selesai ditangani', 'rejected' => 'Ditolak'][$request->status] ?? $request->status }}</strong><span class="small text-muted">{{ $request->created_at->format('d/m/Y H:i') }}</span></div>
                    <p class="mb-1 mt-2">{{ $request->reason }}</p>
                    @if($request->finance_note)<p class="small text-muted mb-0">Catatan Finance{{ $request->handler ? ' · '.$request->handler->name : '' }}: {{ $request->finance_note }}</p>@endif
                </div>
            @endforeach
        </div>
    </section>
@endif
