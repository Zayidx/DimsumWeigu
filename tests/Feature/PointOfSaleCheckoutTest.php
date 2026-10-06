<?php

namespace Tests\Feature;

use App\Livewire\Catalog\CategoriesIndex;
use App\Livewire\Catalog\ProductsIndex;
use App\Livewire\Customers\CustomersIndex;
use App\Livewire\Inventory\InventoryIndex;
use App\Livewire\Inventory\RecipesIndex;
use App\Livewire\Pos\Checkout as CheckoutComponent;
use App\Livewire\Purchasing\GoodsReceivingsIndex;
use App\Livewire\Purchasing\PurchaseOrdersIndex;
use App\Livewire\Purchasing\SuppliersIndex;
use App\Models\BankAccount;
use App\Models\BankAccountLedger;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\CashierShift;
use App\Models\Customer;
use App\Models\GoodsReceiving;
use App\Models\Ingredient;
use App\Models\IngredientCategory;
use App\Models\InventoryLedger;
use App\Models\Outlet;
use App\Models\Payable;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\ProductUnit;
use App\Models\ProductWarehouse;
use App\Models\PurchaseOrder;
use App\Models\Receivable;
use App\Models\RecipeVersion;
use App\Models\Supplier;
use App\Models\Transaction;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Cashier\CartService;
use App\Services\Cashier\CheckoutService;
use App\Services\Finance\SettlementService;
use App\Services\Inventory\StockMutationService;
use App\Services\Inventory\StockOpnameService;
use App\Services\Inventory\StockTransferService;
use App\Services\Purchasing\PurchasingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PointOfSaleCheckoutTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Outlet $outlet;

    private Warehouse $warehouse;

    private Unit $unit;

    private Unit $packageUnit;

    private Product $product;

    private CashierShift $shift;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['pos-access', 'pos-checkout', 'pos-hold', 'products-access', 'products-create', 'products-edit', 'products-delete', 'categories-access', 'categories-create', 'categories-edit', 'categories-delete', 'inventory-access', 'inventory-opening', 'inventory-adjust', 'transfers-access', 'transfers-create', 'transfers-send', 'transfers-receive', 'transfers-cancel', 'stock-opnames-access', 'stock-opnames-create', 'stock-opnames-review', 'stock-opnames-finalize', 'transactions-access', 'customers-access', 'customers-create', 'customers-edit', 'customers-delete', 'suppliers-access', 'suppliers-create', 'suppliers-update', 'suppliers-delete', 'purchase-orders-access', 'purchase-orders-create', 'purchase-orders-order', 'purchase-orders-cancel', 'goods-receivings-access', 'goods-receivings-create', 'payables-access', 'payables-pay', 'receivables-access', 'receivables-collect', 'recipes-access', 'recipes-create'] as $permission) {
            Permission::query()->firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }
        $role = Role::query()->firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
        $role->syncPermissions(Permission::all());
        $this->user = User::query()->create(['name' => 'Cashier Test', 'email' => 'cashier@example.test', 'password' => 'Testing-password-123!', 'is_active' => true]);
        $this->user->assignRole($role);
        $this->outlet = Outlet::query()->create(['code' => 'TEST', 'slug' => 'test', 'name' => 'Test outlet', 'is_active' => true, 'is_sales_enabled' => true]);
        $this->user->outlets()->attach($this->outlet->id, ['is_default' => true]);
        $this->warehouse = Warehouse::query()->create(['outlet_id' => $this->outlet->id, 'code' => 'TEST-WH', 'name' => 'Test warehouse', 'type' => 'branch', 'is_active' => true, 'is_sales_enabled' => true]);
        $this->unit = Unit::query()->create(['name' => 'Pieces', 'symbol' => 'pcs', 'dimension' => 'count', 'is_active' => true]);
        $this->packageUnit = Unit::query()->create(['name' => 'Test package', 'symbol' => 'paket tes', 'dimension' => 'count', 'is_active' => true]);
        $this->product = Product::query()->create(['unit_id' => $this->unit->id, 'sku' => 'TEST-1', 'title' => 'Test product', 'buy_price' => 4000, 'sell_price' => 10000, 'tax_rate' => 0, 'stock' => 5, 'is_active' => true, 'is_sellable' => true, 'track_stock' => true]);
        ProductWarehouse::query()->create(['product_id' => $this->product->id, 'warehouse_id' => $this->warehouse->id, 'stock' => 5, 'reserved_stock' => 0]);
        ProductUnit::query()->create(['product_id' => $this->product->id, 'unit_id' => $this->packageUnit->id, 'conversion_factor' => 1, 'buy_price' => 4000, 'sell_price' => 10000, 'is_base' => false, 'is_default' => false]);
        $this->shift = CashierShift::query()->create(['user_id' => $this->user->id, 'open_user_id' => $this->user->id, 'outlet_id' => $this->outlet->id, 'warehouse_id' => $this->warehouse->id, 'status' => 'open', 'opening_cash' => 0, 'expected_cash' => 0, 'opened_at' => now()]);
        PaymentMethod::query()->create(['code' => 'cash', 'name' => 'Tunai', 'provider' => null, 'is_enabled' => true, 'requires_confirmation' => false]);
        $this->actingAs($this->user)->withSession(['active_outlet_id' => $this->outlet->id]);
    }

    public function test_stock_mutation_updates_warehouse_global_balance_and_ledger_once(): void
    {
        $service = app(StockMutationService::class);
        $key = (string) Str::uuid();
        $service->mutate($this->product->id, $this->warehouse->id, '2.000', 'opening', $key, 'Opening count');
        $service->mutate($this->product->id, $this->warehouse->id, '2.000', 'opening', $key, 'Opening count');

        $this->assertDatabaseHas('product_warehouses', ['product_id' => $this->product->id, 'warehouse_id' => $this->warehouse->id, 'stock' => '7.000']);
        $this->assertDatabaseHas('products', ['id' => $this->product->id, 'stock' => '7.000']);
        $this->assertSame(1, InventoryLedger::query()->where('idempotency_key', $key)->count());
    }

    public function test_cash_checkout_records_sale_and_decrements_stock_atomically(): void
    {
        $cart = Cart::query()->create(['uuid' => (string) Str::uuid(), 'user_id' => $this->user->id, 'cashier_shift_id' => $this->shift->id, 'outlet_id' => $this->outlet->id, 'warehouse_id' => $this->warehouse->id, 'status' => 'active']);
        CartItem::query()->create(['cart_id' => $cart->id, 'product_id' => $this->product->id, 'unit_id' => $this->packageUnit->id, 'quantity' => 2]);

        $sale = app(CheckoutService::class)->cash($cart, '25000');

        $this->assertSame('20000.00', $sale->grand_total);
        $this->assertSame('5000.00', $sale->change_total);
        $this->assertDatabaseHas('product_warehouses', ['product_id' => $this->product->id, 'warehouse_id' => $this->warehouse->id, 'stock' => '3.000']);
        $this->assertDatabaseHas('transactions', ['id' => $sale->id, 'status' => 'completed', 'payment_status' => 'paid']);
        $this->assertDatabaseHas('transaction_tenders', ['transaction_id' => $sale->id, 'method' => 'cash', 'status' => 'paid', 'amount' => '20000.00']);
        $this->assertDatabaseHas('profits', ['transaction_id' => $sale->id, 'revenue' => '20000.00', 'cost' => '8000.00', 'profit' => '12000.00']);
        $this->assertDatabaseHas('carts', ['id' => $cart->id, 'status' => 'completed']);
    }

    public function test_qris_checkout_credits_finance_bank_account_without_adding_warehouse_cash(): void
    {
        PaymentMethod::query()->create(['code' => 'qris', 'name' => 'QRIS', 'provider' => 'qris', 'is_enabled' => true, 'requires_confirmation' => true]);
        $account = BankAccount::query()->create(['bank_name' => 'Bank Test', 'account_name' => 'Finance', 'account_number' => '123456', 'sort_order' => 1, 'is_active' => true]);
        $cart = Cart::query()->create(['uuid' => (string) Str::uuid(), 'user_id' => $this->user->id, 'cashier_shift_id' => $this->shift->id, 'outlet_id' => $this->outlet->id, 'warehouse_id' => $this->warehouse->id, 'status' => 'active']);
        CartItem::query()->create(['cart_id' => $cart->id, 'product_id' => $this->product->id, 'unit_id' => $this->packageUnit->id, 'quantity' => 2]);

        $sale = app(CheckoutService::class)->qris($cart, $account->id, 'QRIS-REF-1');
        app(CheckoutService::class)->qris($cart, $account->id, 'QRIS-REF-1');

        $this->assertSame('paid', $sale->payment_status);
        $this->assertDatabaseHas('transaction_tenders', ['transaction_id' => $sale->id, 'method' => 'qris', 'status' => 'paid', 'bank_account_id' => $account->id, 'provider_reference' => 'QRIS-REF-1']);
        $this->assertDatabaseHas('bank_account_ledgers', ['bank_account_id' => $account->id, 'type' => 'qris_sale', 'amount' => '20000.00', 'balance_after' => '20000.00']);
        $this->assertDatabaseMissing('warehouse_cash_ledgers', ['reference_type' => Transaction::class, 'reference_id' => $sale->id, 'type' => 'sale_cash']);
        $this->assertSame(1, BankAccountLedger::query()->where('type', 'qris_sale')->count());
    }

    public function test_transaction_detail_and_thermal_receipt_are_scoped_to_active_outlet(): void
    {
        $sale = Transaction::query()->create([
            'number' => 'WG-TEST-RECEIPT', 'outlet_id' => $this->outlet->id, 'warehouse_id' => $this->warehouse->id,
            'cashier_shift_id' => $this->shift->id, 'user_id' => $this->user->id, 'source' => 'pos',
            'status' => 'completed', 'payment_status' => 'paid', 'grand_total' => 1000, 'paid_total' => 1000,
            'occurred_at' => now(),
        ]);
        $this->user->givePermissionTo('transactions-access');

        $this->get(route('transactions.show', $sale))->assertOk()->assertSee('WG-TEST-RECEIPT');
        $this->get(route('transactions.receipt', ['transaction' => $sale->id, 'size' => 58]))->assertOk()->assertSee('Cetak struk');
    }

    public function test_cashier_page_renders_products_and_active_shift(): void
    {
        $response = $this->withSession(['active_outlet_id' => $this->outlet->id])->get('/pos');
        $response->assertOk()
            ->assertSee('Pilih produk')
            ->assertSee('Test product')
            ->assertSee('Shift aktif')
            ->assertSee('class="workspace-shell workspace-pos"', false)
            ->assertSee('id="sidebar"', false)
            ->assertSee('workspace-mobile-navigation', false)
            ->assertDontSee('pos-topbar');
    }

    public function test_livewire_cart_add_and_quantity_actions_are_server_backed(): void
    {
        Livewire::actingAs($this->user)->test(CheckoutComponent::class)
            ->call('addProductUnit', $this->product->id, $this->packageUnit->id)
            ->assertSee('Test product')
            ->assertSee('10.000')
            ->call('setQuantity', 1, '2.000')
            ->assertSee('20.000');
    }

    public function test_cart_rejects_base_piece_unit_and_requires_a_package_unit(): void
    {
        $cart = app(CartService::class)->activeFor($this->shift);

        try {
            app(CartService::class)->add($cart, $this->product->id);
            $this->fail('Expected the base piece unit to be rejected.');
        } catch (ValidationException $exception) {
            $this->assertSame('Penjualan wajib memilih satuan paket.', $exception->errors()['unit'][0]);
        }
    }

    public function test_configured_chiquro_can_be_sold_as_a_single_piece(): void
    {
        $chiquro = Product::query()->create([
            'unit_id' => $this->unit->id, 'sku' => 'TEST-CHIQURO-ONE', 'title' => 'Chiquro satuan',
            'buy_price' => 0, 'sell_price' => 26000, 'tax_rate' => 0, 'stock' => 1,
            'is_active' => true, 'is_sellable' => true, 'track_stock' => true, 'allow_single_unit_sale' => true,
        ]);
        ProductUnit::query()->create([
            'product_id' => $chiquro->id, 'unit_id' => $this->unit->id, 'conversion_factor' => 1,
            'buy_price' => 0, 'sell_price' => 26000, 'is_base' => true, 'is_default' => true,
        ]);
        ProductWarehouse::query()->create(['product_id' => $chiquro->id, 'warehouse_id' => $this->warehouse->id, 'stock' => 1, 'reserved_stock' => 0]);

        $cart = app(CartService::class)->activeFor($this->shift);
        app(CartService::class)->add($cart, $chiquro->id, $this->unit->id);
        $sale = app(CheckoutService::class)->cash($cart, '26000');

        $this->assertSame('26000.00', (string) $sale->grand_total);
        $this->assertSame('0.000', (string) ProductWarehouse::query()->where('product_id', $chiquro->id)->where('warehouse_id', $this->warehouse->id)->value('stock'));
    }

    public function test_menu_recipe_can_combine_raw_ingredients_and_semi_finished_products(): void
    {
        $component = Product::query()->create([
            'unit_id' => $this->unit->id, 'sku' => 'TEST-SEMI-001', 'title' => 'Dimsum Wortel Setengah Jadi',
            'buy_price' => 2000, 'sell_price' => 0, 'stock' => 0, 'is_active' => true, 'is_sellable' => false,
            'is_semi_finished' => true, 'track_stock' => true,
        ]);
        $category = IngredientCategory::query()->create(['name' => 'Resep POS Test', 'is_active' => true]);
        $ingredient = Ingredient::query()->create([
            'code' => 'TEST-SAUCE-1', 'name' => 'Saus mentai outlet', 'ingredient_category_id' => $category->id,
            'unit_id' => $this->unit->id, 'buy_price' => 1000, 'stock' => 0, 'is_active' => true,
        ]);

        Livewire::actingAs($this->user)->test(RecipesIndex::class)
            ->call('create')
            ->set('productId', $this->product->id)
            ->set('recipeUnitId', $this->packageUnit->id)
            ->set('lines.0.type', 'ingredient')
            ->set('lines.0.ingredient_id', $ingredient->id)
            ->set('lines.0.quantity', '10')
            ->call('addLine')
            ->set('lines.1.type', 'product')
            ->set('lines.1.component_product_id', $component->id)
            ->set('lines.1.quantity', '2')
            ->call('save')
            ->assertHasNoErrors();

        $recipe = RecipeVersion::query()->where('product_id', $this->product->id)->where('unit_id', $this->packageUnit->id)->firstOrFail();
        $this->assertSame(1, $recipe->items()->where('ingredient_id', $ingredient->id)->count());
        $this->assertSame(1, $recipe->productItems()->where('product_id', $component->id)->count());
    }

    public function test_alternate_unit_uses_server_price_and_base_stock_conversion(): void
    {
        $box = Unit::query()->create(['name' => 'Box', 'symbol' => 'box', 'dimension' => 'count', 'is_active' => true]);
        ProductUnit::query()->create(['product_id' => $this->product->id, 'unit_id' => $box->id, 'conversion_factor' => 2, 'sell_price' => 18000, 'barcode' => 'BOX-TEST', 'is_default' => false]);
        $cart = Cart::query()->create(['uuid' => (string) Str::uuid(), 'user_id' => $this->user->id, 'cashier_shift_id' => $this->shift->id, 'outlet_id' => $this->outlet->id, 'warehouse_id' => $this->warehouse->id, 'status' => 'active']);
        CartItem::query()->create(['cart_id' => $cart->id, 'product_id' => $this->product->id, 'unit_id' => $box->id, 'quantity' => 1]);

        $sale = app(CheckoutService::class)->cash($cart, '18000');

        $this->assertSame('18000.00', $sale->grand_total);
        $this->assertDatabaseHas('transaction_details', ['transaction_id' => $sale->id, 'quantity' => '1.000', 'unit_name' => 'box', 'unit_price' => '18000.00']);
        $this->assertDatabaseHas('product_warehouses', ['product_id' => $this->product->id, 'warehouse_id' => $this->warehouse->id, 'stock' => '3.000']);
    }

    public function test_product_catalog_livewire_creates_products_with_zero_warehouse_balances(): void
    {
        $component = Livewire::actingAs($this->user)->test(ProductsIndex::class)->assertSee('Daftar produk');
        $component->call('openCreate')->assertOk()->assertHasNoErrors()->assertSet('showForm', true);
        $component
            ->set('unitId', $this->unit->id)
            ->set('sku', '  test-new  ')
            ->set('title', 'New Product')
            ->set('buyPrice', '2500')
            ->set('sellPrice', '5000')
            ->set('taxRate', '')
            ->set('minStock', '2')
            ->set('reorderPoint', '3')
            ->call('save');

        $this->assertDatabaseHas('products', ['sku' => 'TEST-NEW', 'title' => 'New Product', 'stock' => '0.000']);
        $product = Product::query()->where('sku', 'TEST-NEW')->firstOrFail();
        $this->assertDatabaseHas('product_warehouses', ['product_id' => $product->id, 'warehouse_id' => $this->warehouse->id, 'stock' => '0.000']);
    }

    public function test_inventory_adjustment_uses_stock_ledger_service(): void
    {
        Livewire::actingAs($this->user)->test(InventoryIndex::class)
            ->call('openAdjustment', $this->product->id)
            ->set('direction', 'in')
            ->set('quantity', '2.500')
            ->set('reason', 'Opening stock count')
            ->call('adjust');

        $this->assertDatabaseHas('product_warehouses', ['product_id' => $this->product->id, 'warehouse_id' => $this->warehouse->id, 'stock' => '7.500']);
        $this->assertDatabaseHas('inventory_ledgers', ['product_id' => $this->product->id, 'warehouse_id' => $this->warehouse->id, 'quantity_delta' => '2.500', 'type' => 'opening']);
    }

    public function test_category_crud_generates_a_unique_slug(): void
    {
        Livewire::actingAs($this->user)->test(CategoriesIndex::class)
            ->call('create')
            ->set('name', 'Frozen Favorites')
            ->set('description', 'Frozen products')
            ->call('save');

        $this->assertDatabaseHas('categories', ['name' => 'Frozen Favorites', 'slug' => 'frozen-favorites', 'is_active' => 1]);
    }

    public function test_customer_and_supplier_records_are_audited(): void
    {
        Livewire::actingAs($this->user)->test(CustomersIndex::class)
            ->call('create')->set('code', 'C-001')->set('name', 'Test Customer')->set('phone', '08123456789')
            ->set('marketingConsent', true)->call('save');
        Livewire::actingAs($this->user)->test(SuppliersIndex::class)
            ->call('create')->set('code', 'S-001')->set('name', 'Test Supplier')->set('phone', '08123456780')
            ->set('city', 'Bekasi')->call('save');

        $this->assertDatabaseHas('customers', ['code' => 'C-001', 'name' => 'Test Customer', 'marketing_consent' => 1]);
        $this->assertDatabaseHas('suppliers', ['code' => 'S-001', 'name' => 'Test Supplier', 'city' => 'Bekasi']);
        $this->assertDatabaseHas('audit_logs', ['event' => 'customer.created']);
        $this->assertDatabaseHas('audit_logs', ['event' => 'supplier.created']);
    }

    public function test_partial_receiving_posts_only_accepted_stock_and_creates_payable_once(): void
    {
        $supplier = Supplier::query()->create(['code' => 'SUP-1', 'name' => 'Supplier One', 'is_active' => true]);
        $service = app(PurchasingService::class);
        $order = $service->createDraft($supplier->id, $this->warehouse->id, [[
            'product_id' => $this->product->id, 'quantity' => '5.000', 'unit_cost' => '6000', 'tax_rate' => '11',
        ]]);
        $service->order($order->id);
        $item = $order->items->firstOrFail();
        $key = (string) Str::uuid();
        $first = $service->receive($order->id, [$item->id => ['quantity' => '3.000', 'accepted' => '2.000']], $key);
        $replayed = $service->receive($order->id, [$item->id => ['quantity' => '3.000', 'accepted' => '2.000']], $key);

        $this->assertSame($first->id, $replayed->id);
        $this->assertSame('partial', $order->refresh()->status);
        $this->assertDatabaseHas('product_warehouses', ['product_id' => $this->product->id, 'warehouse_id' => $this->warehouse->id, 'stock' => '7.000']);
        $this->assertDatabaseHas('payables', ['goods_receiving_id' => $first->id, 'amount' => '13320.00', 'status' => 'unpaid']);
        $this->assertSame(1, GoodsReceiving::query()->where('idempotency_key', $key)->count());
    }

    public function test_purchase_order_and_receiving_screens_complete_ordered_workflow(): void
    {
        $supplier = Supplier::query()->create(['code' => 'SUP-UI', 'name' => 'UI Supplier', 'is_active' => true]);
        $component = Livewire::actingAs($this->user)->test(PurchaseOrdersIndex::class)
            ->call('create')->set('supplierId', $supplier->id)->set('warehouseId', $this->warehouse->id)
            ->set('items.0.product_id', $this->product->id)->set('items.0.quantity', '2')
            ->set('items.0.unit_cost', '5000')->set('items.0.tax_rate', '0')->call('saveDraft');
        $order = PurchaseOrder::query()->where('supplier_id', $supplier->id)->firstOrFail();
        $component->call('order', $order->id);

        Livewire::actingAs($this->user)->test(GoodsReceivingsIndex::class)
            ->assertSee($order->number)->call('begin', $order->id)
            ->set('items.'.$order->items->first()->id.'.quantity', '2')
            ->set('items.'.$order->items->first()->id.'.accepted', '2')
            ->call('receive');

        $this->assertSame('completed', $order->refresh()->status);
        $this->assertDatabaseHas('product_warehouses', ['product_id' => $this->product->id, 'warehouse_id' => $this->warehouse->id, 'stock' => '7.000']);
    }

    public function test_purchasing_and_finance_pages_render_under_permission_scope(): void
    {
        $this->get('/purchase-orders')->assertOk()->assertSee('Purchase order');
        $this->get('/goods-receivings')->assertOk()->assertSee('PO menunggu penerimaan');
        $this->get('/payables')->assertOk()->assertSee('Hutang pemasok');
        $this->get('/receivables')->assertOk()->assertSee('Piutang pelanggan');
    }

    public function test_failed_stock_check_rolls_back_sale_and_payment(): void
    {
        $cart = Cart::query()->create(['uuid' => (string) Str::uuid(), 'user_id' => $this->user->id, 'cashier_shift_id' => $this->shift->id, 'outlet_id' => $this->outlet->id, 'warehouse_id' => $this->warehouse->id, 'status' => 'active']);
        CartItem::query()->create(['cart_id' => $cart->id, 'product_id' => $this->product->id, 'unit_id' => $this->packageUnit->id, 'quantity' => 6]);

        try {
            app(CheckoutService::class)->cash($cart, '100000');
            $this->fail('Expected stock validation to reject the checkout.');
        } catch (ValidationException) {
            $this->assertSame(0, Transaction::query()->count());
            $this->assertSame('active', $cart->refresh()->status);
            $this->assertDatabaseHas('product_warehouses', ['product_id' => $this->product->id, 'warehouse_id' => $this->warehouse->id, 'stock' => '5.000']);
        }
    }

    public function test_pay_later_checkout_observes_credit_limit_and_creates_receivable(): void
    {
        $customer = Customer::query()->create(['code' => 'CREDIT-1', 'name' => 'Credit Customer', 'credit_limit' => 25000, 'is_active' => true]);
        PaymentMethod::query()->create(['code' => 'pay-later', 'name' => 'Bayar nanti', 'provider' => null, 'is_enabled' => true, 'requires_confirmation' => true]);
        $cart = Cart::query()->create(['uuid' => (string) Str::uuid(), 'user_id' => $this->user->id, 'cashier_shift_id' => $this->shift->id, 'outlet_id' => $this->outlet->id, 'warehouse_id' => $this->warehouse->id, 'customer_id' => $customer->id, 'status' => 'active']);
        CartItem::query()->create(['cart_id' => $cart->id, 'product_id' => $this->product->id, 'unit_id' => $this->packageUnit->id, 'quantity' => 2]);

        $sale = app(CheckoutService::class)->payLater($cart);

        $this->assertSame('unpaid', $sale->payment_status);
        $this->assertSame('0.00', $sale->paid_total);
        $this->assertDatabaseHas('receivables', ['transaction_id' => $sale->id, 'customer_id' => $customer->id, 'amount' => '20000.00', 'status' => 'unpaid']);
        $this->assertDatabaseHas('transaction_tenders', ['transaction_id' => $sale->id, 'method' => 'pay-later', 'status' => 'pending']);
        $this->assertDatabaseHas('customer_credits', ['transaction_id' => $sale->id, 'balance_before' => '0.00', 'balance_after' => '20000.00']);
    }

    public function test_financial_settlements_support_partial_payment_and_idempotent_replay(): void
    {
        $supplier = Supplier::query()->create(['code' => 'SUP-AR', 'name' => 'Settlement Supplier', 'is_active' => true]);
        $purchasing = app(PurchasingService::class);
        $order = $purchasing->createDraft($supplier->id, $this->warehouse->id, [['product_id' => $this->product->id, 'quantity' => '2', 'unit_cost' => '10000', 'tax_rate' => '0']]);
        $purchasing->order($order->id);
        $item = $order->items->firstOrFail();
        $receiving = $purchasing->receive($order->id, [$item->id => ['quantity' => '2', 'accepted' => '2']], (string) Str::uuid());
        $payable = Payable::query()->where('goods_receiving_id', $receiving->id)->firstOrFail();
        $settlement = app(SettlementService::class);
        $payKey = (string) Str::uuid();
        $payment = $settlement->payPayable($payable->id, '5000', 'bank-transfer', $payKey);
        $this->assertSame($payment->id, $settlement->payPayable($payable->id, '5000', 'bank-transfer', $payKey)->id);
        $this->assertDatabaseHas('payables', ['id' => $payable->id, 'paid_amount' => '5000.00', 'status' => 'partial']);

        $customer = Customer::query()->create(['code' => 'CUSTOMER-AR', 'name' => 'Receivable Customer', 'credit_limit' => 50000, 'is_active' => true]);
        PaymentMethod::query()->updateOrCreate(['code' => 'pay-later'], ['name' => 'Bayar nanti', 'is_enabled' => true, 'requires_confirmation' => true]);
        $cart = Cart::query()->create(['uuid' => (string) Str::uuid(), 'user_id' => $this->user->id, 'cashier_shift_id' => $this->shift->id, 'outlet_id' => $this->outlet->id, 'warehouse_id' => $this->warehouse->id, 'customer_id' => $customer->id, 'status' => 'active']);
        CartItem::query()->create(['cart_id' => $cart->id, 'product_id' => $this->product->id, 'unit_id' => $this->packageUnit->id, 'quantity' => 1]);
        $sale = app(CheckoutService::class)->payLater($cart);
        $receivable = Receivable::query()->where('transaction_id', $sale->id)->firstOrFail();
        $collectKey = (string) Str::uuid();
        $collected = $settlement->collectReceivable($receivable->id, '4000', 'bank-transfer', $collectKey);
        $this->assertSame($collected->id, $settlement->collectReceivable($receivable->id, '4000', 'bank-transfer', $collectKey)->id);
        $this->assertDatabaseHas('receivables', ['id' => $receivable->id, 'paid_amount' => '4000.00', 'status' => 'partial']);
        $this->assertDatabaseHas('transactions', ['id' => $sale->id, 'paid_total' => '4000.00', 'payment_status' => 'partial']);
    }

    public function test_stock_transfer_moves_stock_at_dispatch_and_receipt_and_can_be_cancelled(): void
    {
        $destination = Warehouse::query()->create(['outlet_id' => $this->outlet->id, 'code' => 'TEST-WH-2', 'name' => 'Destination warehouse', 'type' => 'branch', 'is_active' => true, 'is_sales_enabled' => false]);
        ProductWarehouse::query()->create(['product_id' => $this->product->id, 'warehouse_id' => $destination->id, 'stock' => 0, 'reserved_stock' => 0]);
        $service = app(StockTransferService::class);
        $transfer = $service->createDraft($this->warehouse->id, $destination->id, [['product_id' => $this->product->id, 'quantity' => '2.000']]);
        $this->assertDatabaseHas('product_warehouses', ['product_id' => $this->product->id, 'warehouse_id' => $this->warehouse->id, 'stock' => '5.000']);
        $service->send($transfer->id);
        $this->assertDatabaseHas('product_warehouses', ['product_id' => $this->product->id, 'warehouse_id' => $this->warehouse->id, 'stock' => '3.000']);
        $this->assertDatabaseHas('product_warehouses', ['product_id' => $this->product->id, 'warehouse_id' => $destination->id, 'stock' => '0.000']);
        $receiveKey = (string) Str::uuid();
        $service->receive($transfer->id, $receiveKey);
        $this->assertSame($transfer->id, $service->receive($transfer->id, $receiveKey)->id);
        $this->assertDatabaseHas('product_warehouses', ['product_id' => $this->product->id, 'warehouse_id' => $destination->id, 'stock' => '2.000']);
        $this->assertDatabaseHas('products', ['id' => $this->product->id, 'stock' => '5.000']);

        $cancel = $service->createDraft($this->warehouse->id, $destination->id, [['product_id' => $this->product->id, 'quantity' => '1.000']]);
        $service->send($cancel->id);
        $service->cancel($cancel->id);
        $this->assertDatabaseHas('product_warehouses', ['product_id' => $this->product->id, 'warehouse_id' => $this->warehouse->id, 'stock' => '3.000']);
        $this->assertDatabaseHas('products', ['id' => $this->product->id, 'stock' => '5.000']);
    }

    public function test_stock_transfer_preserves_fefo_batch_identity_at_destination(): void
    {
        $destination = Warehouse::query()->create(['outlet_id' => $this->outlet->id, 'code' => 'BATCH-WH-2', 'name' => 'Batch destination', 'type' => 'branch', 'is_active' => true, 'is_sales_enabled' => false]);
        ProductWarehouse::query()->create(['product_id' => $this->product->id, 'warehouse_id' => $destination->id, 'stock' => 0, 'reserved_stock' => 0]);
        $this->product->forceFill(['track_batches' => true])->save();
        $batch = ProductBatch::query()->create(['product_id' => $this->product->id, 'warehouse_id' => $this->warehouse->id, 'batch_number' => 'LOT-1', 'expires_at' => today()->addMonths(4), 'quantity_received' => 5, 'quantity_remaining' => 5, 'status' => 'active']);
        $service = app(StockTransferService::class);
        $transfer = $service->createDraft($this->warehouse->id, $destination->id, [['product_id' => $this->product->id, 'quantity' => '2.000']]);
        $service->send($transfer->id);
        $this->assertDatabaseHas('stock_transfer_batch_items', ['product_batch_id' => $batch->id, 'quantity_sent' => '2.000']);
        $service->receive($transfer->id, (string) Str::uuid());

        $this->assertDatabaseHas('product_batches', ['product_id' => $this->product->id, 'warehouse_id' => $destination->id, 'batch_number' => 'LOT-1', 'quantity_received' => '2.000', 'quantity_remaining' => '2.000']);
        $this->assertDatabaseHas('product_batches', ['id' => $batch->id, 'quantity_remaining' => '3.000']);
    }

    public function test_stock_opname_requires_count_review_and_finalizes_variance(): void
    {
        $service = app(StockOpnameService::class);
        $opname = $service->createDraft($this->warehouse->id, 'Cycle count');
        $item = $opname->items->firstOrFail();
        $service->count($opname->id, $item->id, '4.500', 'Damaged packaging');
        $service->review($opname->id);
        $service->finalize($opname->id);

        $this->assertSame('finalized', $opname->refresh()->status);
        $this->assertDatabaseHas('product_warehouses', ['product_id' => $this->product->id, 'warehouse_id' => $this->warehouse->id, 'stock' => '4.500']);
        $this->assertDatabaseHas('inventory_ledgers', ['product_id' => $this->product->id, 'warehouse_id' => $this->warehouse->id, 'quantity_delta' => '-0.500', 'type' => 'stock_opname']);
    }

    public function test_opname_refuses_to_apply_stale_snapshot(): void
    {
        $service = app(StockOpnameService::class);
        $opname = $service->createDraft($this->warehouse->id);
        $item = $opname->items->firstOrFail();
        $service->count($opname->id, $item->id, '5.000');
        $service->review($opname->id);
        app(StockMutationService::class)->mutate($this->product->id, $this->warehouse->id, '1.000', 'opening', (string) Str::uuid());

        try {
            $service->finalize($opname->id);
            $this->fail('Expected a stale snapshot to block opname finalization.');
        } catch (ValidationException) {
            $this->assertSame('reviewed', $opname->refresh()->status);
            $this->assertDatabaseHas('product_warehouses', ['product_id' => $this->product->id, 'warehouse_id' => $this->warehouse->id, 'stock' => '6.000']);
        }
    }
}
