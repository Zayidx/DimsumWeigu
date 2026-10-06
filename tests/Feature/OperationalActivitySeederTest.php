<?php

namespace Tests\Feature;

use App\Models\CashHandover;
use App\Models\GoodsReceivingItem;
use App\Models\Outlet;
use App\Models\Payable;
use App\Models\Product;
use App\Models\ProductionOrder;
use App\Models\ProductionRequest;
use App\Models\ProductWarehouse;
use App\Models\Receivable;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseCashLedger;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\OperationalActivitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class OperationalActivitySeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_operational_activity_is_rejected_in_production(): void
    {
        $this->app->instance('env', 'production');
        $this->expectException(RuntimeException::class);
        app(OperationalActivitySeeder::class)->run();
    }

    public function test_operational_activity_is_opt_in_and_idempotent(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->assertDatabaseMissing('production_requests', ['idempotency_key' => 'operational-demo-v2:request:GAL-BP']);
        $this->assertDatabaseCount('transactions', 0);

        $this->seed(OperationalActivitySeeder::class);
        $this->assertSame(4, ProductionRequest::query()->where('idempotency_key', 'like', 'operational-demo-v2:request:%')->count());
        $this->assertSame(4, ProductionRequest::query()->where('idempotency_key', 'like', 'operational-demo-v2:cashier-stock:request:%')->count());
        $this->assertSame(8, ProductionOrder::query()->where('status', 'completed')->count());
        $this->assertSame(100, Transaction::query()->where('source', 'pos')->count());
        $this->assertSame(4, CashHandover::query()->where('idempotency_key', 'like', 'operational-demo-v2:handover:%')->where('status', 'confirmed')->count());
        $this->assertDatabaseHas('cash_handovers', ['idempotency_key' => 'operational-demo-v2:finance-pickup', 'status' => 'confirmed']);
        $this->assertDatabaseHas('warehouses', ['code' => 'DEMO-KAS-FINANCE', 'name' => 'Kas Finance (Simulasi)']);
        $this->assertDatabaseHas('goods_receiving_items', ['qc_status' => 'partial', 'rejected_quantity' => '1.000']);
        $this->assertSame(1, Payable::query()->where('status', 'partial')->count());
        $this->assertSame(9, Payable::query()->where('status', 'unpaid')->count());
        $this->assertSame(4, Receivable::query()->where('status', 'partial')->count());
        $this->assertSame(4, DB::table('receivable_payments')->where('idempotency_key', 'like', 'operational-demo-v2:receivable:%')->count());
        $this->assertSame(5, WarehouseCashLedger::query()->where('type', 'handover_in')->count());
        $central = Warehouse::query()->where('code', 'PUSAT')->firstOrFail();
        $financeVault = Warehouse::query()->where('code', 'DEMO-KAS-FINANCE')->firstOrFail();
        $this->assertSame('500000.00', (string) WarehouseCashLedger::query()->where('warehouse_id', $central->id)->latest('id')->value('balance_after'));
        $this->assertSame('1500000.00', (string) WarehouseCashLedger::query()->where('warehouse_id', $financeVault->id)->latest('id')->value('balance_after'));
        $plain = Product::query()->where('sku', 'TEST-DPL')->firstOrFail();
        $wortel = Product::query()->where('sku', 'TEST-DWO')->firstOrFail();
        foreach (['GAL-BP', 'GAL-HER', 'PEK-JAYA', 'JL-RAYA-PEK'] as $outletCode) {
            $outlet = Outlet::query()->where('code', $outletCode)->firstOrFail();
            $branch = Warehouse::query()->where('outlet_id', $outlet->id)->where('is_sales_enabled', true)->firstOrFail();
            $this->assertSame('75.000', (string) ProductWarehouse::query()->where('warehouse_id', $branch->id)->where('product_id', $plain->id)->value('stock'));
            $this->assertSame('125.000', (string) ProductWarehouse::query()->where('warehouse_id', $branch->id)->where('product_id', $wortel->id)->value('stock'));
            $sold = DB::table('transaction_details')->join('transactions', 'transactions.id', '=', 'transaction_details.transaction_id')
                ->where('transactions.outlet_id', $outlet->id)->where('transactions.source', 'pos')->sum('transaction_details.quantity');
            $this->assertSame(25.0, round((float) $sold, 3));
            $this->assertSame(25, Transaction::query()->where('outlet_id', $outlet->id)->where('source', 'pos')->count());
            $this->assertSame(0, DB::table('transaction_details')->join('transactions', 'transactions.id', '=', 'transaction_details.transaction_id')
                ->where('transactions.outlet_id', $outlet->id)->where('transactions.source', 'pos')->where('transaction_details.quantity', '<>', '1.000')->count());
            $this->assertSame(1, DB::table('stock_transfers')->where('destination_warehouse_id', $branch->id)
                ->where('receive_idempotency_key', 'operational-demo-v2:cashier-stock:'.$outletCode.':transfer')->count());
        }
        $this->assertSame('0.000', (string) ProductWarehouse::query()->where('warehouse_id', $central->id)->where('product_id', $plain->id)->value('stock'));
        $this->assertSame('0.000', (string) ProductWarehouse::query()->where('warehouse_id', $central->id)->where('product_id', $wortel->id)->value('stock'));
        $this->assertSame(0, DB::table('transaction_details')->where('unit_name', 'pcs')->count());

        $counts = [ProductionRequest::count(), ProductionOrder::count(), Transaction::count(), CashHandover::count(), GoodsReceivingItem::count(), Receivable::count()];
        $this->seed(OperationalActivitySeeder::class);
        $this->assertSame($counts, [ProductionRequest::count(), ProductionOrder::count(), Transaction::count(), CashHandover::count(), GoodsReceivingItem::count(), Receivable::count()]);
    }

    public function test_operational_activity_preserves_existing_sales(): void
    {
        $this->seed(DatabaseSeeder::class);
        $outlet = Outlet::query()->where('code', 'GAL-BP')->firstOrFail();
        $warehouse = Warehouse::query()->where('code', 'WH-GAL-BP')->firstOrFail();
        $cashier = User::query()->where('email', 'cashier@gmail.com')->firstOrFail();
        Transaction::query()->create([
            'number' => 'EXISTING-SALE', 'outlet_id' => $outlet->id, 'warehouse_id' => $warehouse->id,
            'user_id' => $cashier->id, 'status' => 'completed', 'payment_status' => 'paid',
            'grand_total' => '1000', 'occurred_at' => now(),
        ]);

        app(OperationalActivitySeeder::class)->run();

        $this->assertDatabaseHas('transactions', ['number' => 'EXISTING-SALE']);
        $this->assertDatabaseHas('cash_handovers', ['idempotency_key' => 'operational-demo-v2:finance-pickup', 'status' => 'confirmed']);
    }
}
