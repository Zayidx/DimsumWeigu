<?php

namespace Tests\Feature;

use App\Models\Ingredient;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\TestDataSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SourceSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_setup_and_local_data_preserve_source_values_and_references(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(UserSeeder::class);
        $this->assertDatabaseCount('products', 0); // Testing does not implicitly load simulations.
        $this->seed(TestDataSeeder::class);
        foreach (['products' => 13, 'product_units' => 28, 'ingredients' => 13, 'recipe_versions' => 32, 'recipe_items' => 79,
            'outlets' => 4, 'warehouses' => 5, 'users' => 8, 'ingredient_warehouses' => 13, 'ingredient_inventory_ledgers' => 13] as $table => $count) {
            $this->assertDatabaseCount($table, $count);
        }
        foreach (['DOR' => 4500, 'DME' => 5500, 'DCH' => 6500, 'DMX' => 6000, 'LOR' => 9000, 'LME' => 10000,
            'CGA' => 26000, 'CLA' => 26000, 'CME' => 26000, 'CCH' => 28000, 'BMX' => 90000, 'BME' => 85000, 'BCH' => 100000] as $sku => $price) {
            $this->assertDatabaseHas('products', ['sku' => 'TEST-'.$sku, 'sell_price' => $price, 'stock' => 0]);
        }
        $this->assertDatabaseHas('warehouses', ['code' => 'PUSAT', 'outlet_id' => null, 'type' => 'central', 'is_sales_enabled' => false]);
        $this->assertSame(4, DB::table('warehouses')->where('type', 'branch')->where('is_sales_enabled', true)->count());
        $this->assertDatabaseHas('ingredients', ['code' => 'TEST-CHILI-OIL', 'buy_price' => 0.18, 'stock' => 5000]);
        $chili = Ingredient::where('code', 'TEST-CHILI-OIL')->firstOrFail();
        $this->assertSame(19, DB::table('recipe_items')->where('ingredient_id', $chili->id)->where('quantity', 50)->count());
        $this->assertSame(13, DB::table('ingredient_warehouses')->join('ingredients', 'ingredients.id', '=', 'ingredient_warehouses.ingredient_id')->whereColumn('ingredient_warehouses.stock', 'ingredients.stock')->count());
        $this->assertFalse(Role::findByName('manager')->hasPermissionTo('products-create'));
        $this->assertFalse(Role::findByName('manager')->hasPermissionTo('cash-management-create'));
        $this->assertTrue(Role::findByName('cashier')->hasPermissionTo('pos-checkout'));
        $cashier = User::where('email', 'cashier2@gmail.com')->firstOrFail();
        $this->assertSame(['GAL-HER'], $cashier->outlets()->pluck('code')->all());
        $this->assertTrue($cashier->must_change_password);
        $this->assertDatabaseHas('payment_settings', ['provider' => 'midtrans', 'is_enabled' => false]);
    }

    public function test_reseed_preserves_custom_prices_recipes_roles_credentials_and_stock(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(UserSeeder::class);
        $this->seed(TestDataSeeder::class);
        Product::where('sku', 'TEST-DOR')->update(['sell_price' => 4700]);
        $ingredient = Ingredient::where('code', 'TEST-SUMPIT')->firstOrFail();
        $ingredient->update(['stock' => 999]);
        DB::table('ingredient_warehouses')->where('ingredient_id', $ingredient->id)->update(['stock' => 999]);
        DB::table('recipe_items')->where('ingredient_id', $ingredient->id)->update(['quantity' => 2]);
        $role = Role::findByName('manager');
        $role->givePermissionTo('products-create');
        $cashier = User::where('email', 'cashier@gmail.com')->firstOrFail();
        $beforeCredential = hash('sha256', $cashier->password);
        $this->seed(DatabaseSeeder::class);
        $this->seed(UserSeeder::class);
        $this->seed(TestDataSeeder::class);
        $this->assertDatabaseCount('ingredient_inventory_ledgers', 13);
        $this->assertDatabaseCount('users', 8);
        $this->assertDatabaseCount('recipe_items', 79);
        $this->assertDatabaseHas('products', ['sku' => 'TEST-DOR', 'sell_price' => 4700]);
        $this->assertDatabaseHas('ingredients', ['id' => $ingredient->id, 'stock' => 999]);
        $this->assertSame(19, DB::table('recipe_items')->where('ingredient_id', $ingredient->id)->where('quantity', 2)->count());
        $this->assertTrue(Role::findByName('manager')->hasPermissionTo('products-create'));
        $this->assertTrue(hash_equals($beforeCredential, hash('sha256', $cashier->fresh()->password)));
    }

    public function test_simulated_data_cannot_run_in_production(): void
    {
        $this->app->instance('env', 'production');
        $this->expectException(\RuntimeException::class);
        (new TestDataSeeder)->run();
    }
}
