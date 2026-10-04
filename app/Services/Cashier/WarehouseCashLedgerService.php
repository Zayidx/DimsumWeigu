<?php

namespace App\Services\Cashier;

use App\Models\Warehouse;
use App\Models\WarehouseCashLedger;
use App\Support\FixedDecimal;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WarehouseCashLedgerService
{
    public function append(int $warehouseId, ?int $shiftId, string $type, int $amountCents, ?string $referenceType = null, ?int $referenceId = null, ?string $note = null): WarehouseCashLedger
    {
        return DB::transaction(function () use ($warehouseId, $shiftId, $type, $amountCents, $referenceType, $referenceId, $note) {
            $warehouse = Warehouse::query()->lockForUpdate()->findOrFail($warehouseId);
            $last = WarehouseCashLedger::query()->where('warehouse_id', $warehouse->id)->latest('id')->first();
            $before = $last ? FixedDecimal::money((string) $last->balance_after) : 0;
            $isOutflow = in_array($type, ['cash_out', 'sale_refund', 'handover_out', 'supplier_payment', 'expense'], true);
            if ($amountCents < 0 || ($isOutflow && $before < $amountCents)) {
                throw ValidationException::withMessages(['amount' => __('Saldo kas gudang tidak mencukupi.')]);
            }
            $after = $isOutflow ? $before - $amountCents : $before + $amountCents;

            return WarehouseCashLedger::query()->create([
                'warehouse_id' => $warehouse->id, 'cashier_shift_id' => $shiftId, 'user_id' => auth()->id(),
                'type' => $type, 'amount' => FixedDecimal::moneyText($amountCents),
                'balance_before' => FixedDecimal::moneyText($before), 'balance_after' => FixedDecimal::moneyText($after),
                'reference_type' => $referenceType, 'reference_id' => $referenceId, 'note' => $note,
            ]);
        }, attempts: 3);
    }
}
