<?php

namespace Tests\Unit;

use App\Models\Ingredient;
use App\Models\RecipeItem;
use App\Models\RecipeVersion;
use App\Services\Production\RecipeCostService;
use Illuminate\Support\Collection;
use Tests\TestCase;

class RecipeCostServiceTest extends TestCase
{
    public function test_recipe_cost_is_divided_by_standard_yield(): void
    {
        $ingredient = new Ingredient(['buy_price' => '100.00000000', 'is_active' => true]);
        $line = new RecipeItem(['quantity' => '2.000', 'waste_percent' => '0']);
        $line->setRelation('ingredient', $ingredient);
        $recipe = new RecipeVersion(['yield_quantity' => '4.000']);
        $recipe->setRelation('items', new Collection([$line]));
        $recipe->setRelation('productItems', new Collection);

        $this->assertSame('50.00', app(RecipeCostService::class)->estimate($recipe));
        $this->assertSame(1500, RecipeCostService::requiredQuantityMilli(2000, 3000, 4000));
    }
}
