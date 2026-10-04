<?php

namespace Database\Seeders;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->command?->info('Running full demo data seeder...');

        $this->call([
            DemoOutletSeeder::class,
            UserSeeder::class,
            SampleDataSeeder::class,
            OperationalCoreSeeder::class,
            FeatureCoverageSeeder::class,
            FeatureDemoSeeder::class,
        ]);

        User::whereIn('email', ['arya@gmail.com', 'manager@gmail.com', 'cashier@gmail.com'])
            ->update(['password' => Hash::make('password')]);

        Setting::set('app_setup_completed', true);

        $this->command?->info('Demo data seeder completed.');
        $this->command?->info('Admin   : arya@gmail.com / password (super-admin, semua outlet)');
        $this->command?->info('Manager : manager@gmail.com / password (outlet MAL + TKB)');
        $this->command?->info('Kasir   : cashier@gmail.com / password (outlet MAL)');
    }
}
