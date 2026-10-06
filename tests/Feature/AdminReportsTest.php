<?php

namespace Tests\Feature;

use App\Livewire\Admin\RolesIndex;
use App\Livewire\Catalog\UnitsIndex;
use App\Livewire\Reports\InventoryReport;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\ProductWarehouse;
use App\Models\Transaction;
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
            ->assertSee('Outlet aktif')
            ->assertSee('Menu</span>', false)
            ->assertDontSee('aria-label="Buka menu"', false)
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

    public function test_inventory_export_includes_cost_and_excludes_another_outlet(): void
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
        $this->assertStringNotContainsString('Other warehouse', $csv);
    }

    public function test_failed_payment_configuration_does_not_flash_credentials(): void
    {
        $this->from('/settings/payments')->put('/settings/payments', ['providers' => ['midtrans' => ['mode' => 'invalid', 'credentials' => ['server_key' => 'sensitive-test-value']], 'xendit' => ['mode' => 'sandbox']]])
            ->assertSessionHasErrors('providers.midtrans.mode')->assertSessionMissing('_old_input');
        $this->assertDatabaseCount('payment_settings', 0);
    }
}
