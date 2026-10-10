<?php

namespace App\Livewire\Admin;

use App\Models\Outlet;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\WithPagination;
use Spatie\Permission\Models\Role;
use Throwable;

class UsersIndex extends Component
{
    use WithPagination;

    public string $search = '';

    public bool $showForm = false;

    public ?int $userId = null;

    public string $name = '';

    public string $email = '';

    public string $phone = '';

    public array $roleIds = [];

    public array $outletIds = [];

    public ?int $defaultOutletId = null;

    public bool $isActive = true;

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function create(): void
    {
        $this->permit('users-create');
        $this->clearForm();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $this->permit('users-edit');
        $user = User::query()->with(['roles:id,name', 'outlets:id'])->findOrFail($id);
        $this->protectTarget($user);
        $this->userId = $user->id;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->phone = $user->phone ?? '';
        $this->roleIds = $user->roles->pluck('id')->all();
        $this->outletIds = $user->outlets->pluck('id')->all();
        $this->defaultOutletId = $user->outlets->firstWhere('pivot.is_default', true)?->id;
        $this->isActive = $user->is_active;
        $this->showForm = true;
    }

    public function closeForm(): void
    {
        $this->clearForm();
    }

    public function save(AuditLogger $audit): void
    {
        $this->requireRecentPasswordConfirmation();
        $this->permit($this->userId ? 'users-edit' : 'users-create');
        $isNewUser = ! $this->userId;
        $data = $this->validate([
            'name' => ['required', 'string', 'max:160'], 'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->userId)],
            'phone' => ['nullable', 'string', 'max:32'],
            'roleIds' => ['required', 'array', 'min:1'], 'roleIds.*' => ['integer', 'exists:roles,id', 'distinct'],
            'outletIds' => ['required', 'array', 'min:1'], 'outletIds.*' => ['integer', 'exists:outlets,id', 'distinct'],
            'defaultOutletId' => ['required', 'integer', 'exists:outlets,id'], 'isActive' => ['boolean'],
        ]);
        if (! in_array((int) $data['defaultOutletId'], array_map('intval', $data['outletIds']), true)) {
            throw ValidationException::withMessages(['defaultOutletId' => __('Outlet default harus termasuk dalam assignment user.')]);
        }
        $roles = Role::query()->whereIn('id', $data['roleIds'])->where('guard_name', 'web')->where('name', '!=', 'customer')->get();
        if ($roles->count() !== count($data['roleIds'])) {
            throw ValidationException::withMessages(['roleIds' => __('Role user tidak valid.')]);
        }
        if ($roles->contains('name', 'super-admin') && ! auth()->user()->hasRole('super-admin')) {
            abort(403);
        }
        foreach ($roles as $role) {
            if (! auth()->user()->hasRole('super-admin') && $role->permissions()->whereNotIn('name', auth()->user()->getAllPermissions()->pluck('name'))->exists()) {
                abort(403);
            }
        }
        $outlets = Outlet::query()->whereIn('id', $data['outletIds'])->where('is_active', true)->get();
        if ($outlets->count() !== count($data['outletIds'])) {
            throw ValidationException::withMessages(['outletIds' => __('Outlet user tidak valid atau nonaktif.')]);
        }

        DB::transaction(function () use ($data, $roles, $audit) {
            $user = $this->userId ? User::query()->lockForUpdate()->findOrFail($this->userId) : new User;
            if ($user->exists) {
                $this->protectTarget($user);
            }
            $before = $this->userId ? ['name' => $user->name, 'email' => $user->email, 'is_active' => $user->is_active, 'roles' => $user->getRoleNames()->all(), 'outlets' => $user->outlets()->pluck('outlets.id')->all()] : null;
            if ($user->id === auth()->id() && $user->exists && $user->getRoleNames()->sort()->values()->all() !== $roles->pluck('name')->sort()->values()->all()) {
                throw ValidationException::withMessages(['roleIds' => __('Role akun Anda sendiri tidak dapat diubah dari halaman ini.')]);
            }
            $keepsSuper = $roles->contains('name', 'super-admin') && $data['isActive'];
            if ($user->id === auth()->id() && ! $data['isActive']) {
                throw ValidationException::withMessages(['isActive' => __('Akun Anda sendiri tidak dapat dinonaktifkan.')]);
            }
            if ($user->exists && $user->is_active && $user->hasRole('super-admin') && ! $keepsSuper && User::role('super-admin')->where('is_active', true)->count() <= 1) {
                throw ValidationException::withMessages(['roleIds' => __('Super-admin aktif terakhir tidak dapat dinonaktifkan atau diturunkan.')]);
            }
            $user->fill(['name' => trim($data['name']), 'email' => strtolower(trim($data['email'])), 'phone' => $data['phone'] ?: null, 'is_active' => $data['isActive']]);
            if (! $user->exists) {
                $user->password = Hash::make(Str::password(64));
            }
            $user->save();
            $user->syncRoles($roles);
            if ($before && (collect($before['roles'])->sort()->values()->all() !== $roles->pluck('name')->sort()->values()->all() || ! $data['isActive'])) {
                $user->tokens()->delete();
            }
            $user->outlets()->sync(collect($data['outletIds'])->mapWithKeys(fn ($id) => [(int) $id => ['is_default' => (int) $id === (int) $data['defaultOutletId']]])->all());
            $after = ['name' => $user->name, 'email' => $user->email, 'is_active' => $user->is_active, 'roles' => $roles->pluck('name')->all(), 'outlets' => array_map('intval', $data['outletIds'])];
            $audit->record($before ? 'user.updated' : 'user.created', $user, $before, $after);
        }, attempts: 3);
        $resetLinkSent = false;
        if ($isNewUser) {
            try {
                $resetLinkSent = Password::sendResetLink(['email' => strtolower(trim($data['email']))]) === Password::ResetLinkSent;
            } catch (Throwable $exception) {
                report($exception);
            }
        }
        $this->clearForm();
        $this->dispatch('toast', type: $isNewUser && ! $resetLinkSent ? 'warning' : 'success', message: $isNewUser ? ($resetLinkSent ? __('Akun dibuat dan instruksi penetapan kata sandi dikirim.') : __('Akun dibuat. Email reset belum dapat dikirim; gunakan aksi kirim ulang setelah konfigurasi email tersedia.')) : __('Data pengguna berhasil disimpan.'));
    }

    public function sendResetLink(int $id): void
    {
        $this->requireRecentPasswordConfirmation();
        $this->permit('users-edit');
        $user = User::query()->whereKey($id)->where('is_active', true)->firstOrFail();
        $this->protectTarget($user);
        abort_unless(cache()->add('admin-password-reset:'.$user->id, true, now()->addMinute()), 429);
        Password::sendResetLink(['email' => $user->email]);
        $this->dispatch('toast', type: 'success', message: __('Instruksi reset dikirim jika email aktif dikonfigurasi.'));
    }

    public function deactivate(int $id, AuditLogger $audit): void
    {
        $this->requireRecentPasswordConfirmation();
        $this->permit('users-delete');
        $user = User::query()->with('roles')->findOrFail($id);
        $this->protectTarget($user);
        if ($user->id === auth()->id()) {
            throw ValidationException::withMessages(['user' => __('Akun Anda sendiri tidak dapat dinonaktifkan.')]);
        }
        if ($user->hasRole('super-admin') && User::role('super-admin')->where('is_active', true)->count() <= 1) {
            throw ValidationException::withMessages(['user' => __('Super-admin aktif terakhir tidak dapat dinonaktifkan.')]);
        }
        $user->forceFill(['is_active' => false])->save();
        $user->tokens()->delete();
        $audit->record('user.deactivated', $user, ['is_active' => true], ['is_active' => false]);
    }

    public function render()
    {
        $this->permit('users-access');

        return view('livewire.admin.users-index', [
            'users' => User::query()->with(['roles:id,name', 'outlets:id,name'])->when($this->search !== '', fn (Builder $query) => $query->where(fn (Builder $sub) => $sub->where('name', 'like', '%'.$this->search.'%')->orWhere('email', 'like', '%'.$this->search.'%')->orWhere('phone', 'like', '%'.$this->search.'%')))->orderBy('name')->paginate(20),
            'roles' => Role::query()->where('guard_name', 'web')->where('name', '!=', 'customer')->orderBy('name')->get(['id', 'name']),
            'outlets' => Outlet::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'code']),
        ])->layout('layouts.dashboard', ['title' => __('Pengguna')]);
    }

    private function clearForm(): void
    {
        $this->showForm = false;
        $this->userId = null;
        $this->name = $this->email = $this->phone = '';
        $this->roleIds = [];
        $this->outletIds = [];
        $this->defaultOutletId = null;
        $this->isActive = true;
        $this->resetValidation();
    }

    private function protectTarget(User $target): void
    {
        $actor = auth()->user();
        if ($actor?->hasRole('super-admin')) {
            return;
        }
        abort_if($target->hasRole('super-admin'), 403);
        abort_if($target->getAllPermissions()->pluck('name')->diff($actor->getAllPermissions()->pluck('name'))->isNotEmpty(), 403);
    }

    private function requireRecentPasswordConfirmation(): void
    {
        abort_if(now()->timestamp - (int) session('auth.password_confirmed_at', 0) > (int) config('auth.password_timeout', 10800), 423, __('Konfirmasi kata sandi diperlukan. Muat ulang halaman untuk mengonfirmasi.'));
    }

    private function permit(string $permission): void
    {
        abort_unless(auth()->user()?->can($permission), 403);
    }
}
