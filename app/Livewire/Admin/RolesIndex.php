<?php

namespace App\Livewire\Admin;

use App\Support\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolesIndex extends Component
{
    public bool $showForm = false;
    public ?int $roleId = null;
    public string $name = '';
    public array $permissionIds = [];

    public function create(): void
    {
        $this->authorizeAdmin();
        $this->requireRecentPasswordConfirmation();
        $this->resetForm();
        $this->showForm = true;
    }

    public function closeForm(): void { $this->resetForm(); }

    public function edit(int $id): void
    {
        $this->authorizeAdmin();
        $this->requireRecentPasswordConfirmation();
        $role = Role::query()->with('permissions:id')->where('guard_name', 'web')->findOrFail($id);
        abort_if($role->name === 'super-admin', 403, __('Permission super-admin dilindungi.'));
        $this->roleId = $role->id;
        $this->name = $role->name;
        $this->permissionIds = $role->permissions->pluck('id')->all();
        $this->showForm = true;
    }

    public function save(AuditLogger $audit): void
    {
        $this->authorizeAdmin();
        $this->requireRecentPasswordConfirmation();
        $data = $this->validate([
            'name' => ['required', 'string', 'min:3', 'max:80', 'alpha_dash', Rule::unique('roles', 'name')->where('guard_name', 'web')->ignore($this->roleId)],
            'permissionIds' => ['array'],
            'permissionIds.*' => ['integer', 'distinct', 'exists:permissions,id'],
        ]);
        $permissions = Permission::query()->where('guard_name', 'web')->whereIn('id', $data['permissionIds'])->orderBy('name')->get();
        if ($permissions->count() !== count($data['permissionIds'])) {
            throw ValidationException::withMessages(['permissionIds' => __('Permission tidak valid.')]);
        }
        DB::transaction(function () use ($data, $permissions, $audit): void {
            $role = $this->roleId ? Role::query()->lockForUpdate()->findOrFail($this->roleId) : new Role(['guard_name' => 'web']);
            abort_if($role->exists && $role->name === 'super-admin', 403, __('Permission super-admin dilindungi.'));
            $before = $role->exists ? ['name' => $role->name, 'permissions' => $role->permissions()->pluck('name')->all()] : null;
            if ($role->exists && in_array($role->name, ['super-admin', 'customer'], true) && $role->name !== $data['name']) {
                throw ValidationException::withMessages(['name' => __('Nama role sistem tidak dapat diubah.')]);
            }
            $role->name = $data['name'];
            $role->save();
            $role->syncPermissions($permissions);
            $role->users()->get()->each(fn ($user) => $user->tokens()->delete());
            $audit->record($before ? 'role.updated' : 'role.created', $role, $before, ['name' => $role->name, 'permissions' => $permissions->pluck('name')->all()]);
        }, attempts: 3);
        $this->resetForm();
        $this->dispatch('toast', type: 'success', message: __('Role dan permission berhasil disimpan.'));
    }

    public function delete(int $id, AuditLogger $audit): void
    {
        $this->authorizeAdmin();
        $this->requireRecentPasswordConfirmation();
        $role = Role::query()->withCount('users')->where('guard_name', 'web')->findOrFail($id);
        if (in_array($role->name, ['super-admin', 'manager', 'finance', 'cashier', 'warehouse', 'customer'], true) || $role->users_count > 0) {
            throw ValidationException::withMessages(['role' => __('Role sistem atau role yang masih ditugaskan tidak dapat dihapus.')]);
        }
        DB::transaction(function () use ($role, $audit): void {
            $snapshot = ['name' => $role->name, 'permissions' => $role->permissions()->pluck('name')->all()];
            $role->delete();
            $audit->record('role.deleted', $role, $snapshot, null);
        });
        $this->dispatch('toast', type: 'success', message: __('Role dihapus.'));
    }

    public function render()
    {
        $this->authorizeAdmin();
        return view('livewire.admin.roles-index', [
            'roles' => Role::query()->withCount('users')->with('permissions:id,name')->where('guard_name', 'web')->orderBy('name')->get(),
            'permissions' => Permission::query()->where('guard_name', 'web')->orderBy('name')->get(['id', 'name']),
        ])->layout('layouts.dashboard', ['title' => __('Role dan permission')]);
    }

    private function authorizeAdmin(): void { abort_unless(auth()->user()?->hasRole('super-admin'), 403); }
    private function requireRecentPasswordConfirmation(): void { abort_if(now()->timestamp - (int) session('auth.password_confirmed_at', 0) > (int) config('auth.password_timeout', 10800), 423, __('Konfirmasi kata sandi diperlukan. Muat ulang halaman untuk mengonfirmasi.')); }
    public function resetForm(): void { $this->showForm = false; $this->roleId = null; $this->name = ''; $this->permissionIds = []; $this->resetValidation(); }
}
