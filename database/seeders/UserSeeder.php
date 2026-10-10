<?php

namespace Database\Seeders;

use App\Models\Outlet;
use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        if (trim((string) config('seed_accounts.passwords.super-admin', '')) === '') {
            throw new RuntimeException('Isi SUPER_ADMIN_PASSWORD sebelum menjalankan UserSeeder.');
        }

        $accounts = [
            [trim((string) config('seed_accounts.admin_email')), config('seed_accounts.admin_name'), 'super-admin', null, 'super-admin'],
            ['manager@gmail.com', 'Manager', 'manager', null, 'manager'],
            ['finance@gmail.com', 'Finance', 'finance', null, 'finance'],
            ['warehouse@gmail.com', 'Warehouse', 'warehouse', null, 'warehouse'],
            ['cashier@gmail.com', 'Kasir Cabang 1 - Galaxy BP', 'cashier', 'GAL-BP', 'cashier1'],
            ['cashier2@gmail.com', 'Kasir Cabang 2 - Galaxy Hermina', 'cashier', 'GAL-HER', 'cashier2'],
            ['cashier3@gmail.com', 'Kasir Cabang 3 - Pekayon Jaya', 'cashier', 'PEK-JAYA', 'cashier3'],
            ['cashier4@gmail.com', 'Kasir Cabang 4 - Jalan Raya Pekayon', 'cashier', 'JL-RAYA-PEK', 'cashier4'],
        ];
        foreach ($accounts as [$email, $name, $role, $code, $passwordKey]) {
            if ($email === '') {
                $email = 'arya@gmail.com';
            }
            $configured = (string) config('seed_accounts.passwords.'.$passwordKey, '');
            $user = User::firstOrCreate(['email' => $email], [
                'name' => $name, 'password' => $configured,
                'locale' => 'id', 'is_active' => true,
            ]);
            if (! $user->wasRecentlyCreated) {
                continue;
            }
            $user->assignRole($role);
            $outlets = Outlet::whereIn('code', ['GAL-BP', 'GAL-HER', 'PEK-JAYA', 'JL-RAYA-PEK'])->when($code, fn ($query) => $query->where('code', $code))->orderBy('id')->get();
            foreach ($outlets as $index => $outlet) {
                $user->outlets()->attach($outlet->id, ['is_default' => $index === 0]);
            }
        }
        $this->command?->info('Akun staff disiapkan. Kata sandi akun yang sudah ada tidak diubah.');
    }
}
