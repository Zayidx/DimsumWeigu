<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private array $permissions = ['homepage-settings-access', 'homepage-settings-update'];

    public function up(): void
    {
        $permissions = collect($this->permissions)->map(fn (string $name) => Permission::query()->firstOrCreate([
            'name' => $name,
            'guard_name' => 'web',
        ]));

        Role::query()->where('guard_name', 'web')->whereIn('name', ['manager', 'super-admin'])->get()
            ->each(fn (Role $role) => $role->givePermissionTo($permissions));

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        $permissions = Permission::query()->where('guard_name', 'web')->whereIn('name', $this->permissions)->get();

        Role::query()->where('guard_name', 'web')->whereIn('name', ['manager', 'super-admin'])->get()
            ->each(fn (Role $role) => $role->revokePermissionTo($permissions));

        Permission::query()->where('guard_name', 'web')->whereIn('name', $this->permissions)->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
