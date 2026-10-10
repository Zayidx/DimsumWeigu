<?php

namespace App\Livewire\Finance;

use App\Models\TransactionCorrectionRequest;
use App\Support\AuditLogger;
use App\Support\OutletAccess;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\WithPagination;

class TransactionCorrectionRequestsIndex extends Component
{
    use WithPagination;

    public string $statusFilter = 'open';

    public array $notes = [];

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updateStatus(int $requestId, string $status, OutletAccess $access, AuditLogger $audit): void
    {
        abort_unless(auth()->user()->can('transaction-corrections-update'), 403);
        abort_unless(in_array($status, ['in_review', 'resolved', 'rejected'], true), 422);

        $data = $this->validate([
            'notes.'.$requestId => ['required', 'string', 'min:3', 'max:2000'],
        ]);
        $data['notes'][$requestId] = trim($data['notes'][$requestId]);
        if (mb_strlen($data['notes'][$requestId]) < 3) {
            throw ValidationException::withMessages(['notes.'.$requestId => __('Catatan Finance minimal 3 karakter.')]);
        }
        $outletIds = $access->outletsFor(auth()->user())->pluck('id');

        DB::transaction(function () use ($requestId, $status, $data, $outletIds, $audit) {
            $correction = TransactionCorrectionRequest::query()
                ->whereKey($requestId)
                ->whereHas('transaction', fn ($transactions) => $transactions->whereIn('outlet_id', $outletIds))
                ->lockForUpdate()->firstOrFail();

            if (! in_array($correction->status, ['pending', 'in_review'], true)) {
                throw ValidationException::withMessages(['notes.'.$requestId => __('Permintaan ini sudah ditangani.')]);
            }

            $before = $correction->only(['status', 'handled_by', 'finance_note']);
            $correction->forceFill([
                'status' => $status,
                'handled_by' => auth()->id(),
                'finance_note' => trim($data['notes'][$requestId]),
            ])->save();

            $audit->record('transaction.correction_status_updated', $correction, $before, $correction->only(['status', 'handled_by', 'finance_note']), [
                'outlet_id' => $correction->transaction()->value('outlet_id'),
                'transaction_id' => $correction->transaction_id,
            ]);
        });

        unset($this->notes[$requestId]);
        session()->flash('status', __('Status permintaan koreksi diperbarui.'));
    }

    public function render(OutletAccess $access)
    {
        abort_unless(auth()->user()->can('transaction-corrections-access'), 403);
        abort_unless(in_array($this->statusFilter, ['open', 'resolved', 'rejected', 'all'], true), 422);

        $outletIds = $access->outletsFor(auth()->user())->pluck('id');
        $requests = TransactionCorrectionRequest::query()
            ->with(['transaction:id,number,outlet_id,grand_total,occurred_at', 'requester:id,name', 'handler:id,name'])
            ->whereHas('transaction', fn ($transactions) => $transactions->whereIn('outlet_id', $outletIds))
            ->when($this->statusFilter === 'open', fn ($query) => $query->whereIn('status', ['pending', 'in_review']))
            ->when(in_array($this->statusFilter, ['resolved', 'rejected'], true), fn ($query) => $query->where('status', $this->statusFilter))
            ->latest()->paginate(20);

        return view('livewire.finance.transaction-correction-requests-index', compact('requests'))
            ->layout('layouts.dashboard', ['title' => __('Permintaan koreksi transaksi')]);
    }
}
