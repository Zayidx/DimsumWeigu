<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        $permission = Permission::query()->firstOrCreate([
            'name' => 'cashier-sales-report-access',
            'guard_name' => 'web',
        ]);

        Role::query()->where('guard_name', 'web')->whereIn('name', ['cashier', 'super-admin'])->get()
            ->each(fn (Role $role) => $role->givePermissionTo($permission));

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        $permission = Permission::query()->where('name', 'cashier-sales-report-access')->where('guard_name', 'web')->first();

        if ($permission) {
            Role::query()->where('guard_name', 'web')->whereIn('name', ['cashier', 'super-admin'])->get()
                ->each(fn (Role $role) => $role->revokePermissionTo($permission));
            $permission->delete();
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
