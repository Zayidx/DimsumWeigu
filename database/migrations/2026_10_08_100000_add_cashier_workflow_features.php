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
        'transaction-corrections-create',
        'transaction-corrections-access',
        'transaction-corrections-update',
    ];

    public function up(): void
    {
        Schema::create('cashier_favorite_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'product_id']);
        });

        Schema::create('transaction_correction_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->constrained()->restrictOnDelete();
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 24)->default('pending')->index();
            $table->text('reason');
            $table->text('finance_note')->nullable();
            $table->timestamps();
            $table->index(['requested_by', 'created_at']);
            $table->index(['status', 'created_at']);
        });

        $permissions = collect($this->permissions)->map(fn (string $name) => Permission::query()->firstOrCreate([
            'name' => $name,
            'guard_name' => 'web',
        ]));

        Role::query()->where('guard_name', 'web')->where('name', 'cashier')->first()
            ?->givePermissionTo($permissions->where('name', 'transaction-corrections-create'));
        Role::query()->where('guard_name', 'web')->whereIn('name', ['finance', 'super-admin'])->get()
            ->each(fn (Role $role) => $role->givePermissionTo($permissions->whereIn('name', ['transaction-corrections-access', 'transaction-corrections-update'])));

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        $permissions = Permission::query()->where('guard_name', 'web')->whereIn('name', $this->permissions)->get();
        Role::query()->where('guard_name', 'web')->whereIn('name', ['cashier', 'finance', 'super-admin'])->get()
            ->each(fn (Role $role) => $role->revokePermissionTo($permissions));
        Permission::query()->where('guard_name', 'web')->whereIn('name', $this->permissions)->delete();
        Schema::dropIfExists('transaction_correction_requests');
        Schema::dropIfExists('cashier_favorite_products');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
