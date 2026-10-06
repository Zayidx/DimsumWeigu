<?php

namespace Tests\Feature;

use App\Models\BankAccount;
use App\Models\BankAccountLedger;
use App\Models\Warehouse;
use App\Models\WarehouseCashLedger;
use App\Services\Cashier\WarehouseCashLedgerService;
use App\Services\Finance\CashDepositService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CashDepositTest extends TestCase
{
    use RefreshDatabase;

    public function test_cash_deposit_moves_central_cash_to_bank_once_and_rejects_overspending(): void
    {
        $warehouse = Warehouse::query()->create(['outlet_id' => null, 'code' => 'CENTER', 'name' => 'Gudang Pusat', 'type' => 'central', 'is_active' => true, 'is_sales_enabled' => false]);
        $account = BankAccount::query()->create(['bank_name' => 'Bank Test', 'account_name' => 'Finance', 'account_number' => '9988', 'sort_order' => 0, 'is_active' => true]);
        $cash = app(WarehouseCashLedgerService::class);
        $cash->append($warehouse->id, null, 'cash_in', 5000000, null, null, 'Kas awal finance');
        $service = app(CashDepositService::class);

        $service->deposit($warehouse, $account, '30000', 'Setor harian', 'deposit-once');
        $service->deposit($warehouse, $account, '30000', 'Setor harian', 'deposit-once');

        $this->assertSame('20000.00', WarehouseCashLedger::query()->latest('id')->value('balance_after'));
        $this->assertSame('30000.00', BankAccountLedger::query()->latest('id')->value('balance_after'));
        $this->assertSame(1, WarehouseCashLedger::query()->where('type', 'bank_deposit')->count());

        try {
            $service->deposit($warehouse, $account, '30000', 'Setor lebih besar dari kas', 'deposit-too-much');
            $this->fail('Expected insufficient cash validation error.');
        } catch (ValidationException) {
            $this->assertSame(1, WarehouseCashLedger::query()->where('type', 'bank_deposit')->count());
            $this->assertSame(1, BankAccountLedger::query()->where('type', 'cash_deposit')->count());
        }
    }
}
