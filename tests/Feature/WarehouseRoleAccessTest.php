<?php

namespace Tests\Feature;

use App\Models\Outlet;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WarehouseRoleAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_warehouse_cashier_and_finance_only_open_their_operational_pages(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(UserSeeder::class);
        $outlet = Outlet::where('code', 'GAL-BP')->firstOrFail();
        $cashier = User::where('email', 'cashier@gmail.com')->firstOrFail();
        $warehouse = User::where('email', 'warehouse@gmail.com')->firstOrFail();
        $finance = User::where('email', 'finance@gmail.com')->firstOrFail();

        $this->as($cashier, $outlet->id);
        $this->get(route('cashier-stock-transfers.index'))->assertOk();
        $this->assertFalse($cashier->can('production-orders-access'));
        $this->assertFalse($cashier->can('warehouse-finance-handovers-access'));

        $this->as($warehouse, $outlet->id);
        $this->get(route('warehouse-cash-receipts.index'))->assertOk();
        $this->get(route('warehouse-finance-handovers.index'))->assertOk();
        $this->assertFalse($warehouse->hasRole('finance'));

        $this->as($finance, $outlet->id);
        $this->get(route('warehouse-finance-handovers.index'))->assertOk();
        $this->get(route('reports.warehouse-production'))->assertOk();
        $this->assertFalse($finance->can('cashier-stock-move'));
    }

    private function as(User $user, int $outletId): void
    {
        $this->actingAs($user)->withSession([
            'active_outlet_id' => $outletId,
            'auth.password_confirmed_at' => now()->timestamp,
            'password_hash_web' => $user->getAuthPassword(),
        ]);
    }
}
