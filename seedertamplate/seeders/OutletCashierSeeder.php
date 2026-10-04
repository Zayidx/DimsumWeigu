<?php

namespace Database\Seeders;

use App\Models\Outlet;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Spatie\Permission\PermissionRegistrar;

class OutletCashierSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $branches = Outlet::whereIn('code', ['GAL-BP', 'GAL-HER', 'PEK-JAYA', 'JL-RAYA-PEK'])
            ->get()
            ->keyBy('code');

        $accounts = [
            ['code' => 'GAL-BP', 'name' => 'Kasir Cabang 1 - Galaxy BP', 'email' => 'cashier@gmail.com'],
            ['code' => 'GAL-HER', 'name' => 'Kasir Cabang 2 - Galaxy Hermina', 'email' => 'cashier2@gmail.com'],
            ['code' => 'PEK-JAYA', 'name' => 'Kasir Cabang 3 - Pekayon Jaya', 'email' => 'cashier3@gmail.com'],
            ['code' => 'JL-RAYA-PEK', 'name' => 'Kasir Cabang 4 - Jalan Raya Pekayon', 'email' => 'cashier4@gmail.com'],
        ];

        foreach ($accounts as $account) {
            $outlet = $branches->get($account['code']);
            if (! $outlet) {
                throw new RuntimeException("Outlet {$account['code']} must exist before outlet cashier accounts are seeded.");
            }

            $user = User::firstOrNew(['email' => $account['email']]);
            $isNew = ! $user->exists;
            $user->name = $account['name'];
            $user->locale = 'id';
            if ($isNew) {
                $user->password = Hash::make('cashier123');
            }
            $user->save();

            $user->markEmailAsVerified();
            $user->syncRoles(['cashier']);
            $user->syncPermissions([]);
            $user->outlets()->sync([$outlet->id => ['is_default' => true]]);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
