<?php

namespace App\Services\Finance;

use App\Models\BankAccount;
use App\Models\BankAccountLedger;
use App\Models\Warehouse;
use App\Services\Cashier\WarehouseCashLedgerService;
use App\Support\FixedDecimal;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CashDepositService
{
    public function __construct(private readonly WarehouseCashLedgerService $cash, private readonly BankAccountLedgerService $bank) {}

    public function deposit(Warehouse $source, BankAccount $account, string $amount, string $note, string $idempotencyKey): void
    {
        $cents = FixedDecimal::money($amount);
        if ($source->outlet_id !== null || $source->type !== 'central' || ! $source->is_active || $cents <= 0 || trim($note) === '') {
            throw ValidationException::withMessages(['depositAmount' => __('Gudang sumber atau rincian setoran tidak valid.')]);
        }
        DB::transaction(function () use ($source, $account, $cents, $note, $idempotencyKey): void {
            $lockedSource = Warehouse::query()->whereKey($source->id)->lockForUpdate()->firstOrFail();
            $key = 'cash-deposit:'.$idempotencyKey;
            if (BankAccountLedger::query()->where('idempotency_key', $key)->exists()) {
                $this->bank->credit($account, $cents, 'cash_deposit', Warehouse::class, $source->id, $key, $note);

                return;
            }
            $this->cash->append($lockedSource->id, null, 'bank_deposit', $cents, BankAccount::class, $account->id, $note);
            $this->bank->credit($account, $cents, 'cash_deposit', Warehouse::class, $source->id, $key, $note);
        }, attempts: 3);
    }
}
