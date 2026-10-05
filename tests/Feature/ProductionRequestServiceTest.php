<?php

namespace Tests\Feature;

use App\Livewire\Finance\PayablesIndex;
use App\Livewire\Inventory\IngredientsIndex;
use App\Livewire\Inventory\ProductionOrdersIndex;
use App\Livewire\Inventory\ProductionRequestsIndex;
use App\Livewire\Inventory\RecipesIndex;
use App\Models\Ingredient;
use App\Models\IngredientPurchaseOrder;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Cashier\CartService;
use App\Services\Cashier\CashierShiftService;
use App\Services\Cashier\CheckoutService;
use App\Services\Inventory\StockTransferService;
use App\Services\Production\ProductionOrderService;
use App\Services\Production\ProductionRequestService;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\HppWorkbookSeeder;
use Database\Seeders\TestDataSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class ProductionRequestServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_request_reserves_available_material_and_receipt_records_only_qc_accepted_stock(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(UserSeeder::class);
        $this->seed(TestDataSeeder::class);
        $warehouseUser = User::where('email', 'warehouse@gmail.com')->firstOrFail();
        $finance = User::where('email', 'finance@gmail.com')->firstOrFail();
        $outlet = Outlet::where('code', 'GAL-BP')->firstOrFail();
        $warehouse = Warehouse::where('code', 'PUSAT')->firstOrFail();
        $product = Product::where('sku', 'TEST-DME')->firstOrFail();
        $this->actingAs($warehouseUser);
        DB::table('ingredient_warehouses')->where('warehouse_id', $warehouse->id)->update(['stock' => 0, 'reserved_stock' => 0]);
        Ingredient::query()->where('code', 'like', 'TEST-%')->update(['stock' => 0]);
        $requestKey = 'test-request-'.Str::uuid();
        $request = app(ProductionRequestService::class)->create($warehouseUser, $outlet->id, $warehouse->id, [['product_id' => $product->id, 'quantity' => '2']], null, $requestKey);
        $replayedRequest = app(ProductionRequestService::class)->create($warehouseUser, $outlet->id, $warehouse->id, [['product_id' => $product->id, 'quantity' => '2']], null, $requestKey);
        $this->assertSame($request->id, $replayedRequest->id);
        $this->assertSame('requested', $request->status);
        $this->assertGreaterThan(0, $request->materials->count());
        $this->assertSame(0.0, (float) $request->materials->first()->reserved_quantity);

        $supplier = Supplier::create(['code' => 'SUP-TEST', 'name' => 'Supplier Test', 'is_active' => true]);
        $this->actingAs($finance);
        $purchase = app(ProductionRequestService::class)->approveAndPurchase($finance, $request->id, $supplier->id);
        $this->assertInstanceOf(IngredientPurchaseOrder::class, $purchase);
        $receivingLines = $purchase->items->map(fn ($item, $index) => [
            'purchase_order_item_id' => $item->id, 'sent_quantity' => (string) $item->quantity,
            'accepted_quantity' => $index === 0 ? '0' : (string) $item->quantity,
            'qc_status' => $index === 0 ? 'unusable' : 'good',
        ])->all();
        $receiptKey = 'test-receipt-'.Str::uuid();
        $this->actingAs($warehouseUser);
        $receiving = app(ProductionRequestService::class)->receive($warehouseUser, $purchase->id, $receivingLines, null, null, $receiptKey);
        $replayedReceipt = app(ProductionRequestService::class)->receive($warehouseUser, $purchase->id, $receivingLines, null, null, $receiptKey);
        $this->assertSame($receiving->id, $replayedReceipt->id);
        $this->assertSame(count($receivingLines), $receiving->items->count());
        $this->assertSame(1, DB::table('payables')->where('ingredient_goods_receiving_id', $receiving->id)->count());
        $badIngredientId = $purchase->items->first()->ingredient_id;
        $this->assertSame(0.0, (float) DB::table('ingredient_warehouses')->where('ingredient_id', $badIngredientId)->where('warehouse_id', $warehouse->id)->value('stock'));
    }

    public function test_accepted_material_can_be_consumed_into_actual_finished_stock(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(UserSeeder::class);
        $this->seed(TestDataSeeder::class);
        $warehouseUser = User::where('email', 'warehouse@gmail.com')->firstOrFail();
        $finance = User::where('email', 'finance@gmail.com')->firstOrFail();
        $this->actingAs($warehouseUser);
        $outlet = Outlet::where('code', 'GAL-BP')->firstOrFail();
        $warehouse = Warehouse::where('code', 'PUSAT')->firstOrFail();
        $product = Product::where('sku', 'TEST-DME')->firstOrFail();
        DB::table('ingredient_warehouses')->where('warehouse_id', $warehouse->id)->update(['stock' => 0, 'reserved_stock' => 0]);
        Ingredient::query()->where('code', 'like', 'TEST-%')->update(['stock' => 0]);
        $request = app(ProductionRequestService::class)->create($warehouseUser, $outlet->id, $warehouse->id, [['product_id' => $product->id, 'quantity' => '2']]);
        $supplier = Supplier::create(['code' => 'SUP-PROD', 'name' => 'Supplier Produksi', 'is_active' => true]);
        $this->actingAs($finance);
        $purchase = app(ProductionRequestService::class)->approveAndPurchase($finance, $request->id, $supplier->id);
        $receipt = $purchase->items->map(fn ($item) => ['purchase_order_item_id' => $item->id, 'sent_quantity' => (string) $item->quantity, 'accepted_quantity' => (string) $item->quantity, 'qc_status' => 'good'])->all();
        $this->actingAs($warehouseUser);
        app(ProductionRequestService::class)->receive($warehouseUser, $purchase->id, $receipt);
        $order = app(ProductionOrderService::class)->startFromRequest($warehouseUser, $request->id);
        $materials = $order->items->flatMap->materials->map(fn ($item) => ['production_order_material_id' => $item->id, 'actual_quantity' => (string) $item->planned_quantity])->all();
        $outputs = $order->items->map(fn ($item) => ['production_order_item_id' => $item->id, 'actual_output' => '1.500'])->all();
        $completed = app(ProductionOrderService::class)->complete($warehouseUser, $order->id, $materials, $outputs);

        $this->assertSame('completed', $completed->status);
        $this->assertSame('1.500', $completed->items->first()->produced_quantity);
        $this->assertDatabaseHas('product_warehouses', ['product_id' => $product->id, 'warehouse_id' => $warehouse->id, 'stock' => 1.5]);
        $this->assertDatabaseHas('ingredient_inventory_ledgers', ['type' => 'production_consumption', 'reference_id' => $order->id]);

        $outletWarehouse = Warehouse::where('outlet_id', $outlet->id)->firstOrFail();
        $transferService = app(StockTransferService::class);
        $transfer = $transferService->createDraft($warehouse->id, $outletWarehouse->id, [['product_id' => $product->id, 'quantity' => '1.500']]);
        $transferService->send($transfer->id);
        $transferService->receive($transfer->id, 'test-outlet-receipt-'.Str::uuid());
        $this->assertDatabaseHas('product_warehouses', ['product_id' => $product->id, 'warehouse_id' => $outletWarehouse->id, 'stock' => 1.5]);

        $cashier = User::where('email', 'cashier@gmail.com')->firstOrFail();
        $this->actingAs($cashier)->withSession(['active_outlet_id' => $outlet->id]);
        $shift = app(CashierShiftService::class)->open($outlet->id, $outletWarehouse->id, '100000');
        $cartService = app(CartService::class);
        $cart = $cartService->activeFor($shift);
        $cartService->add($cart, $product->id);
        $transaction = app(CheckoutService::class)->cash($cart, '10000');
        $this->assertSame('completed', $transaction->status);
        $this->assertSame(1, (int) $transaction->details()->count());
        $this->assertDatabaseHas('product_warehouses', ['product_id' => $product->id, 'warehouse_id' => $outletWarehouse->id, 'stock' => 0.5]);
        $this->assertGreaterThan(0, (float) $transaction->details()->first()->unit_cost);
    }

    public function test_finance_approval_uses_reserved_stock_without_creating_an_unneeded_purchase(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(UserSeeder::class);
        $this->seed(TestDataSeeder::class);
        $warehouseUser = User::where('email', 'warehouse@gmail.com')->firstOrFail();
        $finance = User::where('email', 'finance@gmail.com')->firstOrFail();
        $outlet = Outlet::where('code', 'GAL-BP')->firstOrFail();
        $central = Warehouse::where('code', 'PUSAT')->firstOrFail();
        $product = Product::where('sku', 'TEST-DOR')->firstOrFail();
        $this->actingAs($warehouseUser);
        $request = app(ProductionRequestService::class)->create($warehouseUser, $outlet->id, $central->id, [['product_id' => $product->id, 'quantity' => '1']]);
        $this->assertSame(0.0, (float) $request->materials->first()->purchase_quantity);
        $this->actingAs($finance);
        $purchase = app(ProductionRequestService::class)->approveAndPurchase($finance, $request->id, null);

        $this->assertNull($purchase);
        $this->assertSame('approved', $request->fresh()->status);
        $this->assertSame(0, IngredientPurchaseOrder::query()->count());
    }

    public function test_requests_reserve_partial_stock_so_it_cannot_be_requested_twice(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(UserSeeder::class);
        $this->seed(TestDataSeeder::class);
        $warehouseUser = User::where('email', 'warehouse@gmail.com')->firstOrFail();
        $outlet = Outlet::where('code', 'GAL-BP')->firstOrFail();
        $central = Warehouse::where('code', 'PUSAT')->firstOrFail();
        $product = Product::where('sku', 'TEST-DOR')->firstOrFail();
        $ingredient = Ingredient::where('code', 'TEST-FROZEN-DIMSUM')->firstOrFail();
        DB::table('ingredient_warehouses')->where('warehouse_id', $central->id)->update(['stock' => 0, 'reserved_stock' => 0]);
        Ingredient::query()->where('code', 'like', 'TEST-%')->update(['stock' => 0]);
        DB::table('ingredient_warehouses')->where('warehouse_id', $central->id)->where('ingredient_id', $ingredient->id)->update(['stock' => 1]);
        $ingredient->update(['stock' => 1]);
        $this->actingAs($warehouseUser);

        $service = app(ProductionRequestService::class);
        $first = $service->create($warehouseUser, $outlet->id, $central->id, [['product_id' => $product->id, 'quantity' => '2']]);
        $firstMaterial = $first->materials->first();
        $this->assertSame(1.0, (float) $firstMaterial->reserved_quantity);
        $this->assertSame(1.0, (float) $firstMaterial->purchase_quantity);

        $second = $service->create($warehouseUser, $outlet->id, $central->id, [['product_id' => $product->id, 'quantity' => '1']]);
        $secondMaterial = $second->materials->first();
        $this->assertSame(0.0, (float) $secondMaterial->reserved_quantity);
        $this->assertSame(1.0, (float) $secondMaterial->purchase_quantity);
        $this->assertSame(1.0, (float) DB::table('ingredient_warehouses')->where('warehouse_id', $central->id)->where('ingredient_id', $ingredient->id)->value('reserved_stock'));
    }

    public function test_hpp_menu_runs_from_warehouse_request_through_finance_production_transfer_and_pos(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(UserSeeder::class);
        $this->seed(HppWorkbookSeeder::class);
        $warehouseUser = User::where('email', 'warehouse@gmail.com')->firstOrFail();
        $finance = User::where('email', 'finance@gmail.com')->firstOrFail();
        $outlet = Outlet::where('code', 'GAL-BP')->firstOrFail();
        $central = Warehouse::where('code', 'PUSAT')->firstOrFail();
        $product = Product::where('sku', 'HPP-MENU-001')->firstOrFail();
        $this->assertDatabaseHas('recipe_versions', ['product_id' => $product->id, 'is_active' => true]);

        $this->actingAs($warehouseUser);
        Livewire::test(IngredientsIndex::class)->assertSee('Master bahan');
        Livewire::test(RecipesIndex::class)->assertSee('Estimasi HPP selalu memakai harga terbaru');
        Livewire::test(ProductionRequestsIndex::class)->assertSee('Permintaan menghitung kebutuhan');
        Livewire::test(ProductionOrdersIndex::class)->assertSee('Siap diproduksi');
        $request = app(ProductionRequestService::class)->create($warehouseUser, $outlet->id, $central->id, [['product_id' => $product->id, 'quantity' => '1']]);
        $supplier = Supplier::create(['code' => 'HPP-SUPPLIER', 'name' => 'Pemasok HPP', 'is_active' => true]);
        $this->actingAs($finance);
        $purchase = app(ProductionRequestService::class)->approveAndPurchase($finance, $request->id, $supplier->id);
        $receivedLines = $purchase->items->map(fn ($item) => ['purchase_order_item_id' => $item->id, 'sent_quantity' => (string) $item->quantity, 'accepted_quantity' => (string) $item->quantity, 'qc_status' => 'good'])->all();
        $this->actingAs($warehouseUser);
        $receiving = app(ProductionRequestService::class)->receive($warehouseUser, $purchase->id, $receivedLines);
        $this->assertSame('received', $request->fresh()->status);
        $this->assertDatabaseHas('payables', ['ingredient_goods_receiving_id' => $receiving->id]);

        $this->actingAs($finance)->withSession(['active_outlet_id' => $outlet->id]);
        Livewire::test(PayablesIndex::class)->assertSee('Bahan baku')->assertSee($receiving->number);
        $payableId = (int) DB::table('payables')->where('ingredient_goods_receiving_id', $receiving->id)->value('id');
        Livewire::test(PayablesIndex::class)->call('openPayment', $payableId)->set('method', 'bank-transfer')->set('reference', 'HPP invoice paid')->call('pay')->assertHasNoErrors();
        $this->assertDatabaseHas('payables', ['id' => $payableId, 'status' => 'paid']);
        $this->actingAs($warehouseUser);
        $order = app(ProductionOrderService::class)->startFromRequest($warehouseUser, $request->id);
        $materialLines = $order->items->flatMap->materials->map(fn ($row) => ['production_order_material_id' => $row->id, 'actual_quantity' => (string) $row->planned_quantity])->all();
        $outputLines = $order->items->map(fn ($row) => ['production_order_item_id' => $row->id, 'actual_output' => '1.000'])->all();
        app(ProductionOrderService::class)->complete($warehouseUser, $order->id, $materialLines, $outputLines);

        $branch = Warehouse::where('outlet_id', $outlet->id)->firstOrFail();
        $transfers = app(StockTransferService::class);
        $transfer = $transfers->createDraft($central->id, $branch->id, [['product_id' => $product->id, 'quantity' => '1.000']]);
        $transfers->send($transfer->id);
        $transfers->receive($transfer->id, 'hpp-transfer-'.Str::uuid());

        $cashier = User::where('email', 'cashier@gmail.com')->firstOrFail();
        $this->actingAs($cashier)->withSession(['active_outlet_id' => $outlet->id]);
        $shift = app(CashierShiftService::class)->open($outlet->id, $branch->id, '100000');
        $cart = app(CartService::class)->activeFor($shift);
        app(CartService::class)->add($cart, $product->id);
        $sale = app(CheckoutService::class)->cash($cart, '20000');

        $this->assertSame('completed', $sale->status);
        $this->assertDatabaseHas('product_warehouses', ['product_id' => $product->id, 'warehouse_id' => $branch->id, 'stock' => 0]);
        $this->assertGreaterThan(0, (float) $sale->details()->first()->unit_cost);
    }
}
