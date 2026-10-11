<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Ingredient;
use App\Models\Product;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\TestDataSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
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
        $stockMismatchCount = DB::table('ingredients')
            ->where('ingredients.code', 'like', 'TEST-%')
            ->leftJoinSub(
                DB::table('ingredient_warehouses')->selectRaw('ingredient_id, SUM(stock) as warehouse_stock')->groupBy('ingredient_id'),
                'balances',
                'balances.ingredient_id',
                '=',
                'ingredients.id',
            )
            ->whereRaw('ROUND(ingredients.stock, 3) != ROUND(COALESCE(balances.warehouse_stock, 0), 3)')
            ->count();
        $this->assertSame(0, $stockMismatchCount);
        $this->assertSame(4, DB::table('ingredient_warehouses')->where('ingredient_id', DB::table('ingredients')->where('code', 'TEST-BOX-KECIL')->value('id'))->where('stock', 100)->count());
        $this->assertFalse(Role::findByName('manager')->hasPermissionTo('products-create'));
        $this->assertFalse(Role::findByName('manager')->hasPermissionTo('cash-management-create'));
        $this->assertTrue(Role::findByName('cashier')->hasPermissionTo('pos-checkout'));
        $cashier = User::where('email', 'cashier2@gmail.com')->firstOrFail();
        $this->assertSame(['GAL-HER'], $cashier->outlets()->pluck('code')->all());
        $this->assertTrue(Hash::check('indrawan123', $cashier->password));
        $this->assertDatabaseHas('payment_settings', ['provider' => 'midtrans', 'is_enabled' => false]);
        $this->assertFalse(Schema::hasColumn('users', 'must_change_password'));
        $this->assertFalse(Schema::hasColumn('users', 'password_changed_at'));
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
        $ledgerCountBefore = DB::table('ingredient_inventory_ledgers')->whereIn('ingredient_id', DB::table('ingredients')->where('code', 'like', 'TEST-%')->select('id'))->count();
        $this->seed(DatabaseSeeder::class);
        $this->seed(UserSeeder::class);
        $this->seed(TestDataSeeder::class);
        $this->assertSame($ledgerCountBefore, DB::table('ingredient_inventory_ledgers')->whereIn('ingredient_id', DB::table('ingredients')->where('code', 'like', 'TEST-%')->select('id'))->count());
        $this->assertDatabaseCount('users', 8);
        $this->assertGreaterThan(100, DB::table('recipe_items')->whereIn('ingredient_id', DB::table('ingredients')->where('code', 'like', 'TEST-%')->select('id'))->count());
        $this->assertDatabaseHas('products', ['sku' => 'TEST-DOR', 'sell_price' => 4700]);
        $this->assertDatabaseHas('ingredients', ['id' => $ingredient->id, 'stock' => 999]);
        $this->assertSame(0, DB::table('recipe_items')->where('ingredient_id', $ingredient->id)->where('quantity', 2)->count());
        $this->assertTrue(Role::findByName('manager')->hasPermissionTo('products-create'));
        $this->assertTrue(hash_equals($beforeCredential, hash('sha256', $cashier->fresh()->password)));
    }

    public function test_test_data_seeder_avoids_legacy_barcode_collisions_and_can_be_repeated(): void
    {
        $this->seed(DatabaseSeeder::class);
        $category = Category::query()->where('name', 'Dimsum')->firstOrFail();
        $unit = Unit::query()->where('code', 'PCS')->firstOrFail();
        $legacy = Product::query()->create([
            'sku' => 'TEST-BMX', 'title' => 'Paket Besar Dimsum Mix (arsip uji)',
            'category_id' => $category->id, 'unit_id' => $unit->id,
            'barcode' => 'TESTDW000011', 'buy_price' => 0, 'sell_price' => 0, 'stock' => 0,
        ]);

        $this->seed(TestDataSeeder::class);

        $plain = Product::query()->where('sku', 'TEST-DPL')->firstOrFail();
        $this->assertNotSame($legacy->barcode, $plain->barcode);
        $this->assertDatabaseHas('products', ['id' => $legacy->id, 'sku' => 'TEST-BMX', 'barcode' => 'TESTDW000011']);
        $this->assertDatabaseHas('products', ['sku' => 'TEST-DPL', 'barcode' => $plain->barcode]);

        $this->seed(TestDataSeeder::class);

        $this->assertSame($plain->barcode, $plain->fresh()->barcode);
        $this->assertDatabaseHas('products', ['id' => $legacy->id, 'barcode' => 'TESTDW000011']);
    }

    public function test_simulated_data_cannot_run_in_production(): void
    {
        $this->app->instance('env', 'production');
        $this->expectException(\RuntimeException::class);
        (new TestDataSeeder)->run();
    }

    public function test_seeded_staff_keep_configured_password_without_forcing_a_first_login_change(): void
    {
        $this->seed(DatabaseSeeder::class);
        $manager = User::where('email', 'manager@gmail.com')->firstOrFail();
        $this->assertTrue(Hash::check('indrawan123', $manager->password));

        $manager->forceFill(['password' => 'PersonalManagerPassword789!'])->save();
        $this->seed(UserSeeder::class);
        $this->assertTrue(Hash::check('PersonalManagerPassword789!', $manager->fresh()->password));
    }

    public function test_staff_can_log_in_without_a_forced_password_change_redirect(): void
    {
        $user = User::create([
            'name' => 'Staff Login', 'email' => 'staff-login@example.test',
            'password' => 'Starter-password-123!', 'is_active' => true,
        ]);
        $nonce = str_repeat('a', 48);
        $this->withSession([
            'auth.login.started_at' => now()->subSeconds(3)->timestamp,
            'auth.login.nonce' => $nonce,
        ])->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'Starter-password-123!',
            'form_nonce' => $nonce,
        ])->assertRedirect(route('dashboard'));

        $this->get('/profile/password/force')->assertNotFound();
    }
}
