<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = [
            'dashboard-access', 'users-access', 'users-create', 'users-edit', 'users-delete',
            'roles-access', 'roles-create', 'roles-edit', 'roles-delete',
            'outlets-access', 'outlets-create', 'outlets-update', 'outlets-delete',
            'warehouses-access', 'warehouses-create', 'warehouses-update', 'warehouses-delete',
            'categories-access', 'categories-create', 'categories-edit', 'categories-delete',
            'products-access', 'products-create', 'products-edit', 'products-delete',
            'units-access', 'units-create', 'units-edit', 'units-delete',
            'inventory-access', 'inventory-opening', 'inventory-adjust', 'inventory-ledger-access',
            'batches-access', 'batches-create', 'batches-update',
            'transfers-access', 'transfers-create', 'transfers-send', 'transfers-receive', 'transfers-cancel',
            'stock-opnames-access', 'stock-opnames-create', 'stock-opnames-review', 'stock-opnames-finalize',
            'pos-access', 'pos-checkout', 'pos-hold', 'pos-discount', 'pos-returns',
            'transactions-access', 'transactions-refund', 'cashier-shifts-access', 'cashier-shifts-open',
            'cashier-shifts-close', 'cashier-shifts-force-close', 'cash-management-access', 'cash-management-create', 'cash-management-approve',
            'customers-access', 'customers-create', 'customers-edit', 'customers-delete',
            'suppliers-access', 'suppliers-create', 'suppliers-update', 'suppliers-delete',
            'purchase-orders-access', 'purchase-orders-create', 'purchase-orders-update', 'purchase-orders-order', 'purchase-orders-cancel',
            'goods-receivings-access', 'goods-receivings-create', 'supplier-returns-access', 'supplier-returns-create', 'supplier-returns-approve',
            'payables-access', 'payables-pay', 'receivables-access', 'receivables-collect',
            'expenses-access', 'expenses-create', 'waste-access', 'waste-create',
            'price-lists-access', 'price-lists-create', 'price-lists-edit', 'promotions-access', 'promotions-manage',
            'vouchers-access', 'vouchers-manage', 'loyalty-access', 'loyalty-adjust',
            'crm-access', 'crm-manage', 'crm-send', 'dine-in-access', 'dine-in-process', 'dine-in-manage',
            'store-settings-access', 'store-settings-update', 'payment-settings-access', 'payment-settings-update',
            'bank-accounts-access', 'bank-accounts-manage', 'printer-settings-access',
            'reports-access', 'reports-export', 'imports-access', 'imports-run', 'exports-run',
            'api-tokens-access', 'api-tokens-manage', 'audit-logs-access', 'notifications-access', 'notifications-manage',
            'ingredients-access', 'ingredients-manage', 'recipes-access', 'recipes-manage',
            'production-access', 'production-request', 'production-approve', 'production-complete',
            'discount-approvals-access', 'discount-approvals-decide',
        ];

        foreach ($permissions as $permission) {
            Permission::query()->firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        $all = Permission::query()->where('guard_name', 'web')->get();
        Role::query()->firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web'])->syncPermissions($all);

        $roleMap = [
            'manager' => fn (string $name) => ! str_starts_with($name, 'users-') && ! str_starts_with($name, 'roles-') && ! str_starts_with($name, 'payment-settings-') && ! str_starts_with($name, 'api-tokens-') && $name !== 'cashier-shifts-force-close',
            'finance' => fn (string $name) => self::startsWithAny($name, ['dashboard-', 'reports-', 'payables-', 'receivables-', 'expenses-', 'cash-management-', 'payment-settings-', 'bank-accounts-', 'transactions-', 'customers-', 'audit-logs-', 'notifications-']) || in_array($name, ['supplier-returns-access', 'supplier-returns-approve', 'waste-access'], true),
            'cashier' => fn (string $name) => self::startsWithAny($name, ['dashboard-', 'pos-', 'cashier-shifts-', 'customers-', 'transactions-', 'dine-in-']) && $name !== 'cashier-shifts-force-close' || in_array($name, ['cash-management-access', 'cash-management-create'], true),
            'warehouse' => fn (string $name) => self::startsWithAny($name, ['dashboard-', 'inventory-', 'batches-', 'transfers-', 'stock-opnames-', 'products-access', 'categories-access', 'units-access', 'suppliers-', 'purchase-orders-', 'goods-receivings-', 'supplier-returns-', 'ingredients-', 'recipes-', 'production-', 'waste-', 'reports-access', 'reports-export']),
        ];

        foreach ($roleMap as $roleName => $allows) {
            $role = Role::query()->firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
            $role->syncPermissions($all->filter(fn (Permission $permission) => $allows($permission->name)));
        }

        Role::query()->firstOrCreate(['name' => 'customer', 'guard_name' => 'web'])->syncPermissions([]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private static function startsWithAny(string $value, array $prefixes): bool
    {
        foreach ($prefixes as $prefix) {
            if (str_starts_with($value, $prefix)) {
                return true;
            }
        }

        return false;
    }
}
