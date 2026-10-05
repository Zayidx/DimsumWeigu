<?php

namespace Tests\Feature;

use App\Models\RecipeVersion;
use App\Services\Production\RecipeCostService;
use Database\Seeders\DimsumWeiguSeeder;
use Database\Seeders\TestDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecipeCostServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_estimated_hpp_tracks_current_master_cost(): void
    {
        $this->seed(DimsumWeiguSeeder::class);
        $this->seed(TestDataSeeder::class);
        $recipe = RecipeVersion::query()->with('items.ingredient')->whereHas('product', fn ($q) => $q->where('sku', 'TEST-DOR'))->where('is_active', true)->firstOrFail();
        $service = app(RecipeCostService::class);
        $initial = $service->estimate($recipe);
        $ingredient = $recipe->items->first()->ingredient;
        $ingredient->update(['buy_price' => (string) ((float) $ingredient->buy_price + 1)]);

        $this->assertGreaterThan((float) $initial, (float) $service->estimate($recipe->fresh()));
    }
}
