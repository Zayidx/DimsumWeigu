<?php

namespace Tests\Feature;

use App\Livewire\Finance\PayablesIndex;
use App\Livewire\Inventory\IngredientsIndex;
use App\Livewire\Inventory\ProductionOrdersIndex;
use App\Livewire\Inventory\ProductionRequestsIndex;
use App\Livewire\Inventory\RecipesIndex;
use App\Models\AuditLog;
use App\Models\Ingredient;
use App\Models\IngredientPurchaseOrder;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\ProductionOrder;
use App\Models\ProductionShift;
use App\Models\ProductUnit;
use App\Models\RecipeVersion;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Cashier\CartService;
use App\Services\Cashier\CashierShiftService;
use App\Services\Cashier\CheckoutService;
use App\Services\Inventory\CashierStockMovementService;
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

    public function test_finance_rejection_records_reason_and_releases_reserved_material(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(UserSeeder::class);
        $this->seed(TestDataSeeder::class);
        $warehouseUser = User::where('email', 'warehouse@gmail.com')->firstOrFail();
        $finance = User::where('email', 'finance@gmail.com')->firstOrFail();
        $outlet = Outlet::where('code', 'GAL-BP')->firstOrFail();
        $central = Warehouse::where('code', 'PUSAT')->firstOrFail();
        $product = Product::where('sku', 'TEST-DPL')->firstOrFail();
        $ingredient = Ingredient::where('code', 'TEST-AYAM-FILLET')->firstOrFail();
        DB::table('ingredient_warehouses')->where('warehouse_id', $central->id)->update(['stock' => 0, 'reserved_stock' => 0]);
        Ingredient::query()->where('code', 'like', 'TEST-%')->update(['stock' => 0]);
        DB::table('ingredient_warehouses')->where('warehouse_id', $central->id)->where('ingredient_id', $ingredient->id)->update(['stock' => 1]);
        $ingredient->update(['stock' => 1]);
        $this->actingAs($warehouseUser);
        $request = app(ProductionRequestService::class)->create($warehouseUser, $outlet->id, $central->id, [['product_id' => $product->id, 'quantity' => '2']]);
        $this->assertSame(1.0, (float) DB::table('ingredient_warehouses')->where('warehouse_id', $central->id)->where('ingredient_id', $ingredient->id)->value('reserved_stock'));

        $this->actingAs($finance);
        $rejected = app(ProductionRequestService::class)->reject($finance, $request->id, 'Harga pemasok belum disetujui');
        $this->assertSame('rejected', $rejected->status);
        $this->assertSame('Harga pemasok belum disetujui', $rejected->rejection_reason);
        $this->assertSame(0.0, (float) DB::table('ingredient_warehouses')->where('warehouse_id', $central->id)->where('ingredient_id', $ingredient->id)->value('reserved_stock'));
        $this->assertSame(0.0, (float) $request->materials()->where('ingredient_id', $ingredient->id)->value('reserved_quantity'));
    }

    public function test_restock_can_be_received_in_two_qc_batches_without_duplicate_stock(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(UserSeeder::class);
        $this->seed(TestDataSeeder::class);
        $warehouseUser = User::where('email', 'warehouse@gmail.com')->firstOrFail();
        $finance = User::where('email', 'finance@gmail.com')->firstOrFail();
        $outlet = Outlet::where('code', 'GAL-BP')->firstOrFail();
        $central = Warehouse::where('code', 'PUSAT')->firstOrFail();
        $product = Product::where('sku', 'TEST-DPL')->firstOrFail();
        DB::table('ingredient_warehouses')->where('warehouse_id', $central->id)->update(['stock' => 0, 'reserved_stock' => 0]);
        Ingredient::query()->where('code', 'like', 'TEST-%')->update(['stock' => 0]);
        $this->actingAs($warehouseUser);
        $request = app(ProductionRequestService::class)->create($warehouseUser, $outlet->id, $central->id, [['product_id' => $product->id, 'quantity' => '1']]);
        $supplier = Supplier::create(['code' => 'SUP-PARTIAL', 'name' => 'Pemasok Parsial', 'is_active' => true]);
        $this->actingAs($finance);
        $purchase = app(ProductionRequestService::class)->approveAndPurchase($finance, $request->id, $supplier->id);
        $this->assertGreaterThan(1, $purchase->items->count());
        $first = $purchase->items->first();
        $line = fn ($item) => ['purchase_order_item_id' => $item->id, 'sent_quantity' => (string) $item->quantity, 'accepted_quantity' => (string) $item->quantity, 'qc_status' => 'good'];
        $this->actingAs($warehouseUser);
        app(ProductionRequestService::class)->receive($warehouseUser, $purchase->id, [$line($first)], null, null, 'partial-first');
        $this->assertSame('partially_received', $purchase->fresh()->status);
        $this->assertSame('purchasing', $request->fresh()->status);
        $this->assertSame((float) $first->quantity, (float) DB::table('ingredient_warehouses')->where('warehouse_id', $central->id)->where('ingredient_id', $first->ingredient_id)->value('stock'));

        $remaining = $purchase->items->skip(1)->map($line)->all();
        app(ProductionRequestService::class)->receive($warehouseUser, $purchase->id, $remaining, null, null, 'partial-second');
        $this->assertSame('received', $purchase->fresh()->status);
        $this->assertSame('received', $request->fresh()->status);
        $this->assertSame((float) $first->quantity, (float) DB::table('ingredient_warehouses')->where('warehouse_id', $central->id)->where('ingredient_id', $first->ingredient_id)->value('stock'));
    }

    public function test_request_reserves_available_material_and_receipt_records_only_qc_accepted_stock(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(UserSeeder::class);
        $this->seed(TestDataSeeder::class);
        $warehouseUser = User::where('email', 'warehouse@gmail.com')->firstOrFail();
        $finance = User::where('email', 'finance@gmail.com')->firstOrFail();
        $outlet = Outlet::where('code', 'GAL-BP')->firstOrFail();
        $warehouse = Warehouse::where('code', 'PUSAT')->firstOrFail();
        $product = Product::where('sku', 'TEST-DPL')->firstOrFail();
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
        $firstMaterial = $request->materials->first();
        $purchase = app(ProductionRequestService::class)->approveAndPurchase(
            $finance, $request->id, $supplier->id, null,
            [$firstMaterial->ingredient_id => '123.4567'],
            [$firstMaterial->ingredient_id => number_format((float) $firstMaterial->purchase_quantity + 1, 3, '.', '')],
        );
        $this->assertInstanceOf(IngredientPurchaseOrder::class, $purchase);
        $this->assertSame('123.4567', (string) $purchase->items->firstWhere('ingredient_id', $firstMaterial->ingredient_id)->unit_cost);
        $approvalAudit = AuditLog::query()->where('event', 'ingredient_purchase.ordered')->where('subject_id', $purchase->id)->latest('id')->firstOrFail();
        $this->assertSame('123.45670000', collect($approvalAudit->after['approval_changes'])->firstWhere('ingredient_id', $firstMaterial->ingredient_id)['approved_unit_cost']);
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
        $product = Product::where('sku', 'TEST-DPL')->firstOrFail();
        DB::table('ingredient_warehouses')->where('warehouse_id', $warehouse->id)->update(['stock' => 0, 'reserved_stock' => 0]);
        Ingredient::query()->where('code', 'like', 'TEST-%')->update(['stock' => 0]);
        $request = app(ProductionRequestService::class)->create($warehouseUser, $outlet->id, $warehouse->id, [['product_id' => $product->id, 'quantity' => '2']]);
        $supplier = Supplier::create(['code' => 'SUP-PROD', 'name' => 'Supplier Produksi', 'is_active' => true]);
        $this->actingAs($finance);
        $purchase = app(ProductionRequestService::class)->approveAndPurchase($finance, $request->id, $supplier->id);
        $receipt = $purchase->items->map(fn ($item) => ['purchase_order_item_id' => $item->id, 'sent_quantity' => (string) $item->quantity, 'accepted_quantity' => (string) $item->quantity, 'qc_status' => 'good'])->all();
        $this->actingAs($warehouseUser);
        app(ProductionRequestService::class)->receive($warehouseUser, $purchase->id, $receipt);
        $productionShift = ProductionShift::create(['outlet_id' => $outlet->id, 'warehouse_id' => $warehouse->id, 'user_id' => $warehouseUser->id, 'status' => 'open', 'opened_at' => now()]);
        $order = app(ProductionOrderService::class)->startFromRequest($warehouseUser, $request->id, $productionShift->id);
        $this->assertSame($order->id, app(ProductionOrderService::class)->startFromRequest($warehouseUser, $request->id, $productionShift->id)->id);
        $this->assertSame(0.0, (float) DB::table('ingredient_warehouses')->where('warehouse_id', $warehouse->id)->where('ingredient_id', $order->items->first()->materials->first()->ingredient_id)->value('stock'));
        $this->assertSame($order->items->flatMap->materials->pluck('ingredient_id')->unique()->count(), DB::table('ingredient_inventory_ledgers')->where('reference_type', ProductionOrder::class)->where('reference_id', $order->id)->count());
        $materials = $order->items->flatMap->materials->map(fn ($item) => ['production_order_material_id' => $item->id, 'actual_quantity' => (string) $item->planned_quantity])->all();
        $outputs = $order->items->map(fn ($item) => ['production_order_item_id' => $item->id, 'actual_output' => '1.500', 'variance_note' => 'Hasil kurang saat pembentukan'])->all();
        $completed = app(ProductionOrderService::class)->complete($warehouseUser, $order->id, $materials, $outputs);

        $this->assertSame('completed', $completed->status);
        $this->assertSame('1.500', $completed->items->first()->produced_quantity);
        $this->assertSame('-0.500', $completed->items->first()->variance_quantity);
        $this->assertDatabaseHas('product_warehouses', ['product_id' => $product->id, 'warehouse_id' => $warehouse->id, 'stock' => 1.5]);
        $this->assertDatabaseHas('ingredient_inventory_ledgers', ['type' => 'production_consumption', 'reference_id' => $order->id]);

        $outletWarehouse = Warehouse::where('outlet_id', $outlet->id)->firstOrFail();
        $transferService = app(StockTransferService::class);
        $transfer = $transferService->createDraft($warehouse->id, $outletWarehouse->id, [['product_id' => $product->id, 'quantity' => '1.500']]);
        $transferService->send($transfer->id);
        $transferService->receive($transfer->id, 'test-outlet-receipt-'.Str::uuid());
        $this->assertDatabaseHas('product_warehouses', ['product_id' => $product->id, 'warehouse_id' => $outletWarehouse->id, 'stock' => 1.5]);

        $menu = Product::query()->create(['unit_id' => $product->unit_id, 'sku' => 'TEST-DME-ASSEMBLED', 'title' => 'Dimsum Mentai Paket Test', 'buy_price' => 0, 'sell_price' => 10000, 'stock' => 0, 'is_active' => true, 'is_sellable' => true, 'is_semi_finished' => false, 'track_stock' => false]);
        $packUnit = Unit::query()->firstOrCreate(['code' => 'TEST-PACK'], ['name' => 'Paket test', 'symbol' => 'paket test', 'dimension' => 'count', 'is_active' => true]);
        ProductUnit::query()->create(['product_id' => $menu->id, 'unit_id' => $packUnit->id, 'conversion_factor' => 1, 'sell_price' => 10000, 'is_base' => false, 'is_default' => false]);
        $menuRecipe = RecipeVersion::query()->create(['product_id' => $menu->id, 'unit_id' => $packUnit->id, 'version' => 1, 'starts_at' => now(), 'is_active' => true, 'yield_quantity' => 1]);
        $menuRecipe->productItems()->create(['product_id' => $product->id, 'unit_id' => $product->unit_id, 'quantity' => '0.500', 'waste_percent' => 0]);
        $cashier = User::where('email', 'cashier@gmail.com')->firstOrFail();
        $this->actingAs($cashier)->withSession(['active_outlet_id' => $outlet->id]);
        $shift = app(CashierShiftService::class)->open($outlet->id, $outletWarehouse->id, '100000');
        $cartService = app(CartService::class);
        $cart = $cartService->activeFor($shift);
        $cartService->add($cart, $menu->id, $packUnit->id);
        $transaction = app(CheckoutService::class)->cash($cart, '10000');
        $this->assertSame('completed', $transaction->status);
        $this->assertSame(1, (int) $transaction->details()->count());
        $this->assertDatabaseHas('product_warehouses', ['product_id' => $product->id, 'warehouse_id' => $outletWarehouse->id, 'stock' => 1.0]);
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
        $product = Product::where('sku', 'TEST-DPL')->firstOrFail();
        foreach ($product->recipeVersions()->where('unit_id', $product->unit_id)->where('is_active', true)->firstOrFail()->items as $item) {
            $quantity = (float) $item->quantity;
            DB::table('ingredient_warehouses')->where('warehouse_id', $central->id)->where('ingredient_id', $item->ingredient_id)->update(['stock' => $quantity, 'reserved_stock' => 0]);
            $item->ingredient()->update(['stock' => $quantity]);
        }
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
        $product = Product::where('sku', 'TEST-DPL')->firstOrFail();
        $ingredient = Ingredient::where('code', 'TEST-AYAM-FILLET')->firstOrFail();
        DB::table('ingredient_warehouses')->where('warehouse_id', $central->id)->update(['stock' => 0, 'reserved_stock' => 0]);
        Ingredient::query()->where('code', 'like', 'TEST-%')->update(['stock' => 0]);
        DB::table('ingredient_warehouses')->where('warehouse_id', $central->id)->where('ingredient_id', $ingredient->id)->update(['stock' => 1]);
        $ingredient->update(['stock' => 1]);
        $this->actingAs($warehouseUser);

        $service = app(ProductionRequestService::class);
        $first = $service->create($warehouseUser, $outlet->id, $central->id, [['product_id' => $product->id, 'quantity' => '2']]);
        $firstMaterial = $first->materials->first();
        $this->assertSame(1.0, (float) $firstMaterial->reserved_quantity);
        $this->assertSame(39.0, (float) $firstMaterial->purchase_quantity);

        $second = $service->create($warehouseUser, $outlet->id, $central->id, [['product_id' => $product->id, 'quantity' => '1']]);
        $secondMaterial = $second->materials->first();
        $this->assertSame(0.0, (float) $secondMaterial->reserved_quantity);
        $this->assertSame(20.0, (float) $secondMaterial->purchase_quantity);
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
        $product = Product::where('title', 'Dimsum ori topping wortel')->where('is_semi_finished', true)->firstOrFail();
        $this->assertDatabaseHas('recipe_versions', ['product_id' => $product->id, 'is_active' => true]);

        $this->actingAs($warehouseUser);
        Livewire::test(IngredientsIndex::class)->assertSee('Master bahan');
        Livewire::test(RecipesIndex::class)->assertSee('Atur bahan mentah dan jumlah hasil standar');
        Livewire::test(ProductionRequestsIndex::class)->assertSee('Gudang meminta target produk setengah jadi');
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
        $productionShift = ProductionShift::create(['outlet_id' => $outlet->id, 'warehouse_id' => $central->id, 'user_id' => $warehouseUser->id, 'status' => 'open', 'opened_at' => now()]);
        $order = app(ProductionOrderService::class)->startFromRequest($warehouseUser, $request->id, $productionShift->id);
        $materialLines = $order->items->flatMap->materials->map(fn ($row) => ['production_order_material_id' => $row->id, 'actual_quantity' => (string) $row->planned_quantity])->all();
        $outputLines = $order->items->map(fn ($row) => ['production_order_item_id' => $row->id, 'actual_output' => '1.000'])->all();
        app(ProductionOrderService::class)->complete($warehouseUser, $order->id, $materialLines, $outputLines);

        $branch = Warehouse::where('outlet_id', $outlet->id)->firstOrFail();
        $cashier = User::where('email', 'cashier@gmail.com')->firstOrFail();
        $this->actingAs($cashier)->withSession(['active_outlet_id' => $outlet->id]);
        app(CashierStockMovementService::class)->move($cashier, $outlet->id, 'pickup', [['product_id' => $product->id, 'quantity' => '1.000']], null, 'hpp-transfer-'.Str::uuid());

        $menu = Product::query()->create(['unit_id' => $product->unit_id, 'sku' => 'HPP-MENU-PACK', 'title' => 'HPP Menu Paket', 'buy_price' => 0, 'sell_price' => 20000, 'stock' => 0, 'is_active' => true, 'is_sellable' => true, 'is_semi_finished' => false, 'track_stock' => false]);
        $packUnit = Unit::query()->firstOrCreate(['code' => 'TEST-PACK'], ['name' => 'Paket test', 'symbol' => 'paket test', 'dimension' => 'count', 'is_active' => true]);
        ProductUnit::query()->create(['product_id' => $menu->id, 'unit_id' => $packUnit->id, 'conversion_factor' => 1, 'sell_price' => 20000, 'is_base' => false, 'is_default' => false]);
        $menuRecipe = RecipeVersion::query()->create(['product_id' => $menu->id, 'unit_id' => $packUnit->id, 'version' => 1, 'starts_at' => now(), 'is_active' => true, 'yield_quantity' => 1]);
        $menuRecipe->productItems()->create(['product_id' => $product->id, 'unit_id' => $product->unit_id, 'quantity' => '1.000', 'waste_percent' => 0]);
        $shift = app(CashierShiftService::class)->open($outlet->id, $branch->id, '100000');
        $cart = app(CartService::class)->activeFor($shift);
        app(CartService::class)->add($cart, $menu->id, $packUnit->id);
        $sale = app(CheckoutService::class)->cash($cart, '20000');

        $this->assertSame('completed', $sale->status);
        $this->assertDatabaseHas('product_warehouses', ['product_id' => $product->id, 'warehouse_id' => $branch->id, 'stock' => 0]);
        $this->assertGreaterThan(0, (float) $sale->details()->first()->unit_cost);
    }
}
