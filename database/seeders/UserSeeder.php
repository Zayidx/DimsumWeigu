<?php

namespace Database\Seeders;

use App\Models\Outlet;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $accounts = [
            [trim((string) env('SUPER_ADMIN_EMAIL', 'arya@gmail.com')), env('SUPER_ADMIN_NAME', 'Administrator'), 'super-admin', null],
            ['manager@gmail.com', 'Manager', 'manager', null],
            ['finance@gmail.com', 'Finance', 'finance', null],
            ['warehouse@gmail.com', 'Warehouse', 'warehouse', null],
            ['cashier@gmail.com', 'Kasir Cabang 1 - Galaxy BP', 'cashier', 'GAL-BP'],
            ['cashier2@gmail.com', 'Kasir Cabang 2 - Galaxy Hermina', 'cashier', 'GAL-HER'],
            ['cashier3@gmail.com', 'Kasir Cabang 3 - Pekayon Jaya', 'cashier', 'PEK-JAYA'],
            ['cashier4@gmail.com', 'Kasir Cabang 4 - Jalan Raya Pekayon', 'cashier', 'JL-RAYA-PEK'],
        ];
        foreach ($accounts as [$email, $name, $role, $code]) {
            if ($email === '') {
                $email = 'arya@gmail.com';
            }
            $configured = $role === 'super-admin' ? (string) env('SUPER_ADMIN_PASSWORD') : '';
            $user = User::firstOrCreate(['email' => $email], [
                'name' => $name, 'password' => $configured !== '' ? $configured : Str::random(64),
                'locale' => 'id', 'is_active' => true, 'must_change_password' => true,
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
        $this->command?->info('Akun sumber dibuat tanpa menyalin kredensial. Gunakan reset password atau konfigurasi administrator proyek baru.');
    }
}
