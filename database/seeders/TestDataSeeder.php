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
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Dimsum Weigu menu and recipe data for local development/testing only.
 * Run explicitly for local test menu data. This seeder never truncates business data.
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

        DB::transaction(function () use ($central) {
            $this->seedUnits();
            $this->seedIngredientCategories();
            $this->seedIngredients();
            $this->seedMenuCategories();
            $this->seedProducts();
            RecipeVersion::query()->whereIn('product_id', collect($this->products)->pluck('id'))
                ->where('is_active', true)->update(['is_active' => false]);
            $this->seedRecipes();
            $this->seedPortionRecipes();
            $this->seedMenuAssemblyRecipes();
            $this->seedIngredientOpeningStock($central);
            $this->seedOutletPackagingStock();
        });

        $this->command?->info('Data uji bahan, produk setengah jadi, resep menu, harga, dan stok berhasil dibuat.');
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
            $this->units[$unit['code']] = Unit::firstOrCreate(['code' => $unit['code']], $unit);
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
                DB::table('unit_conversions')->insertOrIgnore(
                    ['from_unit_id' => $from->id, 'to_unit_id' => $to->id] +
                    ['factor' => $conversion['factor'], 'updated_at' => now(), 'created_at' => now()],
                );
            }
        }
    }

    private function seedIngredientCategories(): void
    {
        foreach (['Produk Setengah Jadi', 'Bahan mentah', 'Saus Siap Pakai', 'Kemasan', 'Operasional'] as $name) {
            IngredientCategory::firstOrCreate(['name' => $name], ['is_active' => true]);
        }
    }

    private function seedIngredients(): void
    {
        $categories = IngredientCategory::all()->keyBy('name');
        $rows = [
            ['DAGING-GILING-SAPI', 'Daging giling sapi', 'Bahan mentah', 'GRAM', 100, 0],
            ['AYAM-FILLET', 'Ayam fillet', 'Bahan mentah', 'GRAM', 57, 0],
            ['TELUR', 'Telur', 'Bahan mentah', 'GRAM', 27, 0],
            ['DAUN-BAWANG', 'Daun bawang', 'Bahan mentah', 'GRAM', 20, 0],
            ['BAWANG-BOMBAY', 'Bawang bombay', 'Bahan mentah', 'GRAM', 50, 0],
            ['BAWANG-PUTIH', 'Bawang putih', 'Bahan mentah', 'GRAM', 40, 0],
            ['MINYAK-WIJEN', 'Minyak wijen', 'Bahan mentah', 'GRAM', 75, 0],
            ['KECAP-ASIN', 'Kecap asin', 'Bahan mentah', 'ML', 60, 0],
            ['SAORI', 'Saori', 'Bahan mentah', 'GRAM', 53, 0],
            ['CHICKEN-POWDER', 'Chicken powder', 'Bahan mentah', 'GRAM', 105, 0],
            ['SASA', 'Sasa', 'Bahan mentah', 'GRAM', 50, 0],
            ['GARAM', 'Garam', 'Bahan mentah', 'GRAM', 14, 0],
            ['GULA', 'Gula pasir', 'Bahan mentah', 'GRAM', 18, 0],
            ['TEPUNG-PANIR', 'Tepung panir', 'Bahan mentah', 'GRAM', 22, 0],
            ['TAPIOKA', 'Tapioka', 'Bahan mentah', 'GRAM', 22, 0],
            ['LADA', 'Lada', 'Bahan mentah', 'GRAM', 305.555556, 0],
            ['MAYO', 'Mayones', 'Bahan mentah', 'GRAM', 25, 0],
            ['SAUS-TOMAT', 'Saus tomat', 'Bahan mentah', 'GRAM', 15, 0],
            ['SAMBAL', 'Sambal', 'Bahan mentah', 'GRAM', 17, 0],
            ['KIKOMAN', 'Kikoman', 'Bahan mentah', 'ML', 56.5, 0],
            ['OREGANO', 'Oregano', 'Bahan mentah', 'GRAM', 733.333333, 0],
            ['MINYAK-GORENG', 'Minyak goreng', 'Operasional', 'ML', 19, 0],
            ['CABE-KERING', 'Cabe kering', 'Bahan mentah', 'GRAM', 95, 0],
            ['KAPULAGA', 'Kapulaga', 'Bahan mentah', 'GRAM', 13.333333, 0],
            ['KAYU-MANIS', 'Kayu manis', 'Bahan mentah', 'GRAM', 6.666667, 0],
            ['CENGKEH', 'Cengkeh', 'Bahan mentah', 'GRAM', 13.333333, 0],
            ['PARSLEY', 'Parsley', 'Bahan mentah', 'GRAM', 1000, 0],
            ['QUICKMELT', 'Keju quickmelt', 'Bahan mentah', 'GRAM', 100, 0],
            ['AIR', 'Air', 'Operasional', 'ML', 0.315789, 0],
            ['MAIZENA', 'Maizena', 'Bahan mentah', 'GRAM', 50, 0],
            ['CHEESE-POWDER', 'Cheese powder', 'Bahan mentah', 'GRAM', 112, 0],
            ['CREAMER', 'Creamer', 'Bahan mentah', 'GRAM', 50, 0],
            ['KULIT-DIMSUM', 'Kulit dimsum', 'Bahan mentah', 'PCS', 132.978723, 0],
            ['KULIT-LUMPIA', 'Kulit lumpia', 'Bahan mentah', 'PCS', 240, 0],
            ['BEEF-PATTY', 'Beef patty', 'Bahan mentah', 'GRAM', 42.857143, 0],
            ['SAUS-DIMSUM', 'Saus dimsum', 'Saus Siap Pakai', 'GRAM', 18, 0],
            ['WORTEL', 'Wortel', 'Bahan mentah', 'GRAM', 30, 0],
            ['RUMPUT-LAUT', 'Rumput laut', 'Bahan mentah', 'GRAM', 416, 0],
            ['SAUS-MENTAI', 'Saus mentai dimsum/lumpia, 30 g per porsi', 'Saus Siap Pakai', 'GRAM', 726.535769 / 30, 0],
            ['SAUS-BOLOGNESE', 'Saus bolognese, 35 g per porsi', 'Saus Siap Pakai', 'GRAM', 994.316 / 35, 0],
            ['SAUS-GARLIC', 'Saus garlic Chiquro, 60 g per porsi', 'Saus Siap Pakai', 'GRAM', 1952.761111 / 60, 0],
            ['SAUS-LAVA', 'Saus lava Chiquro, 60 g per porsi', 'Saus Siap Pakai', 'GRAM', 1450 / 60, 0],
            ['SAUS-CHIQ-MENTAI', 'Saus mentai Chiquro, 60 g per porsi', 'Saus Siap Pakai', 'GRAM', 1442.15 / 60, 0],
            ['SAUS-CHEESE', 'Saus cheese (tidak dipakai untuk Chiquro Cheese)', 'Saus Siap Pakai', 'GRAM', 2106.887895 / 60, 0],
            ['GOURMET-BBQ', 'Gourmet BBQ', 'Bahan mentah', 'GRAM', 48, 0],
            ['CHILI-OIL', 'Chili oil racikan', 'Saus Siap Pakai', 'GRAM', 0, 0],
            ['CHILI-OIL-CUP', 'Chili oil siap saji dalam cup', 'Kemasan', 'PCS', 647.954556, 100],
            ['CUP-SAUS-PCS', 'Cup chili oil kosong', 'Kemasan', 'PCS', 100, 100],
            ['BOX-KECIL', 'Kotak dimsum kecil', 'Kemasan', 'PCS', 150000 / 379, 500],
            ['BOX-BESAR', 'Kotak dimsum besar', 'Kemasan', 'PCS', 200000 / 425, 250],
            ['PLASTIK-DIMSUM', 'Plastik dimsum 24', 'Kemasan', 'PCS', 275, 500],
            ['KERTAS-CHIQURO', 'Kertas Chiquro', 'Kemasan', 'PCS', 75, 500],
            ['PLASTIK-CHIQURO', 'Plastik kecil Chiquro', 'Kemasan', 'PCS', 150, 500],
            ['SUMPIT', 'Sumpit', 'Kemasan', 'PCS', 88, 1000],
            ['MINYAK-LITER', 'Minyak goreng', 'Operasional', 'ML', 19, 0],
        ];

        foreach ($rows as [$code, $name, $category, $unitCode, $cost, $openingStock]) {
            $ingredient = Ingredient::firstOrCreate(
                ['code' => 'TEST-'.$code],
                [
                    'name' => $name,
                    'ingredient_category_id' => $categories[$category]->id,
                    'unit_id' => $this->units[$unitCode]->id,
                    'buy_price' => $cost,
                    'description' => 'Data uji. Harga dan stok contoh, wajib diganti sebelum operasional.',
                    'is_active' => true,
                ],
            );
            $ingredient->forceFill([
                'name' => $name,
                'ingredient_category_id' => $categories[$category]->id,
                'unit_id' => $this->units[$unitCode]->id,
                'buy_price' => $cost,
                'description' => 'Harga per gr/ml/pcs mengikuti workbook HPP; saldo existing dipertahankan.',
                'is_active' => true,
            ])->save();
            $this->ingredients[$code] = $ingredient;
        }
        Ingredient::query()->whereIn('code', ['TEST-FROZEN-DIMSUM', 'TEST-FROZEN-LUMPIA', 'TEST-FROZEN-CHIQURO'])
            ->update(['is_active' => false]);
    }

    private function seedMenuCategories(): void
    {
        foreach (['Dimsum', 'Lumpia', 'Chiquro', 'Paket Besar'] as $name) {
            Category::firstOrCreate(['name' => $name], ['slug' => Str::slug($name)]);
        }

        $parent = Category::firstOrCreate(['slug' => 'setengah-jadi'], ['name' => 'Setengah Jadi', 'is_active' => true]);
        foreach (['Dimsum', 'Lumpia', 'Chiquro'] as $name) {
            Category::firstOrCreate(['slug' => 'setengah-jadi-'.Str::slug($name)], [
                'name' => $name, 'parent_id' => $parent->id, 'is_active' => true,
            ]);
        }
    }

    private function seedProducts(): void
    {
        $products = [
            ['DOR', 'Dimsum Ori', 'Dimsum', 'PCS', 4000, 0, ['P4' => [4, 16000], 'P6' => [6, 22000], 'P8' => [8, 28000]]],
            ['DME', 'Dimsum Mentai', 'Dimsum', 'PCS', 5000, 0, ['P4' => [4, 20000], 'P6' => [6, 26000], 'P8' => [8, 36000]]],
            ['DCH', 'Dimsum Bolognese', 'Dimsum', 'PCS', 5500, 0, ['P4' => [4, 22000], 'P6' => [6, 31000], 'P8' => [8, 40000]]],
            ['DMX', 'Dimsum Mix', 'Dimsum', 'PCS', 0, 0, ['P6' => [6, 29000], 'P8' => [8, 40000]]],
            ['LOR', 'Lumpia Original', 'Lumpia', 'PCS', 0, 0, ['P3' => [3, 22000], 'P5' => [5, 34000]]],
            ['LME', 'Lumpia Mentai', 'Lumpia', 'PCS', 0, 0, ['P3' => [3, 26000], 'P5' => [5, 38000]]],
            ['CGA', 'Chiquro Garlic', 'Chiquro', 'PCS', 26000, 0, []],
            ['CLA', 'Chiquro Lava', 'Chiquro', 'PCS', 26000, 0, []],
            ['CME', 'Chiquro Mentai', 'Chiquro', 'PCS', 26000, 0, []],
            ['CCH', 'Chiquro Cheese', 'Chiquro', 'PCS', 28000, 0, []],
            ['DPL', 'Dimsum Ori tanpa topping · Setengah Jadi', 'Dimsum', 'PCS', 0, 0, []],
            ['DWO', 'Dimsum Wortel · Setengah Jadi', 'Dimsum', 'PCS', 0, 0, []],
            ['DBE', 'Dimsum Beef · Setengah Jadi', 'Dimsum', 'PCS', 0, 0, []],
            ['LPS', 'Lumpia · Setengah Jadi', 'Lumpia', 'PCS', 0, 0, []],
            ['CHB', 'Chiquro Basic mentah · Setengah Jadi', 'Chiquro', 'PCS', 0, 0, []],
            ['CHS', 'Chiquro Cheese mentah · Setengah Jadi', 'Chiquro', 'PCS', 0, 0, []],
        ];

        $categoryIds = Category::whereIn('name', ['Dimsum', 'Lumpia', 'Chiquro', 'Paket Besar'])->pluck('id', 'name');
        foreach ($products as $index => [$code, $name, $category, $baseUnitCode, $price, $cost, $sellUnits]) {
            $sku = 'TEST-'.$code;
            $product = Product::firstOrCreate(
                ['sku' => $sku],
                [
                    'title' => $name,
                    'category_id' => $categoryIds[$category],
                    'barcode' => $this->availableProductBarcode($sku, $index + 1),
                    'description' => 'Menu data uji Dimsum Weigu. Bukan data harga atau stok operasional.',
                    'image_path' => null,
                    'unit_id' => $this->units[$baseUnitCode]->id,
                    'buy_price' => $cost,
                    'sell_price' => $price,
                    'stock' => 0,
                    'tax_inclusive' => false,
                    'tax_rate' => 0,
                    'min_stock' => 5,
                    'max_stock' => 500,
                    'allow_single_unit_sale' => in_array($code, ['CGA', 'CLA', 'CME', 'CCH'], true),
                ],
            );

            $previousDefaults = ['DOR' => 4500, 'DME' => 5500, 'DCH' => 6500, 'DMX' => 6000, 'LOR' => 9000, 'LME' => 10000];
            $product->forceFill([
                'title' => $name, 'category_id' => $categoryIds[$category],
                'allow_single_unit_sale' => in_array($code, ['CGA', 'CLA', 'CME', 'CCH'], true),
            ]);
            if (isset($previousDefaults[$code]) && (float) $product->sell_price === (float) $previousDefaults[$code]) {
                $product->sell_price = $price;
                $product->buy_price = $cost;
            }
            $product->save();

            $baseUnit = $this->units[$baseUnitCode];
            $this->setProductUnit($product, $baseUnit, true, 1, $cost, $price, 'BASE');
            foreach ($sellUnits as $unitCode => [$factor, $sellPrice]) {
                $unitCost = $cost * $factor;
                $this->setProductUnit($product, $this->units[$unitCode], false, $factor, $unitCost, $sellPrice, $unitCode);
            }
            $this->products[$code] = $product;
            if (in_array($code, ['DPL', 'DWO', 'DBE', 'LPS', 'CHB', 'CHS'], true)) {
                $semiCategory = Category::query()->where('slug', 'setengah-jadi-'.Str::slug($category))->firstOrFail();
                $product->forceFill(['category_id' => $semiCategory->id, 'is_sellable' => false, 'is_semi_finished' => true, 'track_stock' => true])->save();
            } elseif ($code === 'DMX') {
                $product->forceFill(['is_sellable' => false])->save();
            }
        }
        Product::query()->whereIn('sku', ['TEST-BMX', 'TEST-BME', 'TEST-BCH'])
            ->update(['is_active' => false, 'is_sellable' => false]);
    }

    private function availableProductBarcode(string $sku, int $sequence): string
    {
        $barcode = 'TESTDW'.str_pad((string) $sequence, 6, '0', STR_PAD_LEFT);
        if (! Product::withTrashed()->where('barcode', $barcode)->where('sku', '!=', $sku)->exists()) {
            return $barcode;
        }

        for ($attempt = 1; ; $attempt++) {
            $barcode = $sku.'-'.str_pad((string) $attempt, 3, '0', STR_PAD_LEFT);
            if (! Product::withTrashed()->where('barcode', $barcode)->where('sku', '!=', $sku)->exists()) {
                return $barcode;
            }
        }
    }

    private function setProductUnit(Product $product, Unit $unit, bool $isBase, int $factor, float $buyPrice, int $sellPrice, string $suffix): void
    {
        DB::table('product_units')->updateOrInsert(
            ['product_id' => $product->id, 'unit_id' => $unit->id],
            [
                'is_base' => $isBase,
                'is_default' => $isBase,
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
            'DPL' => ['yield' => 1, 'notes' => 'Basis satu buah dimsum ori polos dari resep topping wortel/beef; takaran topping dihilangkan.', 'items' => ['AYAM-FILLET' => [20, 'GRAM'], 'TELUR' => [1.1, 'GRAM'], 'DAUN-BAWANG' => [1, 'GRAM'], 'BAWANG-PUTIH' => [0.5, 'GRAM'], 'MINYAK-WIJEN' => [0.4, 'GRAM'], 'KECAP-ASIN' => [0.1, 'ML'], 'SAORI' => [0.6, 'GRAM'], 'CHICKEN-POWDER' => [0.4, 'GRAM'], 'SASA' => [0.3, 'GRAM'], 'GARAM' => [0.14, 'GRAM'], 'GULA' => [0.5, 'GRAM'], 'KULIT-DIMSUM' => [1, 'PCS'], 'TAPIOKA' => [3.6, 'GRAM']]],
            'DWO' => ['yield' => 1, 'notes' => 'Satu buah dimsum topping wortel dari resep HPP.', 'items' => ['AYAM-FILLET' => [20, 'GRAM'], 'TELUR' => [1.1, 'GRAM'], 'DAUN-BAWANG' => [1, 'GRAM'], 'BAWANG-PUTIH' => [0.5, 'GRAM'], 'MINYAK-WIJEN' => [0.4, 'GRAM'], 'KECAP-ASIN' => [0.1, 'ML'], 'SAORI' => [0.6, 'GRAM'], 'CHICKEN-POWDER' => [0.4, 'GRAM'], 'SASA' => [0.3, 'GRAM'], 'GARAM' => [0.14, 'GRAM'], 'GULA' => [0.5, 'GRAM'], 'KULIT-DIMSUM' => [1, 'PCS'], 'TAPIOKA' => [3.6, 'GRAM'], 'WORTEL' => [0.5, 'GRAM']]],
            'DBE' => ['yield' => 1, 'notes' => 'Satu buah dimsum topping beef dari resep HPP.', 'items' => ['AYAM-FILLET' => [20, 'GRAM'], 'TELUR' => [1.1, 'GRAM'], 'DAUN-BAWANG' => [1, 'GRAM'], 'BAWANG-PUTIH' => [0.5, 'GRAM'], 'MINYAK-WIJEN' => [0.4, 'GRAM'], 'KECAP-ASIN' => [0.1, 'ML'], 'SAORI' => [0.6, 'GRAM'], 'CHICKEN-POWDER' => [0.4, 'GRAM'], 'SASA' => [0.3, 'GRAM'], 'GARAM' => [0.14, 'GRAM'], 'GULA' => [0.5, 'GRAM'], 'KULIT-DIMSUM' => [1, 'PCS'], 'TAPIOKA' => [3.6, 'GRAM'], 'BEEF-PATTY' => [1, 'GRAM']]],
            'LPS' => ['yield' => 1, 'notes' => 'Resep Lumpia keju ori per satu hasil resep dari workbook; komposisi cheese ikut produk setengah jadi.', 'items' => ['AYAM-FILLET' => [25, 'GRAM'], 'TELUR' => [1.375, 'GRAM'], 'KULIT-LUMPIA' => [2, 'PCS'], 'BAWANG-PUTIH' => [0.625, 'GRAM'], 'MINYAK-WIJEN' => [0.5, 'GRAM'], 'KECAP-ASIN' => [0.125, 'ML'], 'SAORI' => [0.75, 'GRAM'], 'CHICKEN-POWDER' => [0.5, 'GRAM'], 'SASA' => [0.375, 'GRAM'], 'GARAM' => [0.175, 'GRAM'], 'GULA' => [0.625, 'GRAM'], 'QUICKMELT' => [8, 'GRAM'], 'TAPIOKA' => [4.5, 'GRAM']]],
            'CHB' => ['yield' => 1, 'notes' => 'Resep Chiquro Basic dari workbook; yield produk per batch perlu dikonfirmasi.', 'items' => ['AYAM-FILLET' => [100, 'GRAM'], 'TELUR' => [5.5, 'GRAM'], 'LADA' => [0.2, 'GRAM'], 'BAWANG-PUTIH' => [2, 'GRAM'], 'SAORI' => [1.5, 'GRAM'], 'CHICKEN-POWDER' => [2, 'GRAM'], 'SASA' => [1, 'GRAM'], 'GARAM' => [0.5, 'GRAM'], 'MINYAK-GORENG' => [50, 'ML'], 'TAPIOKA' => [10, 'GRAM'], 'RUMPUT-LAUT' => [2, 'GRAM'], 'TEPUNG-PANIR' => [13, 'GRAM']]],
        ];

        foreach ($recipes as $code => $recipeData) {
            $recipe = $this->managedRecipe($this->products[$code], $this->units['PCS'], $recipeData['notes'], $recipeData['yield']);
            $recipe->items()->delete();
            $recipe->productItems()->delete();
            foreach ($recipeData['items'] as $ingredientCode => [$quantity, $unitCode]) {
                $unit = $this->units[$unitCode];
                $recipe->items()->firstOrCreate(['ingredient_id' => $this->ingredients[$ingredientCode]->id], [
                    'quantity' => $quantity,
                    'unit_id' => $unit->id,
                    'base_quantity' => $quantity,
                ]);
            }
        }

        RecipeVersion::query()->where('product_id', $this->products['CHS']->id)->where('is_active', true)
            ->update(['is_active' => false, 'ends_at' => now()]);
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
        foreach ($profiles as $menuCode => $menuProfiles) {
            $product = $this->products[$menuCode];
            foreach ($menuProfiles as $index => [$unitCode, $boxCode]) {
                $unit = $this->units[$unitCode];
                $version = $index + 2;
                $recipe = $this->managedRecipe($product, $unit, 'Porsi menggunakan box, plastik, sumpit, dan satu cup chili oil sesuai tipe paket.');
                $recipe->items()->delete();

                foreach ([[$boxCode, 1, 'PCS'], ['PLASTIK-DIMSUM', 1, 'PCS'], ['SUMPIT', 1, 'PCS'], ['CHILI-OIL-CUP', 1, 'PCS']] as [$ingredientCode, $quantity, $unitCode]) {
                    $recipe->items()->firstOrCreate(['ingredient_id' => $this->ingredients[$ingredientCode]->id], [
                        'quantity' => $quantity,
                        'unit_id' => $this->units[$unitCode]->id,
                        'base_quantity' => $quantity,
                    ]);
                }
            }
        }
    }

    /** Package checkout converts semi-finished outlet stock into the ready-to-serve menu. */
    private function seedMenuAssemblyRecipes(): void
    {
        foreach (['DOR', 'DME', 'DCH'] as $menuCode) {
            $sauce = match ($menuCode) {
                'DME' => ['SAUS-MENTAI' => 120],
                'DCH' => ['SAUS-BOLOGNESE' => 140],
                default => [],
            };
            $this->addAssemblyRecipe($this->products[$menuCode], 'P4', ['DPL' => 3, 'DWO' => 1], 2,
                'Isi 4: tiga ori tanpa topping + satu wortel. Saus mengikuti gramasi workbook.', $sauce);
        }

        foreach (['LOR', 'LME'] as $menuCode) {
            $sauceQty = $menuCode === 'LME' ? 90 : 0;
            $this->addAssemblyRecipe($this->products[$menuCode], 'P3', ['LPS' => 3], 2,
                'Isi 3 lumpia setengah jadi. Saus mentai 30 g per buah sesuai workbook.', $sauceQty ? ['SAUS-MENTAI' => $sauceQty] : []);
            $this->addAssemblyRecipe($this->products[$menuCode], 'P5', ['LPS' => 5], 3,
                'Isi 5 lumpia setengah jadi. Saus mentai 30 g per buah sesuai workbook.', $sauceQty ? ['SAUS-MENTAI' => 150] : []);
        }

        foreach (['CGA' => ['CHB', 'SAUS-GARLIC'], 'CLA' => ['CHB', 'SAUS-LAVA'], 'CME' => ['CHB', 'SAUS-CHIQ-MENTAI'], 'CCH' => ['CHS', null]] as $menuCode => [$componentCode, $sauceCode]) {
            $recipe = $this->managedRecipe($this->products[$menuCode], $this->units['PCS'], 'Satu Chiquro setengah jadi, satu kertas, satu plastik kecil, dan satu porsi saus sesuai workbook.');
            $recipe->items()->whereIn('ingredient_id', collect(['SAUS-GARLIC', 'SAUS-LAVA', 'SAUS-CHIQ-MENTAI', 'SAUS-CHEESE'])->map(fn ($code) => $this->ingredients[$code]->id)->all())->delete();
            $recipe->productItems()->delete();
            $recipe->productItems()->firstOrCreate(['product_id' => $this->products[$componentCode]->id], [
                'unit_id' => $this->units['PCS']->id, 'quantity' => 1, 'waste_percent' => 0,
            ]);
            foreach (['KERTAS-CHIQURO', 'PLASTIK-CHIQURO'] as $ingredientCode) {
                $recipe->items()->firstOrCreate(['ingredient_id' => $this->ingredients[$ingredientCode]->id], [
                    'quantity' => 1, 'unit_id' => $this->units['PCS']->id, 'base_quantity' => 1,
                ]);
            }
            if ($sauceCode) {
                $recipe->items()->firstOrCreate(['ingredient_id' => $this->ingredients[$sauceCode]->id], [
                    'quantity' => 60, 'unit_id' => $this->units['GRAM']->id, 'base_quantity' => 60,
                ]);
            }
        }
    }

    private function addAssemblyRecipe(Product $menu, string $unitCode, array $components, int $version, string $notes, array $ingredients = []): void
    {
        $recipe = $this->managedRecipe($menu, $this->units[$unitCode], $notes);
        $recipe->productItems()->delete();
        $recipe->items()->whereIn('ingredient_id', collect(['SAUS-MENTAI', 'SAUS-BOLOGNESE'])->map(fn ($code) => $this->ingredients[$code]->id)->all())->delete();
        foreach ($components as $code => $quantity) {
            $recipe->productItems()->firstOrCreate(['product_id' => $this->products[$code]->id], [
                'unit_id' => $this->units['PCS']->id, 'quantity' => $quantity, 'waste_percent' => 0,
            ]);
        }
        foreach ($ingredients as $code => $quantity) {
            $recipe->items()->firstOrCreate(['ingredient_id' => $this->ingredients[$code]->id], [
                'unit_id' => $this->units['GRAM']->id, 'quantity' => $quantity, 'base_quantity' => $quantity,
            ]);
        }
    }

    private function managedRecipe(Product $product, Unit $unit, string $notes, int $yield = 1): RecipeVersion
    {
        $recipe = RecipeVersion::query()->where('product_id', $product->id)->where('unit_id', $unit->id)
            ->where('notes', 'like', '[DATA UJI SYNC]%')->orderByDesc('version')->first();
        if (! $recipe) {
            $version = ((int) RecipeVersion::query()->where('product_id', $product->id)->max('version')) + 1;
            $recipe = new RecipeVersion(['product_id' => $product->id, 'unit_id' => $unit->id, 'version' => $version]);
        }
        $recipe->forceFill([
            'yield_quantity' => $yield, 'is_active' => true, 'starts_at' => now(),
            'notes' => '[DATA UJI SYNC] '.$notes,
            'created_by' => User::where('email', 'warehouse@gmail.com')->value('id'),
        ])->save();

        return $recipe;
    }

    private function seedIngredientOpeningStock(Warehouse $central): void
    {
        $stocks = [
            'DAGING-GILING-SAPI' => 10000, 'AYAM-FILLET' => 50000, 'TELUR' => 5000,
            'DAUN-BAWANG' => 5000, 'BAWANG-BOMBAY' => 5000, 'BAWANG-PUTIH' => 5000,
            'MINYAK-WIJEN' => 3000, 'KECAP-ASIN' => 3000, 'SAORI' => 5000,
            'CHICKEN-POWDER' => 3000, 'SASA' => 3000, 'GARAM' => 2000, 'GULA' => 5000,
            'TEPUNG-PANIR' => 2000, 'TAPIOKA' => 10000, 'LADA' => 1000, 'MAYO' => 2000,
            'SAUS-TOMAT' => 0, 'SAMBAL' => 0, 'KIKOMAN' => 0, 'OREGANO' => 0,
            'MINYAK-GORENG' => 5000, 'CABE-KERING' => 1000, 'KAPULAGA' => 500,
            'KAYU-MANIS' => 500, 'CENGKEH' => 500, 'PARSLEY' => 500, 'QUICKMELT' => 2000,
            'AIR' => 10000, 'MAIZENA' => 1000, 'CHEESE-POWDER' => 1000, 'CREAMER' => 1000,
            'KULIT-DIMSUM' => 5000, 'KULIT-LUMPIA' => 5000, 'BEEF-PATTY' => 5000,
            'SAUS-DIMSUM' => 2000, 'SAUS-BOLOGNESE' => 5000, 'SAUS-LAVA' => 0,
            'SAUS-CHIQ-MENTAI' => 0, 'SAUS-GARLIC' => 0, 'GOURMET-BBQ' => 0,
            'RUMPUT-LAUT' => 1000, 'WORTEL' => 3000, 'SAUS-MENTAI' => 10000,
            'SAUS-GARLIC' => 5000, 'SAUS-CHEESE' => 5000, 'CHILI-OIL' => 5000,
            'CHILI-OIL-CUP' => 100, 'CUP-SAUS-PCS' => 100, 'BOX-KECIL' => 500, 'BOX-BESAR' => 250,
            'PLASTIK-DIMSUM' => 500, 'KERTAS-CHIQURO' => 500,
            'PLASTIK-CHIQURO' => 500, 'SUMPIT' => 1000,
        ];
        $actorId = User::where('email', 'warehouse@gmail.com')->value('id');

        foreach ($this->ingredients as $code => $ingredient) {
            $quantity = $stocks[$code] ?? 0;
            DB::transaction(function () use ($ingredient, $central, $quantity, $actorId) {
                $locked = Ingredient::whereKey($ingredient->id)->lockForUpdate()->firstOrFail();
                $key = 'testdata:opening-v2:ingredient:'.$ingredient->id.':warehouse:'.$central->id;
                if (DB::table('ingredient_inventory_ledgers')->where('idempotency_key', $key)->exists()) {
                    return;
                }
                // Only initialize an unused ingredient; preserve inventory already in operation.
                if ((float) $locked->stock !== 0.0
                    || DB::table('ingredient_inventory_ledgers')->where('ingredient_id', $ingredient->id)->where('quantity_delta', '<>', 0)->exists()
                    || DB::table('ingredient_warehouses')->where('ingredient_id', $ingredient->id)->where('stock', '<>', 0)->exists()) {
                    $this->command?->warn('Saldo awal dilewati untuk '.$ingredient->code.': sudah memiliki stok/ledger.');

                    return;
                }
                DB::table('ingredient_warehouses')->insertOrIgnore(['ingredient_id' => $ingredient->id, 'warehouse_id' => $central->id, 'stock' => 0, 'created_at' => now(), 'updated_at' => now()]);
                DB::table('ingredient_warehouses')->where('ingredient_id', $ingredient->id)->where('warehouse_id', $central->id)->update(['stock' => $quantity, 'updated_at' => now()]);
                $locked->update(['stock' => $quantity]);
                DB::table('ingredient_inventory_ledgers')->insert([
                    'ingredient_id' => $ingredient->id, 'warehouse_id' => $central->id, 'user_id' => $actorId,
                    'type' => 'opening_stock', 'quantity_delta' => $quantity, 'quantity_before' => 0, 'quantity_after' => $quantity,
                    'reference_type' => 'TestDataSeeder', 'idempotency_key' => $key, 'created_at' => now(),
                ]);
            });
        }
    }

    private function seedOutletPackagingStock(): void
    {
        $outletStocks = [
            'SAUS-MENTAI' => 5000,
            'SAUS-BOLOGNESE' => 5000,
            'SAUS-GARLIC' => 5000,
            'SAUS-LAVA' => 5000,
            'SAUS-CHIQ-MENTAI' => 5000,
            'SAUS-CHEESE' => 5000,
            'BOX-KECIL' => 100,
            'BOX-BESAR' => 100,
            'PLASTIK-DIMSUM' => 100,
            'SUMPIT' => 100,
            'CHILI-OIL-CUP' => 100,
            'KERTAS-CHIQURO' => 100,
            'PLASTIK-CHIQURO' => 100,
        ];
        $actorId = User::where('email', 'warehouse@gmail.com')->value('id');

        foreach (Warehouse::query()->where('type', 'branch')->where('is_sales_enabled', true)->where('is_active', true)->get() as $outlet) {
            foreach ($outletStocks as $code => $quantity) {
                $ingredient = $this->ingredients[$code];
                DB::transaction(function () use ($ingredient, $outlet, $quantity, $actorId): void {
                    $locked = Ingredient::query()->whereKey($ingredient->id)->lockForUpdate()->firstOrFail();
                    $key = 'testdata:outlet-opening:ingredient:'.$ingredient->id.':warehouse:'.$outlet->id;
                    if (DB::table('ingredient_inventory_ledgers')->where('idempotency_key', $key)->exists()) {
                        return;
                    }

                    $balance = DB::table('ingredient_warehouses')->where('ingredient_id', $ingredient->id)->where('warehouse_id', $outlet->id)->lockForUpdate()->first();
                    if (($balance && (float) $balance->stock !== 0.0)
                        || DB::table('ingredient_inventory_ledgers')->where('ingredient_id', $ingredient->id)->where('warehouse_id', $outlet->id)->exists()) {
                        $this->command?->warn('Saldo outlet dilewati untuk '.$ingredient->code.' di '.$outlet->code.': sudah memiliki stok/ledger.');

                        return;
                    }

                    DB::table('ingredient_warehouses')->updateOrInsert(
                        ['ingredient_id' => $ingredient->id, 'warehouse_id' => $outlet->id],
                        ['stock' => $quantity, 'reserved_stock' => 0, 'created_at' => $balance?->created_at ?? now(), 'updated_at' => now()],
                    );
                    $locked->update(['stock' => number_format((float) $locked->stock + $quantity, 3, '.', '')]);
                    DB::table('ingredient_inventory_ledgers')->insert([
                        'ingredient_id' => $ingredient->id, 'warehouse_id' => $outlet->id, 'user_id' => $actorId,
                        'type' => 'opening_stock', 'quantity_delta' => $quantity, 'quantity_before' => 0, 'quantity_after' => $quantity,
                        'reference_type' => self::class, 'idempotency_key' => $key, 'created_at' => now(),
                    ]);
                });
            }
        }
    }
}
