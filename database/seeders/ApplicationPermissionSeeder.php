<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

class ApplicationPermissionSeeder extends Seeder
{
    public function run(): void
    {
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
            'transactions-access', 'transactions-refund', 'transaction-corrections-create', 'transaction-corrections-access', 'transaction-corrections-update', 'cashier-sales-report-access', 'cashier-shifts-access', 'cashier-shifts-open',
            'qris-reconciliations-access', 'qris-reconciliations-create', 'replenishment-access',
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
            'homepage-settings-access', 'homepage-settings-update',
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

    }
}
