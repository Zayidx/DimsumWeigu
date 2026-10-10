<?php

namespace Tests\Feature;

use App\Livewire\Admin\RolesIndex;
use App\Livewire\Catalog\UnitsIndex;
use App\Livewire\Reports\AgingReport;
use App\Livewire\Reports\InventoryReport;
use App\Livewire\Reports\ProfitReport;
use App\Livewire\Reports\SalesReport;
use App\Livewire\Sales\TransactionsIndex;
use App\Models\CashierShift;
use App\Models\Customer;
use App\Models\OperationalNotification;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\ProductWarehouse;
use App\Models\Receivable;
use App\Models\Transaction;
use App\Models\TransactionDetail;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminReportsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Outlet $outlet;

    private Warehouse $warehouse;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->user = User::create(['name' => 'Administrator', 'email' => 'admin@example.test', 'password' => 'Test-password-123!', 'is_active' => true]);
        $this->user->assignRole('super-admin');
        $this->outlet = Outlet::create(['code' => 'REPORT', 'slug' => 'report', 'name' => 'Report outlet', 'is_active' => true, 'is_sales_enabled' => true]);
        $this->warehouse = Warehouse::create(['outlet_id' => $this->outlet->id, 'code' => 'REPORT-WH', 'name' => 'Report warehouse', 'type' => 'branch', 'is_active' => true, 'is_sales_enabled' => true]);
        $this->user->outlets()->attach($this->outlet->id, ['is_default' => true]);
        $this->actingAs($this->user)->withSession(['active_outlet_id' => $this->outlet->id, 'auth.password_confirmed_at' => now()->timestamp]);
        // Livewire's component harness disables web middleware; supply the authenticated web session explicitly.
        $this->app->rebinding('request', function ($app, $request): void {
            $request->setLaravelSession($app['session.store']);
        });
    }

    public function test_admin_and_report_pages_render_from_real_queries(): void
    {
        foreach (['/users', '/roles', '/units', '/audit-logs', '/notifications', '/settings/store', '/settings/payments', '/settings/bank-accounts', '/reports/sales', '/reports/profit', '/reports/inventory', '/reports/aging'] as $path) {
            $this->get($path)->assertOk();
        }
    }

    public function test_dashboard_renders_empty_sales_trend_without_a_broken_chart(): void
    {
        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Tren penjualan')
            ->assertSee('Belum ada penjualan dalam 7 hari terakhir.')
            ->assertSee('Navigasi cepat')
            ->assertSee('aria-label="Notifikasi', false)
            ->assertSee('class="workspace-menu-toggle"', false)
            ->assertSee('aria-label="Buka menu"', false)
            ->assertSee('workspace-sidebar-brand', false)
            ->assertSee('aria-label="Menu akun"', false)
            ->assertDontSee('workspace-sidebar-outlet', false)
            ->assertDontSee('workspace-topbar', false)
            ->assertDontSee('id="dashboard-sales-trend"', false);
    }

    public function test_dashboard_shows_sales_trend_values_and_payment_status(): void
    {
        $transaction = Transaction::query()->create([
            'number' => 'DASH-PAID-1',
            'outlet_id' => $this->outlet->id,
            'warehouse_id' => $this->warehouse->id,
            'user_id' => $this->user->id,
            'status' => 'completed',
            'payment_status' => 'paid',
            'grand_total' => 125000,
            'occurred_at' => now(),
        ]);

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('id="dashboard-sales-trend"', false)
            ->assertSee('Penjualan 7 hari terakhir, per hari')
            ->assertSee('Rp125.000')
            ->assertSee('Lunas')
            ->assertSee('href="'.route('transactions.show', $transaction).'"', false)
            ->assertSee('bg-light-success text-success', false);
    }

    public function test_dashboard_content_changes_with_operational_role_and_permission(): void
    {
        foreach ([
            ['cashier', 'Ruang kerja kasir', 'Transaksi saya hari ini', 'Sisa utang'],
            ['warehouse', 'Ruang kerja gudang', 'Stok perlu dicek', 'Penjualan saya hari ini'],
            ['finance', 'Ruang kerja keuangan', 'Sisa utang', 'Stok perlu dicek'],
            ['manager', 'Ruang kerja manajer', 'Serah terima kas aktif', 'Sisa utang'],
        ] as [$role, $title, $visible, $hidden]) {
            $user = User::create(['name' => ucfirst($role), 'email' => $role.'@example.test', 'password' => 'Test-password-123!', 'is_active' => true]);
            $user->assignRole($role);
            $user->outlets()->attach($this->outlet->id, ['is_default' => true]);
            $this->assertTrue($user->can('dashboard-access'), $role.' lacks dashboard access');
            $this->flushSession();

            $response = $this->actingAs($user)->withSession(['active_outlet_id' => $this->outlet->id])->get(route('dashboard'));
            $this->assertSame(200, $response->status(), $role.' redirected to '.$response->headers->get('Location'));
            $response
                ->assertSee($title)
                ->assertSee($visible)
                ->assertDontSee($hidden)
                ->assertDontSee('Tren penjualan');
        }
    }

    public function test_cashier_dashboard_shows_only_own_transactions(): void
    {
        $cashier = User::create(['name' => 'Kasir', 'email' => 'own-cashier@example.test', 'password' => 'Test-password-123!', 'is_active' => true]);
        $cashier->assignRole('cashier');
        $cashier->outlets()->attach($this->outlet->id, ['is_default' => true]);
        foreach ([[$cashier, 'OWN-SALE', 12000], [$this->user, 'OTHER-SALE', 90000]] as [$seller, $number, $total]) {
            Transaction::create(['number' => $number, 'outlet_id' => $this->outlet->id, 'warehouse_id' => $this->warehouse->id, 'user_id' => $seller->id, 'status' => 'completed', 'payment_status' => 'paid', 'grand_total' => $total, 'occurred_at' => now()]);
        }

        $this->actingAs($cashier)->withSession(['active_outlet_id' => $this->outlet->id])
            ->get(route('dashboard'))->assertOk()->assertSee('OWN-SALE')->assertSee('Rp12.000')
            ->assertDontSee('OTHER-SALE')->assertDontSee('Rp90.000');
    }

    public function test_cashier_transaction_history_and_receipts_are_limited_to_own_sales(): void
    {
        $cashier = User::create(['name' => 'Kasir riwayat', 'email' => 'history-cashier@example.test', 'password' => 'Test-password-123!', 'is_active' => true]);
        $cashier->assignRole('cashier');
        $cashier->outlets()->attach($this->outlet->id, ['is_default' => true]);
        $own = Transaction::create(['number' => 'OWN-HISTORY', 'outlet_id' => $this->outlet->id, 'warehouse_id' => $this->warehouse->id, 'user_id' => $cashier->id, 'status' => 'completed', 'payment_status' => 'paid', 'grand_total' => 12000, 'occurred_at' => now()]);
        $other = Transaction::create(['number' => 'OTHER-HISTORY', 'outlet_id' => $this->outlet->id, 'warehouse_id' => $this->warehouse->id, 'user_id' => $this->user->id, 'status' => 'completed', 'payment_status' => 'paid', 'grand_total' => 90000, 'occurred_at' => now()]);

        $this->actingAs($cashier)->withSession(['active_outlet_id' => $this->outlet->id])
            ->get(route('transactions.index'))->assertOk()->assertSee('OWN-HISTORY')->assertDontSee('OTHER-HISTORY');
        $this->get(route('transactions.show', $own))->assertOk();
        $this->get(route('transactions.show', $other))->assertNotFound();
        $this->get(route('transactions.receipt', $other))->assertNotFound();
    }

    public function test_cashier_shift_history_excludes_other_cashiers(): void
    {
        $cashier = User::create(['name' => 'Kasir shift', 'email' => 'shift-cashier@example.test', 'password' => 'Test-password-123!', 'is_active' => true]);
        $cashier->assignRole('cashier');
        $cashier->outlets()->attach($this->outlet->id, ['is_default' => true]);
        foreach ([[$cashier, '12500'], [$this->user, '98765']] as [$operator, $openingCash]) {
            CashierShift::create(['user_id' => $operator->id, 'outlet_id' => $this->outlet->id, 'warehouse_id' => $this->warehouse->id, 'status' => 'closed', 'opening_cash' => $openingCash, 'expected_cash' => $openingCash, 'actual_cash' => $openingCash, 'opened_at' => now()->subHour(), 'closed_at' => now()]);
        }

        $this->actingAs($cashier)->withSession(['active_outlet_id' => $this->outlet->id])
            ->get(route('shifts.index'))->assertOk()->assertSee('<td>Kasir shift</td>', false)->assertDontSee('<td>Administrator</td>', false);
    }

    public function test_cashier_permission_migration_removes_finance_access_and_keeps_pos_access(): void
    {
        $role = Role::findByName('cashier', 'web');
        $role->givePermissionTo(['payables-access', 'suppliers-access', 'cash-management-access']);

        $migration = require database_path('migrations/2026_10_06_120000_limit_cashier_finance_permissions.php');
        $migration->up();

        $this->assertFalse($role->fresh()->hasPermissionTo('payables-access'));
        $this->assertFalse($role->fresh()->hasPermissionTo('suppliers-access'));
        $this->assertTrue($role->fresh()->hasPermissionTo('cash-management-access'));
        $this->assertTrue($role->fresh()->hasPermissionTo('pos-access'));
    }

    public function test_finance_dashboard_totals_exclude_another_outlet(): void
    {
        $finance = User::create(['name' => 'Finance', 'email' => 'finance-dashboard@example.test', 'password' => 'Test-password-123!', 'is_active' => true]);
        $finance->assignRole('finance');
        $finance->outlets()->attach($this->outlet->id, ['is_default' => true]);
        $customer = Customer::create(['code' => 'FIN-CUSTOMER', 'name' => 'Pelanggan keuangan', 'is_active' => true]);
        $otherOutlet = Outlet::create(['code' => 'FIN-OTHER', 'slug' => 'fin-other', 'name' => 'Outlet lainnya', 'is_active' => true]);
        $otherWarehouse = Warehouse::create(['outlet_id' => $otherOutlet->id, 'code' => 'FIN-OTHER-WH', 'name' => 'Gudang lainnya', 'type' => 'branch', 'is_active' => true]);

        foreach ([[$this->warehouse, 'FIN-OWN', 30000], [$otherWarehouse, 'FIN-OTHER', 90000]] as [$warehouse, $number, $amount]) {
            $sale = Transaction::create(['number' => $number, 'outlet_id' => $warehouse->outlet_id, 'warehouse_id' => $warehouse->id, 'user_id' => $this->user->id, 'status' => 'completed', 'payment_status' => 'unpaid', 'grand_total' => $amount, 'occurred_at' => now()]);
            Receivable::create(['customer_id' => $customer->id, 'transaction_id' => $sale->id, 'reference' => $number, 'amount' => $amount, 'paid_amount' => 0, 'due_at' => today()->subDay(), 'status' => 'unpaid']);
        }

        $this->actingAs($finance)->withSession(['active_outlet_id' => $this->outlet->id])
            ->get(route('dashboard'))->assertOk()->assertSee('Rp30.000')->assertDontSee('Rp90.000')
            ->assertSee('Piutang jatuh tempo');
    }

    public function test_custom_role_dashboard_lists_only_granted_features(): void
    {
        $role = Role::create(['name' => 'catalog-viewer', 'guard_name' => 'web']);
        $role->givePermissionTo(['dashboard-access', 'products-access']);
        $viewer = User::create(['name' => 'Catalog Viewer', 'email' => 'catalog-viewer@example.test', 'password' => 'Test-password-123!', 'is_active' => true]);
        $viewer->assignRole($role);
        $viewer->outlets()->attach($this->outlet->id, ['is_default' => true]);

        $this->actingAs($viewer)->withSession(['active_outlet_id' => $this->outlet->id])
            ->get(route('dashboard'))->assertOk()->assertSee('Ruang kerja saya')
            ->assertSee('href="'.route('products.index').'"', false)
            ->assertDontSee('href="'.route('payables.index').'"', false)
            ->assertDontSee('Penjualan hari ini');
    }

    public function test_dashboard_periods_compare_the_matching_previous_period(): void
    {
        foreach ([['CURRENT', 0, 125000], ['PREVIOUS-WEEK', 8, 50000], ['PREVIOUS-MONTH', 35, 75000]] as [$number, $daysAgo, $total]) {
            Transaction::query()->create([
                'number' => $number,
                'outlet_id' => $this->outlet->id,
                'warehouse_id' => $this->warehouse->id,
                'user_id' => $this->user->id,
                'status' => 'completed',
                'payment_status' => 'paid',
                'grand_total' => $total,
                'occurred_at' => now()->subDays($daysAgo),
            ]);
        }

        $this->get(route('dashboard', ['period' => 7]))
            ->assertOk()
            ->assertSee('+150,0% dari 7 hari sebelumnya')
            ->assertSee('Penjualan 7 hari terakhir, per hari');

        $this->get(route('dashboard', ['period' => 30]))
            ->assertOk()
            ->assertSee('+133,3% dari 30 hari sebelumnya')
            ->assertSee('dikelompokkan per enam hari')
            ->assertSee('Rp175.000');

        $this->get(route('dashboard', ['period' => 365]))
            ->assertOk()
            ->assertSee('Penjualan 7 hari terakhir, per hari');
    }

    public function test_dashboard_stock_alert_opens_filtered_inventory(): void
    {
        $unit = Unit::create(['name' => 'Pieces', 'symbol' => 'pcs', 'dimension' => 'count', 'is_active' => true]);
        $product = Product::create(['unit_id' => $unit->id, 'sku' => 'LOW-1', 'title' => 'Produk perlu stok', 'is_active' => true, 'track_stock' => true]);
        ProductWarehouse::create(['product_id' => $product->id, 'warehouse_id' => $this->warehouse->id, 'stock' => 2, 'reorder_point' => 5]);
        $otherProduct = Product::create(['unit_id' => $unit->id, 'sku' => 'OTHER-1', 'title' => 'Produk lain', 'is_active' => true, 'track_stock' => true]);
        ProductWarehouse::create(['product_id' => $otherProduct->id, 'warehouse_id' => $this->warehouse->id, 'stock' => 10, 'reorder_point' => 2]);

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Saldo 2,000 / pesan ulang 5,000')
            ->assertSee('search=LOW-1');

        $this->get(route('inventory.index', ['warehouse' => $this->warehouse->id, 'search' => 'LOW-1']))
            ->assertOk()
            ->assertSee('Produk perlu stok')
            ->assertDontSee('Produk lain');
    }

    public function test_dashboard_warehouse_filter_scopes_sales_and_report_links(): void
    {
        $secondWarehouse = Warehouse::create(['outlet_id' => $this->outlet->id, 'code' => 'SECOND-WH', 'name' => 'Gudang kedua', 'type' => 'branch', 'is_active' => true]);
        $otherOutlet = Outlet::create(['code' => 'DISTANT', 'slug' => 'distant', 'name' => 'Outlet lain', 'is_active' => true]);
        $otherWarehouse = Warehouse::create(['outlet_id' => $otherOutlet->id, 'code' => 'DISTANT-WH', 'name' => 'Gudang luar', 'type' => 'branch', 'is_active' => true]);

        foreach ([[$this->warehouse, 'VISIBLE', 10000, 'Dimsum pilihan'], [$secondWarehouse, 'HIDDEN-WH', 50000, 'Produk gudang kedua'], [$otherWarehouse, 'HIDDEN-OUTLET', 90000, 'Produk outlet lain']] as [$warehouse, $number, $total, $product]) {
            $transaction = Transaction::create(['number' => $number, 'outlet_id' => $warehouse->outlet_id, 'warehouse_id' => $warehouse->id, 'user_id' => $this->user->id, 'status' => 'completed', 'payment_status' => 'paid', 'grand_total' => $total, 'occurred_at' => now()]);
            TransactionDetail::create(['transaction_id' => $transaction->id, 'product_name' => $product, 'unit_name' => 'pcs', 'quantity' => 2, 'unit_price' => $total / 2, 'line_total' => $total]);
        }

        $this->get(route('dashboard', ['warehouse' => $this->warehouse->id]))
            ->assertOk()->assertSee('Rp10.000')->assertSee('Dimsum pilihan')
            ->assertDontSee('HIDDEN-WH')->assertDontSee('HIDDEN-OUTLET')
            ->assertDontSee('Produk gudang kedua')->assertDontSee('Produk outlet lain')
            ->assertSee('id="dashboard-show-table"', false)
            ->assertSee('from='.today()->toDateString().'&amp;to='.today()->toDateString().'&amp;warehouse='.$this->warehouse->id, false);

        $this->get(route('dashboard', ['warehouse' => $otherWarehouse->id]))->assertOk()->assertSee('Rp90.000');
        $this->get(route('reports.sales', ['from' => today()->toDateString(), 'to' => today()->toDateString(), 'warehouse' => $this->warehouse->id]))
            ->assertOk()->assertSee('Rp 10.000')->assertDontSee('HIDDEN-WH')->assertDontSee('HIDDEN-OUTLET');
        $this->get(route('reports.sales', ['warehouse' => $otherWarehouse->id]))->assertOk()->assertSee('HIDDEN-OUTLET');
        $finance = User::create(['name' => 'Finance terbatas', 'email' => 'finance-limited@example.test', 'password' => 'Test-password-123!', 'is_active' => true]);
        $finance->assignRole('finance');
        $finance->outlets()->attach($this->outlet->id, ['is_default' => true]);
        $this->flushSession();
        $this->actingAs($finance)->withSession(['active_outlet_id' => $this->outlet->id]);
        $this->get(route('dashboard', ['warehouse' => $otherWarehouse->id]))->assertNotFound();
        $this->get(route('reports.sales', ['warehouse' => $otherWarehouse->id]))->assertNotFound();
        Livewire::test(SalesReport::class)->set('warehouseId', (string) $otherWarehouse->id)->assertNotFound();
    }

    public function test_sales_report_combines_permitted_outlets_and_filters_by_outlet_name(): void
    {
        $secondOutlet = Outlet::create(['code' => 'SALES-SECOND', 'slug' => 'sales-second', 'name' => 'Outlet Pekayon', 'is_active' => true]);
        $secondWarehouse = Warehouse::create(['outlet_id' => $secondOutlet->id, 'code' => 'SALES-SECOND-WH', 'name' => 'Gudang Pekayon', 'type' => 'branch', 'is_active' => true]);
        foreach ([[$this->warehouse, 'FIRST-SALE', 10000], [$secondWarehouse, 'SECOND-SALE', 20000]] as [$warehouse, $number, $total]) {
            Transaction::create(['number' => $number, 'outlet_id' => $warehouse->outlet_id, 'warehouse_id' => $warehouse->id, 'user_id' => $this->user->id, 'status' => 'completed', 'payment_status' => 'paid', 'grand_total' => $total, 'occurred_at' => now()]);
        }

        $this->get(route('reports.sales'))->assertOk()->assertSee('FIRST-SALE')->assertSee('SECOND-SALE')->assertSee('Outlet Pekayon')->assertSee('sales-chart-daily')->assertSee('sales-chart-outlets');
        $this->get(route('reports.sales', ['outlet' => $secondOutlet->id]))->assertOk()->assertSee('SECOND-SALE')->assertDontSee('FIRST-SALE');
        $finance = User::create(['name' => 'Finance outlet satu', 'email' => 'finance-first@example.test', 'password' => 'Test-password-123!', 'is_active' => true]);
        $finance->assignRole('finance');
        $finance->outlets()->attach($this->outlet->id, ['is_default' => true]);
        $this->flushSession();
        $this->actingAs($finance)->withSession(['active_outlet_id' => $this->outlet->id]);
        $this->get(route('reports.sales'))->assertOk()->assertSee('FIRST-SALE')->assertDontSee('SECOND-SALE');
        $this->get(route('reports.sales', ['outlet' => $secondOutlet->id]))->assertNotFound();
        Livewire::test(ProfitReport::class)->set('outletId', (string) $secondOutlet->id)->assertNotFound();
        Livewire::test(InventoryReport::class)->set('outletId', (string) $secondOutlet->id)->assertNotFound();
        Livewire::test(AgingReport::class)->set('outletId', (string) $secondOutlet->id)->assertNotFound();
        Livewire::test(TransactionsIndex::class)->set('outletId', (string) $secondOutlet->id)->assertNotFound();
    }

    public function test_dashboard_notification_links_open_the_authorized_work_area(): void
    {
        OperationalNotification::create(['outlet_id' => $this->outlet->id, 'type' => 'receivable-overdue', 'title' => 'Piutang harus ditagih', 'reference_type' => 'receivable', 'reference_id' => 1]);
        $otherOutlet = Outlet::create(['code' => 'NOTICE-OTHER', 'slug' => 'notice-other', 'name' => 'Outlet rahasia', 'is_active' => true]);
        OperationalNotification::create(['outlet_id' => $otherOutlet->id, 'type' => 'receivable-overdue', 'title' => 'Piutang outlet rahasia']);

        $this->get(route('dashboard'))->assertOk()->assertSee('Piutang harus ditagih')
            ->assertSee('href="'.route('receivables.index').'"', false)
            ->assertSee('Piutang outlet rahasia');
    }

    public function test_forged_role_form_cannot_remove_super_admin_permissions(): void
    {
        $role = Role::findByName('super-admin');
        $count = $role->permissions()->count();
        Livewire::test(RolesIndex::class)->set('roleId', $role->id)->set('name', 'super-admin')->set('permissionIds', [])->call('save')->assertForbidden();
        $this->assertSame($count, $role->fresh()->permissions()->count());
    }

    public function test_unit_conversion_is_reciprocal_and_rejects_unrepresentable_factor(): void
    {
        $kg = Unit::create(['name' => 'Kilogram', 'symbol' => 'kg', 'dimension' => 'mass', 'is_active' => true]);
        $gram = Unit::create(['name' => 'Gram', 'symbol' => 'g', 'dimension' => 'mass', 'is_active' => true]);
        Livewire::test(UnitsIndex::class)->set('fromUnitId', (string) $kg->id)->set('toUnitId', (string) $gram->id)->set('factor', '1000')->call('saveConversion')->assertHasNoErrors();
        $this->assertDatabaseHas('unit_conversions', ['from_unit_id' => $gram->id, 'to_unit_id' => $kg->id, 'factor' => '0.00100000']);
        Livewire::test(UnitsIndex::class)->set('fromUnitId', (string) $kg->id)->set('toUnitId', (string) $gram->id)->set('factor', '999999999999')->call('saveConversion')->assertHasErrors('factor');
    }

    public function test_inventory_export_combines_permitted_outlets_and_filters_by_outlet(): void
    {
        $unit = Unit::create(['name' => 'Pieces', 'symbol' => 'pcs', 'dimension' => 'count', 'is_active' => true]);
        $product = Product::create(['unit_id' => $unit->id, 'sku' => 'EXPORT-1', 'title' => '=Unsafe name', 'buy_price' => 4000, 'is_active' => true]);
        ProductWarehouse::create(['product_id' => $product->id, 'warehouse_id' => $this->warehouse->id, 'stock' => 5]);
        $other = Outlet::create(['code' => 'OTHER', 'slug' => 'other', 'name' => 'Other', 'is_active' => true]);
        $otherWarehouse = Warehouse::create(['outlet_id' => $other->id, 'code' => 'OTHER-WH', 'name' => 'Other warehouse', 'type' => 'branch', 'is_active' => true]);
        ProductWarehouse::create(['product_id' => $product->id, 'warehouse_id' => $otherWarehouse->id, 'stock' => 100]);
        $test = Livewire::test(InventoryReport::class)->call('export')->assertFileDownloaded();
        $csv = base64_decode($test->effects['download']['content']);
        $this->assertStringContainsString('20000', $csv);
        $this->assertStringContainsString("'=Unsafe name", $csv);
        $this->assertStringContainsString('Other warehouse', $csv);
        $filtered = Livewire::test(InventoryReport::class)->set('outletId', (string) $this->outlet->id)->call('export')->assertFileDownloaded();
        $filteredCsv = base64_decode($filtered->effects['download']['content']);
        $this->assertStringNotContainsString('Other warehouse', $filteredCsv);
    }

    public function test_failed_payment_configuration_does_not_flash_credentials(): void
    {
        $this->from('/settings/payments')->put('/settings/payments', ['providers' => ['midtrans' => ['mode' => 'invalid', 'credentials' => ['server_key' => 'sensitive-test-value']], 'xendit' => ['mode' => 'sandbox']]])
            ->assertSessionHasErrors('providers.midtrans.mode')->assertSessionMissing('_old_input');
        $this->assertDatabaseCount('payment_settings', 0);
    }
}
