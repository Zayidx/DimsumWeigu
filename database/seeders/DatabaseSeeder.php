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
            if (app()->environment('local')) {
                $this->call([UserSeeder::class, TestDataSeeder::class]);
            }
        });
    }
}
