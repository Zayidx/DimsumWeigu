<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private array $permissions = [
        'qris-reconciliations-access',
        'qris-reconciliations-create',
        'replenishment-access',
    ];

    public function up(): void
    {
        Schema::create('qris_reconciliations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('outlet_id')->constrained()->restrictOnDelete();
            $table->date('sales_date');
            $table->date('settlement_date');
            $table->decimal('reported_gross', 15, 2);
            $table->decimal('fee_amount', 15, 2)->default(0);
            $table->decimal('net_received', 15, 2);
            $table->decimal('pos_gross_at_reconciliation', 15, 2);
            $table->string('merchant_reference', 160)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['outlet_id', 'sales_date']);
            $table->index(['outlet_id', 'settlement_date']);
        });

        $permissions = collect($this->permissions)->map(fn (string $name) => Permission::query()->firstOrCreate([
            'name' => $name,
            'guard_name' => 'web',
        ]));

        Role::query()->where('guard_name', 'web')->whereIn('name', ['finance', 'super-admin'])->get()
            ->each(fn (Role $role) => $role->givePermissionTo($permissions->whereIn('name', ['qris-reconciliations-access', 'qris-reconciliations-create'])));
        Role::query()->where('guard_name', 'web')->whereIn('name', ['manager', 'warehouse', 'super-admin'])->get()
            ->each(fn (Role $role) => $role->givePermissionTo($permissions->where('name', 'replenishment-access')));

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        $permissions = Permission::query()->where('guard_name', 'web')->whereIn('name', $this->permissions)->get();
        Role::query()->where('guard_name', 'web')->whereIn('name', ['finance', 'manager', 'warehouse', 'super-admin'])->get()
            ->each(fn (Role $role) => $role->revokePermissionTo($permissions));
        Permission::query()->where('guard_name', 'web')->whereIn('name', $this->permissions)->delete();
        Schema::dropIfExists('qris_reconciliations');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
