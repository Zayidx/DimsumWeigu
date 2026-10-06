<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        $cashier = Role::query()->where('name', 'cashier')->where('guard_name', 'web')->first();

        if (! $cashier) {
            return;
        }

        $permissions = $cashier->permissions()->whereIn('name', [
            'sales-analysis-access',
            'outlet-operations-access', 'outlet-operations-create',
            'expenses-access', 'expenses-create', 'waste-access', 'waste-create',
            'receivables-access', 'receivables-pay', 'receivables-collect',
            'payables-access', 'payables-pay',
            'suppliers-access',
            'dine-orders-access', 'dine-orders-process', 'dine-in-access', 'dine-in-process',
        ])->get();

        foreach ($permissions as $permission) {
            $cashier->revokePermissionTo($permission);
        }
    }

    public function down(): void
    {
        // Restoring permissions could grant access that was never assigned to this cashier role.
    }
};
