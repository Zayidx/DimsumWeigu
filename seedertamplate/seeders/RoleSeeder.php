<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    // Refactor the RoleSeeder to improve readability and avoid repetitive code
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->normalizeLegacyPermissionRole();

        $this->createRoleWithPermissions('users-access', '%users%');
        $this->createRoleWithPermissions('roles-access', '%roles%');
        $this->createRoleWithPermissions('permissions-access', '%permissions%');
        $this->createRoleWithPermissions('categories-access', '%categories%');
        $this->createRoleWithPermissions('products-access', '%products%');
        $this->createRoleWithPermissions('pricing-rules-access', '%pricing-rules%');
        $this->createRoleWithPermissions('customers-access', '%customers%');
        $this->createRoleWithPermissions('customer-vouchers-access', '%customer-vouchers%');
        $this->createRoleWithPermissions('customer-segments-access', '%customer-segments%');
        $this->createRoleWithPermissions('crm-campaigns-access', '%crm-campaigns%');
        $this->createRoleWithPermissions('crm-reminders-access', '%crm-reminders%');
        $this->createRoleWithPermissions('transactions-access', '%transactions%');
        $this->createRoleWithPermissions('transactions-confirm-payment', 'transactions-confirm-payment');
        $this->createRoleWithPermissions('receivables-access', '%receivables%');
        $this->createRoleWithPermissions('payables-access', '%payables%');
        $this->createRoleWithPermissions('suppliers-access', '%suppliers%');
        $this->createRoleWithPermissions('suppliers-create', 'suppliers-create');
        $this->createRoleWithPermissions('suppliers-update', 'suppliers-update');
        $this->createRoleWithPermissions('suppliers-delete', 'suppliers-delete');
        $this->createRoleWithPermissions('reports-access', '%reports%');
        $this->createRoleWithPermissions('profits-access', '%profits%');
        $this->createRoleWithPermissions('payment-settings-access', '%payment-settings%');
        $this->createRoleWithPermissions('payment-settings-update', 'payment-settings-update');
        $this->createRoleWithPermissions('stock-opnames-access', '%stock-opnames%');
        $this->createRoleWithPermissions('stock-mutations-access', '%stock-mutations%');
        $this->createRoleWithPermissions('sales-returns-access', '%sales-returns%');
        $this->createRoleWithPermissions('cashier-shifts-access', '%cashier-shifts%');
        $this->createRoleWithPermissions('audit-logs-access', '%audit-logs%');
        $this->createRoleWithPermissions('purchase-orders-access', '%purchase-orders%');
        $this->createRoleWithPermissions('goods-receivings-access', '%goods-receivings%');
        $this->createRoleWithPermissions('supplier-returns-access', '%supplier-returns%');
        $this->createRoleWithPermissions('stock-transfers-access', '%stock-transfers%');
        $this->createRoleWithPermissions('products-import', '%products-import%');
        $this->createRoleWithPermissions('products-export', '%products-export%');
        $this->createRoleWithPermissions('customers-import', '%customers-import%');
        $this->createRoleWithPermissions('customers-export', '%customers-export%');
        $this->createRoleWithPermissions('ingredients-import', '%ingredients-import%');
        $this->createRoleWithPermissions('ingredients-export', '%ingredients-export%');
        $this->createRoleWithPermissions('suppliers-import', '%suppliers-import%');
        $this->createRoleWithPermissions('suppliers-export', '%suppliers-export%');
        $this->createRoleWithPermissions('recipes-import', '%recipes-import%');
        $this->createRoleWithPermissions('recipes-export', '%recipes-export%');
        $this->createRoleWithPermissions('inventory-opening-stock-import', '%inventory-opening-stock-import%');
        $this->createRoleWithPermissions('discounts-approve', 'discounts-approve');
        $this->createRoleWithPermissions('price-lists-access', '%price-lists%');
        $this->createRoleWithPermissions('warehouses-access', '%warehouses%');
        $this->createRoleWithPermissions('units-access', '%units%');

        $this->createRoleWithPermissions('dine-tables-access', '%dine-tables%');
        $this->createRoleWithPermissions('dine-orders-access', '%dine-orders%');

        $superAdminRole = Role::firstOrCreate(['name' => 'super-admin']);
        $superAdminRole->syncPermissions(Permission::all());

        // Create cashier role with basic permissions for public registration
        $cashierRole = Role::firstOrCreate(['name' => 'cashier']);
        $cashierPermissions = Permission::whereIn('name', [
            'dashboard-access',
            'transactions-access',
            'pos-access',
            'cashier-shifts-access',
            'cashier-shifts-open',
            'cashier-shifts-close',
            'sales-analysis-access',
            'outlet-operations-access',
            'outlet-operations-create',
            'cash-handovers-access',
            'cash-handovers-create',
            'outlet-stock-returns-access',
            'outlet-stock-returns-create',
            'customers-access',
            'customers-create',
            'receivables-access',
            'receivables-pay',
            'payables-access',
            'payables-pay',
            'suppliers-access',
            'dine-orders-access',
            'dine-orders-process',
        ])->get();
        $cashierRole->syncPermissions($cashierPermissions);

        $this->createManagerRole();
        $this->createFinanceRole();
        $this->createWarehouseRole();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /** Manager can monitor day-to-day activity without changing records. */
    private function createManagerRole(): void
    {
        $managerRole = Role::firstOrCreate(['name' => 'manager']);
        $managerRole->syncPermissions(Permission::whereIn('name', [
            'dashboard-access',
            'categories-access',
            'products-access',
            'customers-access',
            'suppliers-access',
            'transactions-access',
            'receivables-access',
            'payables-access',
            'reports-access',
            'sales-analysis-access',
            'profits-access',
            'warehouses-access',
            'units-access',
            'purchase-orders-access',
            'goods-receivings-access',
            'stock-transfers-access',
            'stock-opnames-access',
            'stock-mutations-access',
            'sales-returns-access',
            'cashier-shifts-access',
            'audit-logs-access',
            'production-requests-access',
            'production-orders-access',
            'outlet-operations-access',
            'cash-handovers-access',
            'cash-pickups-access',
            'outlet-stock-returns-access',
            'ingredients-access',
            'recipes-access',
            'recipes-export',
        ])->get());
    }

    private function createFinanceRole(): void
    {
        $role = Role::firstOrCreate(['name' => 'finance']);
        $role->syncPermissions(Permission::whereIn('name', [
            'dashboard-access',
            'suppliers-access',
            'suppliers-create',
            'suppliers-update',
            'suppliers-delete',
            'purchase-orders-access',
            'purchase-orders-create',
            'purchase-orders-update',
            'goods-receivings-access',
            'payables-access',
            'payables-pay',
            'receivables-access',
            'reports-access',
            'sales-analysis-access',
            'profits-access',
            'stock-opnames-access',
            'warehouses-access',
            'audit-logs-access',
            'production-requests-access',
            'production-orders-access',
            'production-requests-approve',
            'production-requests-reject',
            'ingredients-access',
            'ingredients-import',
            'ingredients-export',
            'suppliers-import',
            'suppliers-export',
            'recipes-access',
            'recipes-export',
        ])->get());
    }

    private function createWarehouseRole(): void
    {
        $role = Role::firstOrCreate(['name' => 'warehouse']);
        $role->syncPermissions(Permission::whereIn('name', [
            'dashboard-access',
            'categories-access',
            'categories-create',
            'categories-edit',
            'products-access',
            'products-create',
            'products-edit',
            'purchase-orders-access',
            'goods-receivings-access',
            'goods-receivings-create',
            'supplier-returns-access',
            'supplier-returns-create',
            'warehouses-access',
            'units-access',
            'units-create',
            'units-update',
            'stock-opnames-access',
            'stock-opnames-create',
            'stock-opnames-finalize',
            'stock-mutations-access',
            'stock-transfers-access',
            'stock-transfers-create',
            'stock-transfers-send',
            'stock-transfers-receive',
            'stock-transfers-cancel',
            'production-requests-access',
            'production-requests-create',
            'production-orders-access',
            'production-orders-create',
            'production-orders-complete',
            'outlet-operations-access',
            'cash-handovers-access',
            'cash-handovers-confirm',
            'cash-pickups-access',
            'cash-pickups-confirm',
            'outlet-stock-returns-access',
            'outlet-stock-returns-receive',
            'ingredients-access',
            'ingredients-create',
            'ingredients-update',
            'ingredients-adjust',
            'recipes-access',
            'recipes-create',
            'recipes-export',
            'recipes-import',
            'inventory-opening-stock-import',
        ])->get());
    }

    private function normalizeLegacyPermissionRole(): void
    {
        $legacyRole = Role::where('name', 'permission-access')->first();

        if (! $legacyRole) {
            return;
        }

        $finalRole = Role::firstOrCreate([
            'name' => 'permissions-access',
            'guard_name' => $legacyRole->guard_name,
        ]);

        if (DB::getSchemaBuilder()->hasTable('model_has_roles')) {
            DB::table('model_has_roles')
                ->where('role_id', $legacyRole->id)
                ->update(['role_id' => $finalRole->id]);
        }

        if (DB::getSchemaBuilder()->hasTable('role_has_permissions')) {
            DB::table('role_has_permissions')
                ->where('role_id', $legacyRole->id)
                ->update(['role_id' => $finalRole->id]);
        }

        $legacyRole->delete();
    }

    private function createRoleWithPermissions($roleName, $permissionNamePattern)
    {
        $permissions = Permission::where('name', 'like', $permissionNamePattern)->get();
        $role = Role::firstOrCreate(['name' => $roleName]);
        $role->syncPermissions($permissions);
    }
}
