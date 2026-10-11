<?php

namespace Tests\Feature;

use App\Models\Ingredient;
use App\Models\Product;
use App\Models\RecipeVersion;
use Database\Seeders\DimsumWeiguSeeder;
use Database\Seeders\HppWorkbookSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HppWorkbookSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_import_is_idempotent_and_does_not_replace_edited_cost_or_stock(): void
    {
        $this->seed(DimsumWeiguSeeder::class);
        $this->seed(HppWorkbookSeeder::class);

        $ingredient = Ingredient::query()->where('code', 'HPP-001')->firstOrFail();
        $this->assertSame('57.00000000', $ingredient->buy_price);
        $this->assertSame(0.0, (float) $ingredient->stock);
        $ingredient->update(['buy_price' => '61.25000000', 'stock' => 12]);
        $count = Ingredient::query()->where('code', 'like', 'HPP-%')->count();

        $this->seed(HppWorkbookSeeder::class);

        $this->assertDatabaseHas('ingredients', ['id' => $ingredient->id, 'buy_price' => 61.25, 'stock' => 12]);
        $this->assertSame($count, Ingredient::query()->where('code', 'like', 'HPP-%')->count());
        $this->assertDatabaseHas('products', ['sku' => 'HPP-MENU-001', 'sell_price' => 16000, 'stock' => 0]);
        $this->assertDatabaseHas('products', ['sku' => 'HPP-MENU-018', 'title' => 'Chikuro Garlic']);
        $this->assertDatabaseHas('products', ['sku' => 'HPP-MENU-018-02', 'title' => 'Chikuro Mentai']);
        $menuId = Product::query()->where('sku', 'HPP-MENU-001')->value('id');
        $this->assertDatabaseHas('recipe_versions', ['product_id' => $menuId, 'is_active' => true, 'yield_quantity' => 1]);
        $recipe = RecipeVersion::query()->with('productItems.product')->where('product_id', $menuId)->where('is_active', true)->firstOrFail();
        $this->assertGreaterThan(0, $recipe->productItems->filter(fn ($line) => $line->product?->is_semi_finished)->count());
    }

    public function test_importer_runs_in_production_environment_for_explicit_seeding(): void
    {
        $this->seed(DimsumWeiguSeeder::class);
        $this->app->instance('env', 'production');
        (new HppWorkbookSeeder)->run();

        $this->assertDatabaseHas('ingredients', ['code' => 'HPP-001']);
    }
}
