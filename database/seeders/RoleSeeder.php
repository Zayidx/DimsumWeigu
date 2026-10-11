<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
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
        $this->grant($superAdminRole, Permission::all());
        $superAdminRole->givePermissionTo(Permission::whereIn('name', [
            'cashier-stock-move', 'warehouse-finance-handovers-access',
            'warehouse-finance-handovers-create', 'warehouse-finance-handovers-confirm',
        ])->get());

        // Create cashier role with basic permissions for public registration
        $cashierRole = Role::firstOrCreate(['name' => 'cashier']);
        $cashierPermissions = Permission::whereIn('name', [
            'dashboard-access',
            'transactions-access',
            'cashier-sales-report-access',
            'transaction-corrections-create',
            'pos-access',
            'cashier-shifts-access',
            'cashier-shifts-open',
            'cashier-shifts-close',
            'cash-handovers-access',
            'cash-handovers-create',
            'outlet-stock-returns-access',
            'outlet-stock-returns-create',
            'customers-access',
            'customers-create',
        ])->get();
        $this->grant($cashierRole, $cashierPermissions);
        $cashierRole->revokePermissionTo(Permission::whereIn('name', ['expenses-access', 'expenses-create'])->get());
        // Existing cashier roles receive only the new read-only report permission.
        $cashierRole->givePermissionTo(Permission::where('name', 'cashier-sales-report-access')->get());
        $cashierRole->givePermissionTo(Permission::where('name', 'transaction-corrections-create')->get());
        $cashierRole->givePermissionTo(Permission::where('name', 'cashier-stock-move')->get());

        $this->createManagerRole();
        $this->createFinanceRole();
        $this->createWarehouseRole();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /** Manager monitors operations and may update the public homepage content. */
    private function createManagerRole(): void
    {
        $managerRole = Role::firstOrCreate(['name' => 'manager']);
        $this->grant($managerRole, Permission::whereIn('name', [
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
            'replenishment-access',
        ])->get());
        // Add review and settings capabilities without replacing a customized role.
        $managerRole->givePermissionTo(Permission::whereIn('name', [
            'homepage-settings-access',
            'homepage-settings-update',
            'cash-management-access',
            'cash-management-approve',
        ])->get());
    }

    private function createFinanceRole(): void
    {
        $role = Role::firstOrCreate(['name' => 'finance']);
        $this->grant($role, Permission::whereIn('name', [
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
            'transaction-corrections-access',
            'transaction-corrections-update',
            'qris-reconciliations-access',
            'qris-reconciliations-create',
        ])->get());
        $role->givePermissionTo(Permission::whereIn('name', ['transaction-corrections-access', 'transaction-corrections-update'])->get());
        $role->givePermissionTo(Permission::whereIn('name', [
            'bank-accounts-access',
            'cash-management-access',
            'cash-management-approve',
            'cash-handovers-confirm',
            'warehouse-finance-handovers-access',
            'warehouse-finance-handovers-confirm',
        ])->get());
    }

    private function createWarehouseRole(): void
    {
        $role = Role::firstOrCreate(['name' => 'warehouse']);
        $this->grant($role, Permission::whereIn('name', [
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
            'replenishment-access',
        ])->get());
        $role->givePermissionTo(Permission::whereIn('name', [
            'warehouse-finance-handovers-access', 'warehouse-finance-handovers-create',
        ])->get());
    }

    private function createRoleWithPermissions($roleName, $permissionNamePattern)
    {
        $permissions = Permission::where('name', 'like', $permissionNamePattern)->get();
        $role = Role::firstOrCreate(['name' => $roleName]);
        $this->grant($role, $permissions);
    }

    private function grant(Role $role, $permissions): void
    {
        // Preserve roles customized after setup. New roles receive the source policy.
        if (! $role->wasRecentlyCreated) {
            return;
        }
        $aliases = [
            'users-update' => ['users-edit'], 'roles-update' => ['roles-edit'], 'units-update' => ['units-edit'],
            'pos-access' => ['pos-checkout', 'pos-hold'], 'receivables-pay' => ['receivables-collect'],
            'stock-transfers-access' => ['transfers-access'], 'stock-transfers-create' => ['transfers-create'],
            'stock-transfers-send' => ['transfers-send'], 'stock-transfers-receive' => ['transfers-receive'], 'stock-transfers-cancel' => ['transfers-cancel'],
            'stock-mutations-access' => ['inventory-access', 'inventory-ledger-access'],
            'outlet-operations-access' => ['expenses-access', 'waste-access'], 'outlet-operations-create' => ['expenses-create', 'waste-create'],
            'cash-handovers-access' => ['cash-management-access'], 'cash-handovers-create' => ['cash-management-create'],
            'dine-orders-access' => ['dine-in-access'], 'dine-orders-process' => ['dine-in-process'],
            'production-requests-access' => ['production-access'], 'production-orders-access' => ['production-access'],
            'production-requests-create' => ['production-request'], 'production-requests-approve' => ['production-approve'],
            'production-orders-complete' => ['production-complete'],
            'inventory-opening-stock-import' => ['inventory-opening'],
        ];
        $names = $permissions->pluck('name')->all();
        foreach ($names as $name) {
            $names = array_merge($names, $aliases[$name] ?? []);
        }
        $role->syncPermissions(Permission::where('guard_name', 'web')->whereIn('name', array_unique($names))->get());
    }
}
