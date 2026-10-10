<?php

namespace App\Services\Finance;

use App\Models\CashierShift;
use App\Models\ExpenseCategory;
use App\Models\OutletExpense;
use App\Models\OutletWasteRecord;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\ShiftCashMovement;
use App\Models\Warehouse;
use App\Services\Cashier\CashierShiftService;
use App\Services\Cashier\WarehouseCashLedgerService;
use App\Services\Inventory\StockMutationService;
use App\Support\AuditLogger;
use App\Support\FixedDecimal;
use App\Support\OutletAccess;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class OperatingCostService
{
    public function __construct(private readonly AuditLogger $audit, private readonly WarehouseCashLedgerService $cash, private readonly StockMutationService $stock) {}

    public function category(string $name): ExpenseCategory
    {
        $this->permit('expenses-create');
        $name = trim($name);
        Validator::make(['name' => $name], ['name' => ['required', 'string', 'max:80']])->validate();

        return DB::transaction(function () use ($name) {
            $outlet = $this->outletId();
            $category = ExpenseCategory::firstOrCreate(['outlet_id' => $outlet, 'name' => $name], ['is_active' => true]);
            if ($category->wasRecentlyCreated) {
                $this->audit->record('expense.category_created', $category, null, ['name' => $name]);
            }

            return $category;
        });
    }

    public function toggleCategory(int $id): void
    {
        $this->permit('expenses-create');
        DB::transaction(function () use ($id) {
            $category = ExpenseCategory::where('outlet_id', $this->outletId())->lockForUpdate()->findOrFail($id);
            $before = $category->is_active;
            $category->update(['is_active' => ! $before]);
            $this->audit->record('expense.category_updated', $category, ['is_active' => $before], ['is_active' => $category->is_active]);
        });
    }

    public function expense(array $input): OutletExpense
    {
        $this->permit('expenses-create');
        foreach (['description', 'reference'] as $field) {
            if (isset($input[$field]) && is_string($input[$field])) {
                $input[$field] = trim($input[$field]);
            }
        }
        $data = Validator::make($input, [
            'warehouse_id' => ['required', 'integer'], 'expense_category_id' => ['required', 'integer'],
            'amount' => ['required', 'numeric', 'decimal:0,2', 'gt:0', 'max:999999999999.99'],
            'method' => ['required', auth()->user()?->hasRole('cashier') ? 'in:cash' : 'in:cash,bank-transfer'], 'reference' => ['nullable', 'string', 'max:160', 'required_if:method,bank-transfer'],
            'description' => ['required', 'string', 'max:2000'], 'occurred_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'idempotency_key' => ['required', 'string', 'max:96'],
        ])->validate();
        $data['amount'] = FixedDecimal::moneyText(FixedDecimal::money((string) $data['amount']));
        $data['warehouse_id'] = (int) $data['warehouse_id'];
        $data['expense_category_id'] = (int) $data['expense_category_id'];
        $data['reference'] = $data['reference'] ?? null;
        $fingerprint = $this->fingerprint($data);

        return DB::transaction(function () use ($data, $fingerprint) {
            $warehouse = $this->warehouse((int) $data['warehouse_id']);
            if ($existing = OutletExpense::where('idempotency_key', $data['idempotency_key'])->first()) {
                $this->replay($existing, $warehouse->outlet_id, $fingerprint);

                return $existing;
            }
            $category = ExpenseCategory::where('outlet_id', $warehouse->outlet_id)->where('is_active', true)->lockForUpdate()->findOrFail($data['expense_category_id']);
            $shift = null;
            if ($data['method'] === 'cash') {
                if ($data['occurred_on'] !== today()->toDateString()) {
                    throw ValidationException::withMessages(['occurred_on' => __('Pengeluaran kas shift harus dicatat pada hari ini.')]);
                }
                $shift = CashierShift::where('open_user_id', auth()->id())->where('outlet_id', $warehouse->outlet_id)->where('warehouse_id', $warehouse->id)->where('status', 'open')->lockForUpdate()->first();
                if (! $shift) {
                    throw ValidationException::withMessages(['method' => __('Buka shift pada gudang ini sebelum mencatat pengeluaran tunai.')]);
                }
            }
            $expense = OutletExpense::create($data + [
                'outlet_id' => $warehouse->outlet_id, 'user_id' => auth()->id(), 'category' => $category->name,
                'cashier_shift_id' => $shift?->id, 'request_fingerprint' => $fingerprint,
            ]);
            if ($shift) {
                $cents = FixedDecimal::money($expense->amount);
                ShiftCashMovement::create(['cashier_shift_id' => $shift->id, 'user_id' => auth()->id(), 'type' => 'out', 'amount' => $expense->amount, 'reference' => OutletExpense::class.':'.$expense->id, 'note' => $expense->description]);
                $this->cash->append($warehouse->id, $shift->id, 'expense', $cents, OutletExpense::class, $expense->id, $expense->description);
                $shift->update(['expected_cash' => FixedDecimal::moneyText(app(CashierShiftService::class)->expectedCashCents($shift))]);
            }
            $this->audit->record('expense.recorded', $expense, null, ['warehouse_id' => $warehouse->id, 'category' => $category->name, 'amount' => $expense->amount, 'method' => $expense->method]);

            return $expense;
        }, attempts: 3);
    }

    public function waste(array $input): OutletWasteRecord
    {
        $this->permit('waste-create');
        if (isset($input['notes']) && is_string($input['notes'])) {
            $input['notes'] = trim($input['notes']);
        }
        $data = Validator::make($input, [
            'warehouse_id' => ['required', 'integer'], 'product_id' => ['required', 'integer'], 'product_batch_id' => ['nullable', 'integer'],
            'quantity' => ['required', 'numeric', 'decimal:0,3', 'gt:0', 'max:999999999999.999'],
            'reason' => ['required', 'in:expired,damaged,quality,operational'], 'notes' => ['required', 'string', 'max:2000'],
            'idempotency_key' => ['required', 'string', 'max:96'],
        ])->validate();
        $data['quantity'] = FixedDecimal::quantityText(FixedDecimal::quantity((string) $data['quantity']));
        $data['warehouse_id'] = (int) $data['warehouse_id'];
        $data['product_id'] = (int) $data['product_id'];
        $data['product_batch_id'] = isset($data['product_batch_id']) ? (int) $data['product_batch_id'] : null;
        $fingerprint = $this->fingerprint($data);

        return DB::transaction(function () use ($data, $fingerprint) {
            $warehouse = $this->warehouse((int) $data['warehouse_id']);
            if ($existing = OutletWasteRecord::where('idempotency_key', $data['idempotency_key'])->first()) {
                $this->replay($existing, $warehouse->outlet_id, $fingerprint);

                return $existing;
            }
            $product = Product::withTrashed()->lockForUpdate()->findOrFail($data['product_id']);
            if (! $product->track_stock) {
                throw ValidationException::withMessages(['product_id' => __('Produk ini tidak memiliki stok yang dapat dibuang.')]);
            }
            if ($product->track_batches) {
                if (! $data['product_batch_id']) {
                    throw ValidationException::withMessages(['product_batch_id' => __('Pilih batch yang benar-benar dibuang.')]);
                }
                $batch = ProductBatch::where('product_id', $product->id)->where('warehouse_id', $warehouse->id)->lockForUpdate()->findOrFail($data['product_batch_id']);
                if ($data['reason'] === 'expired' && (! $batch->expires_at || ! $batch->expires_at->lt(today()))) {
                    throw ValidationException::withMessages(['reason' => __('Batch ini belum kedaluwarsa.')]);
                }
            } elseif ($data['product_batch_id'] || $data['reason'] === 'expired') {
                throw ValidationException::withMessages(['product_batch_id' => __('Waste kedaluwarsa harus memiliki batch dengan tanggal kedaluwarsa.')]);
            }
            $cost = FixedDecimal::money((string) $product->buy_price);
            $value = (int) round($cost * FixedDecimal::quantity($data['quantity']) / 1000);
            if ($value > 99999999999999) {
                throw ValidationException::withMessages(['quantity' => __('Nilai waste melewati batas penyimpanan.')]);
            }
            $waste = OutletWasteRecord::create($data + ['outlet_id' => $warehouse->outlet_id, 'user_id' => auth()->id(), 'unit_cost' => FixedDecimal::moneyText($cost), 'cost_total' => FixedDecimal::moneyText($value), 'request_fingerprint' => $fingerprint]);
            $this->stock->mutate($product->id, $warehouse->id, '-'.$data['quantity'], 'waste', 'waste:'.$waste->id, $data['notes'], $data['product_batch_id'], OutletWasteRecord::class, $waste->id);
            $this->audit->record('waste.recorded', $waste, null, ['warehouse_id' => $warehouse->id, 'product_id' => $product->id, 'quantity' => $waste->quantity, 'cost_total' => $waste->cost_total, 'reason' => $waste->reason]);

            return $waste;
        }, attempts: 3);
    }

    private function outletId(): int
    {
        $id = (int) session('active_outlet_id');
        app(OutletAccess::class)->require(auth()->user(), $id);

        return $id;
    }

    private function warehouse(int $id): Warehouse
    {
        return Warehouse::where('outlet_id', $this->outletId())->where('is_active', true)->lockForUpdate()->findOrFail($id);
    }

    private function replay($record, int $outletId, string $fingerprint): void
    {
        abort_unless($record->outlet_id === $outletId && $record->user_id === auth()->id(), 404);
        if ($record->request_fingerprint !== $fingerprint) {
            throw ValidationException::withMessages(['idempotency_key' => __('Kunci sudah dipakai untuk data yang berbeda.')]);
        }
    }

    private function fingerprint(array $data): string
    {
        ksort($data);

        return hash('sha256', json_encode($data, JSON_THROW_ON_ERROR));
    }

    private function permit(string $permission): void
    {
        $user = auth()->user();
        abort_unless($user && ($user->hasRole('super-admin') || ! $user->hasRole('cashier')) && $user->can($permission), 403);
    }
}
