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
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SourceSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_setup_and_local_data_preserve_source_values_and_references(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(UserSeeder::class);
        $this->assertDatabaseHas('products', ['sku' => 'HPP-MENU-001']);
        $this->assertDatabaseHas('ingredients', ['code' => 'HPP-001']);
        $this->assertSame(1, User::role('super-admin')->count());
        $this->assertDatabaseMissing('products', ['sku' => 'TEST-DOR']); // Synthetic simulation data stays opt-in.
        $this->seed(TestDataSeeder::class);
        $this->assertSame(16, DB::table('products')->where('sku', 'like', 'TEST-%')->count());
        $this->assertGreaterThan(40, DB::table('ingredients')->where('code', 'like', 'TEST-%')->count());
        $testProductIds = DB::table('products')->where('sku', 'like', 'TEST-%')->select('id');
        $testIngredientIds = DB::table('ingredients')->where('code', 'like', 'TEST-%')->select('id');
        $this->assertGreaterThan(30, DB::table('product_units')->whereIn('product_id', $testProductIds)->count());
        $this->assertGreaterThan(20, DB::table('recipe_versions')->whereIn('product_id', $testProductIds)->count());
        $this->assertGreaterThan(100, DB::table('recipe_items')->whereIn('ingredient_id', $testIngredientIds)->count());
        $this->assertSame(4, DB::table('outlets')->count());
        $this->assertSame(5, DB::table('warehouses')->count());
        $this->assertSame(8, DB::table('users')->count());
        $ingredientCount = DB::table('ingredients')->where('code', 'like', 'TEST-%')->count();
        $openingLedgerCount = DB::table('ingredient_inventory_ledgers')->whereIn('ingredient_id', $testIngredientIds)->count();
        $this->assertGreaterThan(40, $openingLedgerCount);
        $this->assertSame($openingLedgerCount, DB::table('ingredient_warehouses')->whereIn('ingredient_id', $testIngredientIds)->count());
        foreach (['DOR' => 4000, 'DME' => 5000, 'DCH' => 5500, 'DMX' => 0, 'LOR' => 0, 'LME' => 0,
            'CGA' => 26000, 'CLA' => 26000, 'CME' => 26000, 'CCH' => 28000] as $sku => $price) {
            $this->assertDatabaseHas('products', ['sku' => 'TEST-'.$sku, 'sell_price' => $price, 'stock' => 0]);
        }
        $this->assertDatabaseHas('warehouses', ['code' => 'PUSAT', 'outlet_id' => null, 'type' => 'central', 'is_sales_enabled' => false]);
        $this->assertSame(4, DB::table('warehouses')->where('type', 'branch')->where('is_sales_enabled', true)->count());
        $this->assertDatabaseHas('ingredients', ['code' => 'TEST-AYAM-FILLET', 'unit_id' => DB::table('units')->where('code', 'GRAM')->value('id'), 'buy_price' => 57]);
        $smallBoxCost = (float) Ingredient::query()->where('code', 'TEST-BOX-KECIL')->value('buy_price');
        $this->assertEqualsWithDelta(150000 / 379, $smallBoxCost, 0.001);
        $this->assertDatabaseHas('products', ['sku' => 'TEST-CGA', 'allow_single_unit_sale' => true]);
        $this->assertDatabaseHas('products', ['sku' => 'TEST-DPL', 'is_semi_finished' => true, 'is_sellable' => false]);
        $this->assertDatabaseMissing('ingredients', ['code' => 'TEST-FROZEN-DIMSUM']);
        $this->assertSame($openingLedgerCount, DB::table('ingredient_warehouses')->join('ingredients', 'ingredients.id', '=', 'ingredient_warehouses.ingredient_id')->where('ingredients.code', 'like', 'TEST-%')->whereColumn('ingredient_warehouses.stock', 'ingredients.stock')->count());
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
        $ingredientCount = DB::table('ingredients')->where('code', 'like', 'TEST-%')->count();
        $this->assertSame($ingredientCount, DB::table('ingredient_inventory_ledgers')->whereIn('ingredient_id', DB::table('ingredients')->where('code', 'like', 'TEST-%')->select('id'))->count());
        $this->assertDatabaseCount('users', 8);
        $this->assertGreaterThan(100, DB::table('recipe_items')->whereIn('ingredient_id', DB::table('ingredients')->where('code', 'like', 'TEST-%')->select('id'))->count());
        $this->assertDatabaseHas('products', ['sku' => 'TEST-DOR', 'sell_price' => 4700]);
        $this->assertDatabaseHas('ingredients', ['id' => $ingredient->id, 'stock' => 999]);
        $this->assertSame(0, DB::table('recipe_items')->where('ingredient_id', $ingredient->id)->where('quantity', 2)->count());
        $this->assertTrue(Role::findByName('manager')->hasPermissionTo('products-create'));
        $this->assertTrue(hash_equals($beforeCredential, hash('sha256', $cashier->fresh()->password)));
    }

    public function test_simulated_data_cannot_run_in_production(): void
    {
        $this->app->instance('env', 'production');
        $this->expectException(\RuntimeException::class);
        (new TestDataSeeder)->run();
    }

    public function test_seeded_staff_receive_initial_password_without_overwriting_changed_credentials(): void
    {
        $this->seed(DatabaseSeeder::class);
        $manager = User::where('email', 'manager@gmail.com')->firstOrFail();

        $manager->forceFill(['password' => 'UnknownOldSeederPassword456!', 'must_change_password' => true, 'password_changed_at' => null])->save();
        $this->seed(UserSeeder::class);
        $this->assertTrue(Hash::check('indrawan123', $manager->fresh()->password));

        $manager->forceFill(['password' => 'PersonalManagerPassword789!', 'password_changed_at' => now(), 'must_change_password' => false])->save();
        $this->seed(UserSeeder::class);
        $this->assertTrue(Hash::check('PersonalManagerPassword789!', $manager->fresh()->password));
    }
}
