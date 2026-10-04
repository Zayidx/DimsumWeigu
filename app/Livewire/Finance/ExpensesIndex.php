<?php

namespace App\Livewire\Finance;

use App\Models\ExpenseCategory;
use App\Models\OutletExpense;
use App\Models\Warehouse;
use App\Services\Finance\OperatingCostService;
use App\Support\CsvCell;
use App\Support\OutletAccess;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\WithPagination;

class ExpensesIndex extends Component
{
    use WithPagination;

    protected $paginationTheme = 'bootstrap';

    public string $from = '';

    public string $to = '';

    public bool $showForm = false;

    public string $categoryName = '';

    public array $form = [];

    public function mount(): void
    {
        $this->from = today()->startOfMonth()->toDateString();
        $this->to = today()->toDateString();
    }

    public function updatedFrom(): void
    {
        $this->resetPage();
    }

    public function updatedTo(): void
    {
        $this->resetPage();
    }

    public function create(): void
    {
        $this->permit('expenses-create');
        $this->form = ['warehouse_id' => '', 'expense_category_id' => '', 'amount' => '', 'method' => 'bank-transfer', 'reference' => '', 'description' => '', 'occurred_on' => today()->toDateString(), 'idempotency_key' => (string) Str::uuid()];
        $this->resetValidation();
        $this->showForm = true;
    }

    public function save(OperatingCostService $service): void
    {
        $this->permit('expenses-create');
        $this->resetValidation();
        try {
            $service->expense($this->form);
        } catch (ValidationException $e) {
            foreach ($e->errors() as $key => $messages) {
                $this->addError('form.'.$key, $messages[0]);
            }

            return;
        }
        $this->showForm = false;
        $this->dispatch('toast', type: 'success', message: __('Pengeluaran dicatat.'));
    }

    public function addCategory(OperatingCostService $service): void
    {
        $this->permit('expenses-create');
        $this->validate(['categoryName' => ['required', 'string', 'max:80']]);
        $service->category($this->categoryName);
        $this->categoryName = '';
    }

    public function toggleCategory(int $id, OperatingCostService $service): void
    {
        $service->toggleCategory($id);
    }

    public function cancel(): void
    {
        $this->showForm = false;
        $this->resetValidation();
    }

    public function export()
    {
        $this->permit('expenses-access');
        $this->permit('reports-export');
        $rows = $this->query()->with(['warehouse:id,name', 'user:id,name'])->orderBy('occurred_on')->get();

        return response()->streamDownload(function () use ($rows) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Tanggal', 'Gudang', 'Kategori', 'Jumlah', 'Metode', 'Referensi', 'Keterangan', 'Petugas']);
            foreach ($rows as $row) {
                fputcsv($handle, array_map(CsvCell::safe(...), [$row->occurred_on->toDateString(), $row->warehouse?->name, $row->category, $row->amount, $row->method, $row->reference, $row->description, $row->user?->name]));
            }
            fclose($handle);
        }, 'expenses-'.today()->toDateString().'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function render()
    {
        $this->permit('expenses-access');
        $query = $this->query();

        return view('livewire.finance.expenses-index', [
            'rows' => (clone $query)->with(['warehouse:id,name', 'user:id,name'])->latest('occurred_on')->latest('id')->paginate(20),
            'total' => (clone $query)->sum('amount'),
            'categories' => ExpenseCategory::where('outlet_id', $this->outletId())->orderBy('name')->get(),
            'warehouses' => Warehouse::where('outlet_id', $this->outletId())->where('is_active', true)->orderBy('name')->get(),
        ])->layout('layouts.dashboard', ['title' => __('Pengeluaran outlet')]);
    }

    private function query()
    {
        $this->validate(['from' => ['required', 'date_format:Y-m-d'], 'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from']]);

        return OutletExpense::where('outlet_id', $this->outletId())->whereDate('occurred_on', '>=', $this->from)->whereDate('occurred_on', '<=', $this->to);
    }

    private function outletId(): int
    {
        return app(OutletAccess::class)->current(request())?->id ?? abort(404);
    }

    private function permit(string $permission): void
    {
        abort_unless(auth()->user()?->can($permission), 403);
    }
}
