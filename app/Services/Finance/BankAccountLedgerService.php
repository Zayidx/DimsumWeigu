<?php

namespace App\Services\Finance;

use App\Models\BankAccount;
use App\Models\BankAccountLedger;
use App\Support\FixedDecimal;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BankAccountLedgerService
{
    public function balanceCents(BankAccount $account): int
    {
        $last = BankAccountLedger::query()->where('bank_account_id', $account->id)->latest('id')->first();

        return FixedDecimal::money((string) ($last?->balance_after ?? '0'));
    }

    public function credit(BankAccount $account, int $amountCents, string $type, ?string $referenceType, ?int $referenceId, string $key, ?string $note): BankAccountLedger
    {
        return DB::transaction(function () use ($account, $amountCents, $type, $referenceType, $referenceId, $key, $note) {
            $locked = BankAccount::query()->whereKey($account->id)->lockForUpdate()->firstOrFail();
            $existing = BankAccountLedger::query()->where('idempotency_key', $key)->first();
            if ($existing) {
                if ($existing->bank_account_id !== $locked->id || FixedDecimal::money((string) $existing->amount) !== $amountCents || $existing->type !== $type) {
                    throw ValidationException::withMessages(['amount' => __('Kunci transaksi sudah digunakan dengan rincian berbeda.')]);
                }

                return $existing;
            }
            if (! $locked->is_active || $amountCents <= 0) {
                throw ValidationException::withMessages(['amount' => __('Rekening atau jumlah transaksi tidak valid.')]);
            }
            $before = $this->balanceCents($locked);

            return BankAccountLedger::query()->create([
                'bank_account_id' => $locked->id, 'user_id' => auth()->id(), 'type' => $type,
                'amount' => FixedDecimal::moneyText($amountCents), 'balance_before' => FixedDecimal::moneyText($before),
                'balance_after' => FixedDecimal::moneyText($before + $amountCents), 'reference_type' => $referenceType,
                'reference_id' => $referenceId, 'idempotency_key' => $key, 'note' => $note,
            ]);
        }, attempts: 3);
    }
}
