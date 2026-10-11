<?php

namespace Tests\Feature;

use App\Livewire\Finance\WarehouseFinanceHandoversIndex;
use App\Models\Outlet;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseFinanceCashHandover;
use App\Services\Cashier\CashHandoverService;
use App\Services\Cashier\CashierShiftService;
use App\Services\Cashier\WarehouseCashLedgerService;
use App\Services\Finance\WarehouseFinanceHandoverService;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class WarehouseFinanceHandoverTest extends TestCase
{
    use RefreshDatabase;

    public function test_pending_money_is_reserved_and_finance_counts_only_physical_receipt(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(UserSeeder::class);
        $warehouseUser = User::where('email', 'warehouse@gmail.com')->firstOrFail();
        $finance = User::where('email', 'finance@gmail.com')->firstOrFail();
        $outlet = Outlet::where('code', 'GAL-BP')->firstOrFail();
        $central = Warehouse::where('code', 'PUSAT')->firstOrFail();
        $this->actingAs($warehouseUser)->withSession(['active_outlet_id' => $outlet->id, 'auth.password_confirmed_at' => now()->timestamp]);
        app(WarehouseCashLedgerService::class)->append($central->id, null, 'cash_in', 100000, null, null, 'Kas dari outlet');
        Livewire::test(WarehouseFinanceHandoversIndex::class)->assertSee('Kas Warehouse ke Finance');
        $service = app(WarehouseFinanceHandoverService::class);
        $handover = $service->request('500', 'Setor fisik ke Finance', 'warehouse-finance-001');
        $this->assertSame($handover->id, $service->request('500', 'Setor fisik ke Finance', 'warehouse-finance-001')->id);
        $this->assertSame(1000.0, (float) DB::table('warehouse_cash_ledgers')->where('warehouse_id', $central->id)->latest('id')->value('balance_after'));
        try {
            $service->request('600', 'Pengajuan kedua', 'warehouse-finance-002');
            $this->fail('Dana pending diajukan dua kali.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('amount', $e->errors());
        }

        $this->actingAs($finance)->withSession(['active_outlet_id' => $outlet->id, 'auth.password_confirmed_at' => now()->timestamp]);
        $confirmed = $service->confirm($handover->id, '480', 'Selisih kurang Rp20 setelah dihitung');
        $this->assertSame($confirmed->id, $service->confirm($handover->id, '480', 'Selisih kurang Rp20 setelah dihitung')->id);
        $this->assertSame('confirmed', $confirmed->status);
        $this->assertSame('-20.00', $confirmed->variance_amount);
        $this->assertDatabaseHas('warehouse_cash_ledgers', ['warehouse_id' => $central->id, 'type' => 'finance_handover_out', 'amount' => 480, 'balance_after' => 520]);
        $this->assertDatabaseHas('finance_cash_ledgers', ['warehouse_finance_cash_handover_id' => $handover->id, 'amount' => 480, 'balance_after' => 480]);
        $this->assertSame(1, DB::table('finance_cash_ledgers')->count());
    }

    public function test_pending_handover_can_be_cancelled_without_changing_ledger(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(UserSeeder::class);
        $warehouseUser = User::where('email', 'warehouse@gmail.com')->firstOrFail();
        $outlet = Outlet::where('code', 'GAL-BP')->firstOrFail();
        $central = Warehouse::where('code', 'PUSAT')->firstOrFail();
        $this->actingAs($warehouseUser)->withSession(['active_outlet_id' => $outlet->id, 'auth.password_confirmed_at' => now()->timestamp]);
        app(WarehouseCashLedgerService::class)->append($central->id, null, 'cash_in', 100000, null, null, 'Kas dari outlet');
        $service = app(WarehouseFinanceHandoverService::class);
        $handover = $service->request('500', 'Pengajuan dibatalkan', 'warehouse-finance-cancel-001');
        $service->cancel($handover->id);
        $this->assertSame('cancelled', WarehouseFinanceCashHandover::findOrFail($handover->id)->status);
        $this->assertSame(1, DB::table('warehouse_cash_ledgers')->where('warehouse_id', $central->id)->count());
        $this->assertSame(0, DB::table('finance_cash_ledgers')->count());
        $this->assertSame('pending', $service->request('900', 'Pengajuan pengganti', 'warehouse-finance-cancel-002')->status);
    }

    public function test_cash_moves_from_cashier_to_warehouse_to_finance_without_new_revenue(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(UserSeeder::class);
        $cashier = User::where('email', 'cashier@gmail.com')->firstOrFail();
        $warehouseUser = User::where('email', 'warehouse@gmail.com')->firstOrFail();
        $finance = User::where('email', 'finance@gmail.com')->firstOrFail();
        $outlet = Outlet::where('code', 'GAL-BP')->firstOrFail();
        $branch = Warehouse::where('outlet_id', $outlet->id)->firstOrFail();
        $central = Warehouse::where('code', 'PUSAT')->firstOrFail();
        $this->actingAs($cashier)->withSession(['active_outlet_id' => $outlet->id, 'auth.password_confirmed_at' => now()->timestamp]);
        app(CashierShiftService::class)->open($outlet->id, $branch->id, '1000');
        $cashierHandover = app(CashHandoverService::class)->request($branch->id, $central->id, '200', 'Kas penjualan outlet', 'cashier-warehouse-flow-001');
        $this->actingAs($warehouseUser)->withSession(['active_outlet_id' => $outlet->id, 'auth.password_confirmed_at' => now()->timestamp]);
        app(CashHandoverService::class)->confirmPhysical($cashierHandover->id, '200');
        $warehouseHandover = app(WarehouseFinanceHandoverService::class)->request('150', 'Serah terima kas outlet', 'warehouse-finance-flow-001');
        $this->actingAs($finance)->withSession(['active_outlet_id' => $outlet->id, 'auth.password_confirmed_at' => now()->timestamp]);
        app(WarehouseFinanceHandoverService::class)->confirm($warehouseHandover->id, '150');

        $this->assertDatabaseHas('warehouse_cash_ledgers', ['warehouse_id' => $central->id, 'type' => 'handover_in', 'amount' => 200]);
        $this->assertDatabaseHas('warehouse_cash_ledgers', ['warehouse_id' => $central->id, 'type' => 'finance_handover_out', 'amount' => 150, 'balance_after' => 50]);
        $this->assertDatabaseHas('finance_cash_ledgers', ['warehouse_finance_cash_handover_id' => $warehouseHandover->id, 'amount' => 150]);
        $this->assertSame(1, DB::table('finance_cash_ledgers')->count());
    }
}
