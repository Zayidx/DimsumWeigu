<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Keep this list limited to real source/business data and required system setup.
        // TestDataSeeder and OperationalActivitySeeder are explicit opt-in seeders.
        DB::transaction(function () {
            $this->call([
                ApplicationPermissionSeeder::class,
                PermissionSeeder::class,
                RoleSeeder::class,
                DimsumWeiguSeeder::class,
                HppWorkbookSeeder::class,
                UserSeeder::class,
            ]);
        });
    }
}
