<?php

namespace Database\Seeders;

use App\Models\Outlet;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production') && (! env('SUPER_ADMIN_EMAIL') || ! env('SUPER_ADMIN_PASSWORD'))) {
            throw new RuntimeException('Set SUPER_ADMIN_EMAIL and SUPER_ADMIN_PASSWORD before seeding users in production.');
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $branches = Outlet::where('is_sales_enabled', true)->orderBy('id')->get();
        $outlets = Outlet::where('code', 'PUSAT')->get()->merge($branches);
        $financeOutlets = $branches->merge(Outlet::where('code', 'PUSAT')->get());
        $demoManagerOutlets = Outlet::whereIn('code', ['MAL', 'TKB'])->get();
        $demoCashierOutlet = Outlet::where('code', 'MAL')->get();

        $accounts = [
            [
                'name' => 'Administrator',
                'email' => env('SUPER_ADMIN_EMAIL', 'arya@gmail.com'),
                'password' => env('SUPER_ADMIN_PASSWORD', 'password'),
                'role' => 'super-admin',
                'outlets' => $outlets,
            ],
            [
                'name' => 'Manager',
                'email' => 'manager@gmail.com',
                'password' => 'manager123',
                'role' => 'manager',
                'outlets' => $demoManagerOutlets->count() === 2 ? $demoManagerOutlets : $branches,
            ],
            [
                'name' => 'Finance',
                'email' => 'finance@gmail.com',
                'password' => 'finance123',
                'role' => 'finance',
                'outlets' => $financeOutlets,
            ],
            [
                'name' => 'Cashier',
                'email' => 'cashier@gmail.com',
                'password' => 'cashier123',
                'role' => 'cashier',
                'outlets' => $demoCashierOutlet->isNotEmpty() ? $demoCashierOutlet : $branches->take(1),
            ],
            [
                'name' => 'Warehouse',
                'email' => 'warehouse@gmail.com',
                'password' => 'warehouse123',
                'role' => 'warehouse',
                'outlets' => $outlets,
            ],
        ];

        foreach ($accounts as $account) {
            $user = User::updateOrCreate(
                ['email' => $account['email']],
                ['name' => $account['name'], 'password' => Hash::make($account['password']), 'locale' => 'id'],
            );

            $user->markEmailAsVerified();
            $user->syncRoles([$account['role']]);
            $user->syncPermissions($account['role'] === 'super-admin' ? Permission::all() : []);

            $user->outlets()->sync($account['outlets']->mapWithKeys(fn (Outlet $outlet, int $index) => [
                $outlet->id => ['is_default' => $index === 0],
            ])->all());
        }

        if (Outlet::where('code', 'GAL-BP')->exists()) {
            $this->call(OutletCashierSeeder::class);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
