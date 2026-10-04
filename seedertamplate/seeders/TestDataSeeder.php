<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Ingredient;
use App\Models\IngredientCategory;
use App\Models\Product;
use App\Models\RecipeVersion;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\InventoryLedgerService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Dimsum Weigu menu and recipe data for local development/testing only.
 * DatabaseSeeder runs this automatically in the local environment. This seeder never truncates business data.
 */
class TestDataSeeder extends Seeder
{
    private array $ingredients = [];

    private array $products = [];

    private array $units = [];

    public function run(): void
    {
        if (app()->environment('production')) {
            throw new RuntimeException('TestDataSeeder hanya boleh dijalankan di development atau testing.');
        }

        $central = Warehouse::where('code', 'PUSAT')->where('is_active', true)->first();
        if (! $central) {
            throw new RuntimeException('Jalankan seeder utama terlebih dahulu agar gudang PUSAT tersedia.');
        }

        $this->seedUnits();
        $this->seedIngredientCategories();
        $this->seedIngredients();
        $this->deactivateObsoleteRawIngredientSamples();
        $this->seedMenuCategories();
        $this->seedProducts();
        $this->seedRecipes();
        $this->seedPortionRecipes();
        $this->seedIngredientOpeningStock($central);

        $this->command?->info('Data uji frozen, saus, resep menu, harga, dan stok berhasil dibuat.');
        $this->command?->warn('Seluruh komposisi resep, biaya, dan stok pada seeder ini adalah data simulasi; jangan gunakan sebagai angka produksi/akuntansi aktual.');
    }

    private function seedUnits(): void
    {
        $units = [
            ['code' => 'GRAM', 'name' => 'Gram', 'symbol' => 'g'],
            ['code' => 'ML', 'name' => 'Mililiter', 'symbol' => 'ml'],
            ['code' => 'LITER', 'name' => 'Liter', 'symbol' => 'L'],
            ['code' => 'P4', 'name' => 'Paket isi 4', 'symbol' => 'paket 4'],
            ['code' => 'P6', 'name' => 'Paket isi 6', 'symbol' => 'paket 6'],
            ['code' => 'P8', 'name' => 'Paket isi 8', 'symbol' => 'paket 8'],
            ['code' => 'P3', 'name' => 'Paket isi 3', 'symbol' => 'paket 3'],
            ['code' => 'P5', 'name' => 'Paket isi 5', 'symbol' => 'paket 5'],
            ['code' => 'PKT', 'name' => 'Paket besar', 'symbol' => 'paket'],
        ];

        foreach ($units as $unit) {
            $this->units[$unit['code']] = Unit::updateOrCreate(['code' => $unit['code']], $unit);
        }

        $this->units['PCS'] = Unit::where('code', 'PCS')->firstOrFail();

        foreach ([
            ['from' => 'KG', 'to' => 'GRAM', 'factor' => 1000],
            ['from' => 'GRAM', 'to' => 'KG', 'factor' => 0.001],
            ['from' => 'LITER', 'to' => 'ML', 'factor' => 1000],
            ['from' => 'ML', 'to' => 'LITER', 'factor' => 0.001],
        ] as $conversion) {
            $from = Unit::where('code', $conversion['from'])->first();
            $to = Unit::where('code', $conversion['to'])->first();
            if ($from && $to && DB::getSchemaBuilder()->hasTable('unit_conversions')) {
                DB::table('unit_conversions')->updateOrInsert(
                    ['from_unit_id' => $from->id, 'to_unit_id' => $to->id],
                    ['conversion_factor' => $conversion['factor'], 'updated_at' => now(), 'created_at' => now()],
                );
            }
        }
    }

    private function seedIngredientCategories(): void
    {
        foreach (['Produk Frozen', 'Saus Siap Pakai', 'Kemasan', 'Operasional'] as $name) {
            IngredientCategory::firstOrCreate(['name' => $name], ['is_active' => true]);
        }
    }

    private function seedIngredients(): void
    {
        $categories = IngredientCategory::all()->keyBy('name');
        $rows = [
            ['FROZEN-DIMSUM', 'Dimsum frozen', 'Produk Frozen', 'PCS', 1600, 3000],
            ['FROZEN-LUMPIA', 'Lumpia frozen', 'Produk Frozen', 'PCS', 2800, 3000],
            ['FROZEN-CHIQURO', 'Chiquro frozen', 'Produk Frozen', 'PCS', 8000, 3000],
            ['SAUS-MENTAI', 'Saus mentai siap pakai', 'Saus Siap Pakai', 'GRAM', 0.20, 5000],
            ['SAUS-GARLIC', 'Saus garlic siap pakai', 'Saus Siap Pakai', 'GRAM', 0.15, 1500],
            ['SAUS-LAVA', 'Saus lava siap pakai', 'Saus Siap Pakai', 'GRAM', 0.18, 2000],
            ['SAUS-CHEESE', 'Saus keju siap pakai', 'Saus Siap Pakai', 'GRAM', 0.22, 4000],
            ['CHILI-OIL', 'Chili oil siap pakai', 'Saus Siap Pakai', 'GRAM', 0.18, 5000],
            ['CUP-SAUS-PCS', 'Cup saus', 'Kemasan', 'PCS', 120, 500],
            ['BOX-KECIL', 'Box kecil', 'Kemasan', 'PCS', 250, 500],
            ['BOX-BESAR', 'Box besar', 'Kemasan', 'PCS', 450, 250],
            ['MINYAK-LITER', 'Minyak goreng', 'Operasional', 'LITER', 20000, 15],
            ['SUMPIT', 'Sumpit', 'Operasional', 'PCS', 100, 1000],
        ];

        foreach ($rows as [$code, $name, $category, $unitCode, $cost, $openingStock]) {
            $ingredient = Ingredient::updateOrCreate(
                ['code' => 'TEST-'.$code],
                [
                    'name' => $name,
                    'ingredient_category_id' => $categories[$category]->id,
                    'base_unit_id' => $this->units[$unitCode]->id,
                    'default_unit_cost' => $cost,
                    'description' => 'Data uji. Harga dan stok contoh, wajib diganti sebelum operasional.',
                    'is_active' => true,
                ],
            );
            $this->ingredients[$code] = $ingredient;
        }
    }

    private function deactivateObsoleteRawIngredientSamples(): void
    {
        Ingredient::query()
            ->whereIn('code', [
                'TEST-AYAM', 'TEST-UDANG', 'TEST-SURIMI', 'TEST-KULIT-DIMSUM', 'TEST-KULIT-LUMPIA',
                'TEST-TEPUNG', 'TEST-TAPIOKA', 'TEST-MAYO', 'TEST-SAMBAL', 'TEST-MINYAK',
                'TEST-KEJU', 'TEST-MENTEGA', 'TEST-BAWANG', 'TEST-GARAM', 'TEST-GULA', 'TEST-LADA',
                'TEST-CABAI', 'TEST-COKELAT', 'TEST-WORTEL', 'TEST-KOL', 'TEST-CUP-SAUS',
            ])
            ->update(['is_active' => false]);
    }

    private function seedMenuCategories(): void
    {
        foreach (['Dimsum', 'Lumpia', 'Chiquro', 'Paket Besar'] as $name) {
            Category::firstOrCreate(['name' => $name]);
        }
    }

    private function seedProducts(): void
    {
        $products = [
            ['DOR', 'Dimsum Ori', 'Dimsum', 'PCS', 4500, 1600, ['P4' => [4, 16000], 'P6' => [6, 24000], 'P8' => [8, 30000]]],
            ['DME', 'Dimsum Mentai', 'Dimsum', 'PCS', 5500, 2200, ['P4' => [4, 21000], 'P6' => [6, 28000], 'P8' => [8, 38000]]],
            ['DCH', 'Dimsum Cheesemelt', 'Dimsum', 'PCS', 6500, 2700, ['P4' => [4, 25000], 'P6' => [6, 35000], 'P8' => [8, 48000]]],
            ['DMX', 'Dimsum Mix', 'Dimsum', 'PCS', 6000, 2200, ['P6' => [6, 30000], 'P8' => [8, 45000]]],
            ['LOR', 'Lumpia Original', 'Lumpia', 'PCS', 9000, 2800, ['P3' => [3, 24000], 'P5' => [5, 36000]]],
            ['LME', 'Lumpia Mentai', 'Lumpia', 'PCS', 10000, 3200, ['P3' => [3, 28000], 'P5' => [5, 40000]]],
            ['CGA', 'Chiquro Garlic', 'Chiquro', 'PCS', 26000, 7000, []],
            ['CLA', 'Chiquro Lava', 'Chiquro', 'PCS', 26000, 8000, []],
            ['CME', 'Chiquro Mentai', 'Chiquro', 'PCS', 26000, 8500, []],
            ['CCH', 'Chiquro Cheese', 'Chiquro', 'PCS', 28000, 9000, []],
            ['BMX', 'Paket Besar Dimsum Mix', 'Paket Besar', 'PKT', 90000, 28000, []],
            ['BME', 'Paket Besar Dimsum Mentai', 'Paket Besar', 'PKT', 85000, 27000, []],
            ['BCH', 'Paket Besar Dimsum Cheesemelt', 'Paket Besar', 'PKT', 100000, 33000, []],
        ];

        $categoryIds = Category::whereIn('name', ['Dimsum', 'Lumpia', 'Chiquro', 'Paket Besar'])->pluck('id', 'name');
        foreach ($products as $index => [$code, $name, $category, $baseUnitCode, $price, $cost, $sellUnits]) {
            $product = Product::updateOrCreate(
                ['sku' => 'TEST-'.$code],
                [
                    'title' => $name,
                    'category_id' => $categoryIds[$category],
                    'barcode' => 'TESTDW'.str_pad((string) ($index + 1), 6, '0', STR_PAD_LEFT),
                    'description' => 'Menu data uji Dimsum Weigu. Bukan data harga atau stok operasional.',
                    'image' => null,
                    'buy_price' => $cost,
                    'sell_price' => $price,
                    'stock' => 0,
                    'tax_type' => 'exclusive',
                    'tax_rate' => 0,
                    'min_stock' => 5,
                    'max_stock' => 500,
                    'is_composite' => false,
                ],
            );

            $baseUnit = $this->units[$baseUnitCode];
            $this->setProductUnit($product, $baseUnit, true, 1, $cost, $price, 'BASE');
            foreach ($sellUnits as $unitCode => [$factor, $sellPrice]) {
                $unitCost = $cost * $factor;
                $this->setProductUnit($product, $this->units[$unitCode], false, $factor, $unitCost, $sellPrice, $unitCode);
            }
            $this->products[$code] = $product;
        }
    }

    private function setProductUnit(Product $product, Unit $unit, bool $isBase, int $factor, int $buyPrice, int $sellPrice, string $suffix): void
    {
        DB::table('product_units')->updateOrInsert(
            ['product_id' => $product->id, 'unit_id' => $unit->id],
            [
                'is_base' => $isBase,
                'conversion_factor' => $factor,
                'buy_price' => $buyPrice,
                'sell_price' => $sellPrice,
                'barcode' => null,
                'sku_suffix' => $suffix,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
    }

    private function seedRecipes(): void
    {
        $recipes = [
            'DOR' => ['yield' => 1, 'notes' => 'Dimsum frozen disiapkan kukus tanpa topping.', 'items' => ['FROZEN-DIMSUM' => [1, 'PCS']]],
            'DME' => ['yield' => 1, 'notes' => 'Dimsum frozen dengan saus mentai siap pakai; gramasi simulasi.', 'items' => ['FROZEN-DIMSUM' => [1, 'PCS'], 'SAUS-MENTAI' => [5, 'GRAM']]],
            'DCH' => ['yield' => 1, 'notes' => 'Dimsum frozen dengan saus keju siap pakai; gramasi simulasi.', 'items' => ['FROZEN-DIMSUM' => [1, 'PCS'], 'SAUS-CHEESE' => [4, 'GRAM']]],
            'DMX' => ['yield' => 1, 'notes' => 'Campuran dimsum frozen; rasio simulasi.', 'items' => ['FROZEN-DIMSUM' => [1, 'PCS']]],
            'LOR' => ['yield' => 1, 'notes' => 'Satu lumpia frozen digoreng.', 'items' => ['FROZEN-LUMPIA' => [1, 'PCS']]],
            'LME' => ['yield' => 1, 'notes' => 'Lumpia frozen dengan saus mentai siap pakai; gramasi simulasi.', 'items' => ['FROZEN-LUMPIA' => [1, 'PCS'], 'SAUS-MENTAI' => [5, 'GRAM']]],
            'CGA' => ['yield' => 1, 'notes' => 'Chiquro frozen dengan saus garlic siap pakai; gramasi simulasi.', 'items' => ['FROZEN-CHIQURO' => [1, 'PCS'], 'SAUS-GARLIC' => [5, 'GRAM']]],
            'CLA' => ['yield' => 1, 'notes' => 'Chiquro frozen dengan saus lava siap pakai; gramasi simulasi.', 'items' => ['FROZEN-CHIQURO' => [1, 'PCS'], 'SAUS-LAVA' => [6, 'GRAM']]],
            'CME' => ['yield' => 1, 'notes' => 'Chiquro frozen dengan saus mentai siap pakai; gramasi simulasi.', 'items' => ['FROZEN-CHIQURO' => [1, 'PCS'], 'SAUS-MENTAI' => [5, 'GRAM']]],
            'CCH' => ['yield' => 1, 'notes' => 'Chiquro frozen dengan saus keju siap pakai; gramasi simulasi.', 'items' => ['FROZEN-CHIQURO' => [1, 'PCS'], 'SAUS-CHEESE' => [5, 'GRAM']]],
            'BMX' => ['yield' => 1, 'notes' => 'Paket besar simulasi; komposisi aktual perlu disesuaikan.', 'items' => ['FROZEN-DIMSUM' => [20, 'PCS']]],
            'BME' => ['yield' => 1, 'notes' => 'Paket besar simulasi; komposisi aktual perlu disesuaikan.', 'items' => ['FROZEN-DIMSUM' => [20, 'PCS'], 'SAUS-MENTAI' => [100, 'GRAM']]],
            'BCH' => ['yield' => 1, 'notes' => 'Paket besar simulasi; komposisi aktual perlu disesuaikan.', 'items' => ['FROZEN-DIMSUM' => [20, 'PCS'], 'SAUS-CHEESE' => [80, 'GRAM']]],
        ];

        foreach ($recipes as $code => $recipeData) {
            $recipe = RecipeVersion::updateOrCreate(
                ['product_id' => $this->products[$code]->id, 'version_number' => 1],
                [
                    'yield_quantity' => $recipeData['yield'],
                    'unit_id' => null,
                    'notes' => '[DATA UJI] '.$recipeData['notes'],
                    'created_by' => User::where('email', 'warehouse@gmail.com')->value('id'),
                ],
            );
            $recipe->items()->delete();
            foreach ($recipeData['items'] as $ingredientCode => [$quantity, $unitCode]) {
                $unit = $this->units[$unitCode];
                $recipe->items()->create([
                    'ingredient_id' => $this->ingredients[$ingredientCode]->id,
                    'quantity' => $quantity,
                    'unit_id' => $unit->id,
                    'base_quantity' => $quantity,
                ]);
            }
        }
    }

    private function seedPortionRecipes(): void
    {
        $profiles = [];
        foreach (['DOR', 'DME', 'DCH', 'DMX'] as $menuCode) {
            $profiles[$menuCode] = [
                ['P4', 'BOX-KECIL'],
                ['P6', 'BOX-BESAR'],
                ['P8', 'BOX-BESAR'],
            ];
        }
        foreach (['LOR', 'LME'] as $menuCode) {
            $profiles[$menuCode] = [
                ['P3', 'BOX-BESAR'],
                ['P5', 'BOX-BESAR'],
            ];
        }
        foreach (['BMX', 'BME', 'BCH'] as $menuCode) {
            $profiles[$menuCode] = [['PKT', 'BOX-BESAR']];
        }

        foreach ($profiles as $menuCode => $menuProfiles) {
            $product = $this->products[$menuCode];
            foreach ($menuProfiles as $index => [$unitCode, $boxCode]) {
                $unit = $this->units[$unitCode];
                $version = $index + 2;
                $recipe = RecipeVersion::updateOrCreate(
                    ['product_id' => $product->id, 'version_number' => $version],
                    [
                        'unit_id' => $unit->id,
                        'yield_quantity' => 1,
                        'notes' => '[DATA UJI] Per porsi: 1 box, 1 sumpit, chili oil 50 gram.',
                        'created_by' => User::where('email', 'warehouse@gmail.com')->value('id'),
                    ],
                );
                $recipe->items()->delete();

                foreach ([[$boxCode, 1, 'PCS'], ['SUMPIT', 1, 'PCS'], ['CHILI-OIL', 50, 'GRAM']] as [$ingredientCode, $quantity, $unitCode]) {
                    $recipe->items()->create([
                        'ingredient_id' => $this->ingredients[$ingredientCode]->id,
                        'quantity' => $quantity,
                        'unit_id' => $this->units[$unitCode]->id,
                        'base_quantity' => $quantity,
                    ]);
                }
            }
        }
    }

    private function seedIngredientOpeningStock(Warehouse $central): void
    {
        $stocks = [
            'FROZEN-DIMSUM' => 3000, 'FROZEN-LUMPIA' => 3000, 'FROZEN-CHIQURO' => 3000,
            'SAUS-MENTAI' => 5000, 'SAUS-GARLIC' => 1500,
            'SAUS-LAVA' => 2000, 'SAUS-CHEESE' => 4000, 'CHILI-OIL' => 5000,
            'CUP-SAUS-PCS' => 500, 'BOX-KECIL' => 500, 'BOX-BESAR' => 250,
            'MINYAK-LITER' => 15, 'SUMPIT' => 1000,
        ];
        $actorId = User::where('email', 'warehouse@gmail.com')->value('id');

        foreach ($stocks as $code => $quantity) {
            $ingredient = $this->ingredients[$code];
            app(InventoryLedgerService::class)->recordOpeningStock([
                'idempotency_key' => 'testdata:opening:ingredient:'.$ingredient->id.':warehouse:'.$central->id,
                'item_type' => 'ingredient',
                'item_id' => $ingredient->id,
                'location_type' => 'warehouse',
                'location_id' => $central->id,
                'movement_type' => 'opening_stock',
                'quantity' => (string) $quantity,
                'unit_id' => $ingredient->base_unit_id,
                'unit_cost' => (string) $ingredient->default_unit_cost,
                'reference_number' => 'TEST-OPEN-'.str_replace('TEST-', '', $ingredient->code),
                'notes' => 'Saldo awal simulasi TestDataSeeder; bukan stok aktual.',
                'created_by' => $actorId,
            ]);
        }
    }
}
