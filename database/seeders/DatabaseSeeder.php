<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $this->call([ApplicationPermissionSeeder::class, PermissionSeeder::class, RoleSeeder::class, DimsumWeiguSeeder::class]);
            // These seeders contain repository business data and use idempotent inserts.
            // Keep them available in every environment so a normal `db:seed` provisions a usable system.
            $this->call([HppWorkbookSeeder::class, UserSeeder::class]);
        });
    }
}
