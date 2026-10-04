<?php

namespace Tests\Feature;

use App\Livewire\Finance\ExpensesIndex;
use App\Livewire\Inventory\WasteIndex;
use App\Models\ExpenseCategory;
use App\Models\Outlet;
use App\Models\OutletExpense;
use App\Models\OutletWasteRecord;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Cashier\CashierShiftService;
use App\Services\Finance\OperatingCostService;
use App\Services\Inventory\StockMutationService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class OperatingCostsTest extends TestCase
{
    use RefreshDatabase;

    private Warehouse $warehouse;

    private Product $product;

    private ExpenseCategory $category;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $user = User::create(['name' => 'Cost operator', 'email' => 'cost@example.test', 'password' => 'Test-password-123!', 'is_active' => true]);
        $user->assignRole('super-admin');
        $outlet = Outlet::create(['code' => 'COST', 'slug' => 'cost', 'name' => 'Cost outlet', 'is_active' => true, 'is_sales_enabled' => true]);
        $user->outlets()->attach($outlet->id, ['is_default' => true]);
        $this->warehouse = Warehouse::create(['outlet_id' => $outlet->id, 'code' => 'COST-WH', 'name' => 'Cost warehouse', 'type' => 'branch', 'is_active' => true, 'is_sales_enabled' => true]);
        $unit = Unit::create(['name' => 'Pieces', 'symbol' => 'pcs', 'dimension' => 'count', 'is_active' => true]);
        $this->product = Product::create(['unit_id' => $unit->id, 'sku' => 'COST-1', 'title' => '=Cost product', 'buy_price' => 1000, 'sell_price' => 2000, 'is_active' => true, 'track_stock' => true]);
        $this->actingAs($user)->withSession(['active_outlet_id' => $outlet->id]);
        $this->category = app(OperatingCostService::class)->category('Utilities');
    }

    private function expenseInput(array $changes = []): array
    {
        return array_replace(['warehouse_id' => $this->warehouse->id, 'expense_category_id' => $this->category->id, 'amount' => '250', 'method' => 'bank-transfer', 'reference' => 'BANK-1', 'description' => 'Electricity bill', 'occurred_on' => today()->toDateString(), 'idempotency_key' => (string) Str::uuid()], $changes);
    }

    private function wasteInput(array $changes = []): array
    {
        return array_replace(['warehouse_id' => $this->warehouse->id, 'product_id' => $this->product->id, 'product_batch_id' => null, 'quantity' => '1', 'reason' => 'damaged', 'notes' => 'Seal broken, separated for disposal', 'idempotency_key' => (string) Str::uuid()], $changes);
    }

    private function stock(?ProductBatch $batch = null): void
    {
        app(StockMutationService::class)->mutate($this->product->id, $this->warehouse->id, '3', 'opening', (string) Str::uuid(), productBatchId: $batch?->id);
    }

    public function test_cash_expense_updates_shift_and_cash_ledger_once(): void
    {
        $shift = app(CashierShiftService::class)->open($this->warehouse->outlet_id, $this->warehouse->id, '1000');
        $data = $this->expenseInput(['method' => 'cash', 'reference' => null]);
        $service = app(OperatingCostService::class);
        $expense = $service->expense($data);
        $this->assertSame($expense->id, $service->expense($data)->id);
        $this->assertSame('750.00', $shift->fresh()->expected_cash);
        $this->assertDatabaseCount('outlet_expenses', 1);
        $this->assertDatabaseCount('shift_cash_movements', 1);
        $this->assertDatabaseHas('warehouse_cash_ledgers', ['type' => 'expense', 'amount' => 250, 'balance_after' => 750]);
    }

    public function test_cash_expense_without_shift_or_sufficient_cash_rolls_back(): void
    {
        $service = app(OperatingCostService::class);
        try {
            $service->expense($this->expenseInput(['method' => 'cash']));
            $this->fail('Missing shift accepted');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('method', $e->errors());
        }
        $shift = app(CashierShiftService::class)->open($this->warehouse->outlet_id, $this->warehouse->id, '100');
        try {
            $service->expense($this->expenseInput(['method' => 'cash']));
            $this->fail('Insufficient cash accepted');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('amount', $e->errors());
        }
        $this->assertDatabaseCount('outlet_expenses', 0);
        $this->assertDatabaseCount('shift_cash_movements', 0);
        $this->assertSame('100.00', $shift->fresh()->expected_cash);
    }

    public function test_bank_expense_is_immutable_replay_and_conflicting_payload_fails(): void
    {
        $service = app(OperatingCostService::class);
        $data = $this->expenseInput();
        $expense = $service->expense($data);
        $this->assertSame($expense->id, $service->expense($data)->id);
        $this->assertDatabaseCount('warehouse_cash_ledgers', 0);
        try {
            $service->expense(array_replace($data, ['amount' => '300']));
            $this->fail('Conflicting replay accepted');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('idempotency_key', $e->errors());
        }
        $this->assertSame('250.00', $expense->fresh()->amount);
    }

    public function test_disabled_category_and_future_or_backdated_cash_expense_are_rejected(): void
    {
        $service = app(OperatingCostService::class);
        $service->toggleCategory($this->category->id);
        try {
            $service->expense($this->expenseInput());
            $this->fail('Inactive category accepted');
        } catch (ModelNotFoundException $e) {
            $this->assertSame(ExpenseCategory::class, $e->getModel());
        }
        $service->toggleCategory($this->category->id);
        foreach ([['occurred_on' => today()->addDay()->toDateString()], ['method' => 'cash', 'occurred_on' => today()->subDay()->toDateString()]] as $changes) {
            try {
                $service->expense($this->expenseInput($changes));
                $this->fail('Invalid date accepted');
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('occurred_on', $e->errors());
            }
        }
        $this->assertDatabaseCount('outlet_expenses', 0);
    }

    public function test_waste_debits_stock_and_preserves_original_cost_on_replay(): void
    {
        $this->stock();
        $service = app(OperatingCostService::class);
        $data = $this->wasteInput();
        $waste = $service->waste($data);
        $this->product->update(['buy_price' => 5000]);
        $this->assertSame($waste->id, $service->waste($data)->id);
        $this->assertSame('1000.00', $waste->fresh()->cost_total);
        $this->assertSame('2.000', $this->product->fresh()->stock);
        $this->assertDatabaseHas('product_warehouses', ['warehouse_id' => $this->warehouse->id, 'stock' => 2]);
        $this->assertDatabaseHas('inventory_ledgers', ['type' => 'waste', 'quantity_delta' => -1]);
        $this->assertDatabaseCount('outlet_waste_records', 1);
        $this->assertDatabaseCount('warehouse_cash_ledgers', 0);
    }

    public function test_expired_waste_uses_selected_batch_and_cannot_sell_expired_stock(): void
    {
        $this->product->update(['track_batches' => true]);
        $batch = ProductBatch::create(['product_id' => $this->product->id, 'warehouse_id' => $this->warehouse->id, 'batch_number' => 'EXPIRED', 'quantity_received' => 3, 'quantity_remaining' => 0, 'expires_at' => today()->addDay(), 'status' => 'active']);
        $this->stock($batch);
        $batch->update(['expires_at' => today()->subDay(), 'status' => 'expired']);
        $service = app(OperatingCostService::class);
        $service->waste($this->wasteInput(['product_batch_id' => $batch->id, 'reason' => 'expired']));
        $this->assertSame('2.000', $batch->fresh()->quantity_remaining);
        try {
            app(StockMutationService::class)->mutate($this->product->id, $this->warehouse->id, '-1', 'sale', (string) Str::uuid());
            $this->fail('Expired sale accepted');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('stock', $e->errors());
        }
        $this->assertSame('2.000', $this->product->fresh()->stock);
        $batch->update(['expires_at' => today()->addDay(), 'status' => 'active']);
        try {
            $service->waste($this->wasteInput(['product_batch_id' => $batch->id, 'reason' => 'expired']));
            $this->fail('Nonexpired batch accepted as expired');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('reason', $e->errors());
        }
    }

    public function test_waste_insufficient_stock_rolls_back_and_conflicting_replay_fails(): void
    {
        $service = app(OperatingCostService::class);
        try {
            $service->waste($this->wasteInput());
            $this->fail('Insufficient stock accepted');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('stock', $e->errors());
        }
        $this->assertDatabaseCount('outlet_waste_records', 0);
        $this->stock();
        $data = $this->wasteInput();
        $service->waste($data);
        try {
            $service->waste(array_replace($data, ['quantity' => '2']));
            $this->fail('Conflicting waste replay accepted');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('idempotency_key', $e->errors());
        }
        $this->assertSame('2.000', $this->product->fresh()->stock);
    }

    public function test_forged_cross_outlet_warehouse_is_rejected_even_for_admin(): void
    {
        $other = Outlet::create(['code' => 'OTHER', 'slug' => 'other', 'name' => 'Other outlet', 'is_active' => true]);
        $warehouse = Warehouse::create(['outlet_id' => $other->id, 'code' => 'OTHER-WH', 'name' => 'Other warehouse', 'type' => 'branch', 'is_active' => true]);
        foreach (['expense', 'waste'] as $method) {
            try {
                app(OperatingCostService::class)->{$method}($method === 'expense' ? $this->expenseInput(['warehouse_id' => $warehouse->id]) : $this->wasteInput(['warehouse_id' => $warehouse->id]));
                $this->fail('Cross outlet accepted');
            } catch (ModelNotFoundException $e) {
                $this->assertSame(Warehouse::class, $e->getModel());
            }
        }
        $this->assertDatabaseCount('outlet_expenses', 0);
        $this->assertDatabaseCount('outlet_waste_records', 0);
    }

    public function test_livewire_forms_and_csv_exports_work_with_formula_protection(): void
    {
        $this->get(route('expenses.index'))->assertOk();
        $this->get(route('waste.index'))->assertOk();
        $this->app->rebinding('request', fn ($app, $request) => $request->setLaravelSession($app['session']->driver()));
        $this->stock();
        Livewire::test(ExpensesIndex::class)->call('create')->set('form', $this->expenseInput())->call('save')->assertHasNoErrors()->assertSet('showForm', false)->assertSee('Electricity bill');
        Livewire::test(WasteIndex::class)->call('create')->set('form', $this->wasteInput())->call('save')->assertHasNoErrors()->assertSet('showForm', false)->assertSee('Seal broken');
        $this->assertDatabaseCount('outlet_expenses', 1);
        $this->assertDatabaseCount('outlet_waste_records', 1);
        $page = new WasteIndex;
        $page->mount();
        ob_start();
        $page->export()->sendContent();
        $csv = ob_get_clean();
        $this->assertStringContainsString("'=Cost product", $csv);
        $this->assertStringContainsString('1000.00', $csv);
    }

    public function test_permission_is_checked_by_service_and_livewire_actions(): void
    {
        auth()->user()->syncRoles([]);
        $this->get(route('expenses.index'))->assertForbidden();
        $this->get(route('waste.index'))->assertForbidden();
        foreach (['expense', 'waste'] as $method) {
            try {
                app(OperatingCostService::class)->{$method}($method === 'expense' ? $this->expenseInput() : $this->wasteInput());
                $this->fail('Unauthorized write accepted');
            } catch (HttpException $e) {
                $this->assertSame(403, $e->getStatusCode());
            }
        }
        $this->app->rebinding('request', fn ($app, $request) => $request->setLaravelSession($app['session']->driver()));
        Livewire::test(ExpensesIndex::class)->assertForbidden();
        Livewire::test(WasteIndex::class)->assertForbidden();
    }

    public function test_csv_exports_do_not_include_other_outlet_records(): void
    {
        $other = Outlet::create(['code' => 'CSV-OTHER', 'slug' => 'csv-other', 'name' => 'CSV other', 'is_active' => true]);
        OutletExpense::create(['outlet_id' => $other->id, 'user_id' => auth()->id(), 'category' => 'OTHER-PRIVATE', 'amount' => 9000, 'method' => 'bank-transfer', 'description' => 'SECRET-EXPENSE', 'occurred_on' => today()]);
        OutletWasteRecord::create(['outlet_id' => $other->id, 'warehouse_id' => $this->warehouse->id, 'product_id' => $this->product->id, 'user_id' => auth()->id(), 'quantity' => 1, 'reason' => 'quality', 'notes' => 'SECRET-WASTE']);
        $this->app->rebinding('request', fn ($app, $request) => $request->setLaravelSession($app['session']->driver()));
        foreach ([ExpensesIndex::class, WasteIndex::class] as $component) {
            $page = Livewire::test($component)->call('export')->assertFileDownloaded();
            $csv = base64_decode($page->effects['download']['content']);
            $this->assertStringNotContainsString('SECRET-', $csv);
            $this->assertStringNotContainsString('OTHER-PRIVATE', $csv);
        }
    }

    public function test_batch_expiring_today_can_receive_stock_and_is_not_expired_waste(): void
    {
        $this->product->update(['track_batches' => true]);
        $batch = ProductBatch::create(['product_id' => $this->product->id, 'warehouse_id' => $this->warehouse->id, 'batch_number' => 'TODAY', 'quantity_received' => 3, 'quantity_remaining' => 0, 'expires_at' => today(), 'status' => 'active']);
        $this->stock($batch);
        $this->assertSame('3.000', $batch->fresh()->quantity_remaining);
        try {
            app(OperatingCostService::class)->waste($this->wasteInput(['product_batch_id' => $batch->id, 'reason' => 'expired']));
            $this->fail('Today treated as expired');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('reason', $e->errors());
        }
        $this->assertDatabaseCount('outlet_waste_records', 0);
    }
}
