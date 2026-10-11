<?php

namespace Tests\Feature;

use App\Livewire\Inventory\CashierStockTransfersIndex;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\StockTransfer;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Inventory\CashierStockMovementService;
use App\Services\Inventory\StockMutationService;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\TestDataSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class CashierStockMovementTest extends TestCase
{
    use RefreshDatabase;

    public function test_pickup_rolls_back_every_product_when_one_product_lacks_stock(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(UserSeeder::class);
        $this->seed(TestDataSeeder::class);
        $warehouseUser = User::where('email', 'warehouse@gmail.com')->firstOrFail();
        $cashier = User::where('email', 'cashier@gmail.com')->firstOrFail();
        $outlet = Outlet::where('code', 'GAL-BP')->firstOrFail();
        $central = Warehouse::where('code', 'PUSAT')->firstOrFail();
        $branch = Warehouse::where('outlet_id', $outlet->id)->firstOrFail();
        $availableProduct = Product::where('sku', 'TEST-DPL')->firstOrFail();
        $missingProduct = Product::where('sku', 'TEST-DWO')->firstOrFail();
        $this->actingAs($warehouseUser);
        app(StockMutationService::class)->mutate($availableProduct->id, $central->id, '10.000', 'production_output', 'cashier-rollback-fixture');
        $this->actingAs($cashier)->withSession(['active_outlet_id' => $outlet->id]);

        try {
            app(CashierStockMovementService::class)->move($cashier, $outlet->id, 'pickup', [
                ['product_id' => $availableProduct->id, 'quantity' => '3'],
                ['product_id' => $missingProduct->id, 'quantity' => '1'],
            ], null, 'rollback-pickup-001');
            $this->fail('Pengambilan stok kurang seharusnya ditolak.');
        } catch (ValidationException) {
            $this->assertSame(0, StockTransfer::query()->where('idempotency_key', 'rollback-pickup-001')->count());
            $this->assertSame(10.0, (float) DB::table('product_warehouses')->where('warehouse_id', $central->id)->where('product_id', $availableProduct->id)->value('stock'));
            $this->assertSame(0.0, (float) DB::table('product_warehouses')->where('warehouse_id', $branch->id)->where('product_id', $availableProduct->id)->value('stock'));
        }
    }

    public function test_cashier_pickup_and_good_return_move_only_own_outlet_stock_once(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(UserSeeder::class);
        $this->seed(TestDataSeeder::class);
        $warehouseUser = User::where('email', 'warehouse@gmail.com')->firstOrFail();
        $cashier = User::where('email', 'cashier@gmail.com')->firstOrFail();
        $outlet = Outlet::where('code', 'GAL-BP')->firstOrFail();
        $central = Warehouse::where('code', 'PUSAT')->firstOrFail();
        $branch = Warehouse::where('outlet_id', $outlet->id)->firstOrFail();
        $product = Product::where('sku', 'TEST-DPL')->firstOrFail();
        $this->actingAs($warehouseUser);
        app(StockMutationService::class)->mutate($product->id, $central->id, '10.000', 'production_output', 'cashier-stock-fixture');

        $this->actingAs($cashier)->withSession(['active_outlet_id' => $outlet->id]);
        Livewire::test(CashierStockTransfersIndex::class)->assertSee('Ambil stok dari gudang')->assertSee($product->title);
        $service = app(CashierStockMovementService::class);
        $pickup = $service->move($cashier, $outlet->id, 'pickup', [['product_id' => $product->id, 'quantity' => '3']], null, 'pickup-test-001');
        $this->assertSame($pickup->id, $service->move($cashier, $outlet->id, 'pickup', [['product_id' => $product->id, 'quantity' => '3']], null, 'pickup-test-001')->id);
        $this->assertSame('completed', $pickup->status);
        $this->assertSame(7.0, (float) DB::table('product_warehouses')->where('warehouse_id', $central->id)->where('product_id', $product->id)->value('stock'));
        $this->assertSame(3.0, (float) DB::table('product_warehouses')->where('warehouse_id', $branch->id)->where('product_id', $product->id)->value('stock'));

        $return = $service->move($cashier, $outlet->id, 'return', [['product_id' => $product->id, 'quantity' => '1']], 'Sisa produk masih layak', 'return-test-001', 'good');
        $this->assertSame('return', $return->direction);
        $this->assertSame(8.0, (float) DB::table('product_warehouses')->where('warehouse_id', $central->id)->where('product_id', $product->id)->value('stock'));
        $this->assertSame(2.0, (float) DB::table('product_warehouses')->where('warehouse_id', $branch->id)->where('product_id', $product->id)->value('stock'));
        $this->assertSame(4, DB::table('inventory_ledgers')->where('reference_type', StockTransfer::class)->count());
    }

    public function test_batch_identity_follows_pickup_and_return(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(UserSeeder::class);
        $this->seed(TestDataSeeder::class);
        $warehouseUser = User::where('email', 'warehouse@gmail.com')->firstOrFail();
        $cashier = User::where('email', 'cashier@gmail.com')->firstOrFail();
        $outlet = Outlet::where('code', 'GAL-BP')->firstOrFail();
        $central = Warehouse::where('code', 'PUSAT')->firstOrFail();
        $branch = Warehouse::where('outlet_id', $outlet->id)->firstOrFail();
        $product = Product::where('sku', 'TEST-DPL')->firstOrFail();
        $product->update(['track_batches' => true]);
        $sourceBatch = ProductBatch::query()->create([
            'product_id' => $product->id, 'warehouse_id' => $central->id, 'batch_number' => 'TEST-CHILLED-001',
            'expires_at' => today()->addWeek(), 'quantity_received' => '0.000', 'quantity_remaining' => '0.000', 'status' => 'active',
        ]);
        $this->actingAs($warehouseUser);
        app(StockMutationService::class)->mutate($product->id, $central->id, '5.000', 'production_output', 'batch-opening-stock', null, $sourceBatch->id);
        $sourceBatch->update(['quantity_received' => '5.000']);

        $this->actingAs($cashier)->withSession(['active_outlet_id' => $outlet->id]);
        $service = app(CashierStockMovementService::class);
        $service->move($cashier, $outlet->id, 'pickup', [['product_id' => $product->id, 'quantity' => '2']], null, 'batch-pickup-001');
        $this->assertDatabaseHas('product_batches', ['product_id' => $product->id, 'warehouse_id' => $branch->id, 'batch_number' => 'TEST-CHILLED-001', 'quantity_remaining' => 2]);
        $service->move($cashier, $outlet->id, 'return', [['product_id' => $product->id, 'quantity' => '1']], 'Sisa batch masih layak', 'batch-return-001', 'good');
        $this->assertDatabaseHas('product_batches', ['product_id' => $product->id, 'warehouse_id' => $central->id, 'batch_number' => 'TEST-CHILLED-001', 'quantity_remaining' => 4]);
        $this->assertDatabaseHas('product_batches', ['product_id' => $product->id, 'warehouse_id' => $branch->id, 'batch_number' => 'TEST-CHILLED-001', 'quantity_remaining' => 1]);
    }
}
