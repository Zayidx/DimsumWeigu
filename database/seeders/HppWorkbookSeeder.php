<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Ingredient;
use App\Models\IngredientCategory;
use App\Models\Product;
use App\Models\RecipeVersion;
use App\Models\Unit;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/** Imports source master data without changing existing stock, prices, or recipes. */
class HppWorkbookSeeder extends Seeder
{
    public function run(): void
    {
        $path = base_path('hpp.json');
        if (! is_file($path)) {
            throw new RuntimeException('File hpp.json tidak ditemukan di root repository.');
        }
        $source = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        if (($source['sumber'] ?? null) !== 'Weigu - ALL HPP.xlsx') {
            throw new RuntimeException('Sumber data hpp.json harus Weigu - ALL HPP.xlsx. Perbarui snapshot JSON dari workbook tersebut sebelum seeding.');
        }
        foreach (['bahan_baku', 'menu', 'resep', 'kemasan'] as $key) {
            if (! is_array($source[$key] ?? null)) {
                throw new RuntimeException("Data {$key} pada hpp.json tidak valid.");
            }
        }

        $stats = DB::transaction(function () use ($source): array {
            Unit::firstOrCreate(['code' => 'SET'], ['name' => 'Set', 'symbol' => 'set', 'dimension' => 'quantity', 'is_active' => true]);
            $units = Unit::query()->get()->keyBy(fn (Unit $unit) => strtoupper($unit->symbol));
            $unitFor = function (?string $symbol) use ($units): Unit {
                $symbol = strtolower(trim((string) $symbol));
                $aliases = ['gr' => 'g', 'gram' => 'g', 'grams' => 'g', 'ml' => 'ml', 'pcs' => 'pcs', 'pc' => 'pcs', 'kg' => 'kg', 'lt' => 'l', 'liter' => 'l', 'l' => 'l'];
                $symbol = $aliases[$symbol] ?? $symbol;
                $unit = $units->get(strtoupper($symbol));
                if (! $unit) {
                    $code = match ($symbol) {
                        'g' => 'GRAM', 'ml' => 'ML', 'pcs' => 'PCS', 'kg' => 'KG', 'l' => 'LITER', 'set' => 'SET', default => null
                    };
                    $unit = $code ? Unit::query()->where('code', $code)->first() : null;
                }
                if (! $unit) {
                    throw new RuntimeException("Satuan sumber '{$symbol}' belum tersedia. Tambahkan satuan terlebih dahulu.");
                }

                return $unit;
            };

            $category = IngredientCategory::firstOrCreate(['name' => 'Bahan HPP Workbook'], ['is_active' => true]);
            $recipeUnits = [];
            foreach ($source['resep'] ?? [] as $recipe) {
                foreach ($recipe['bahan'] ?? [] as $line) {
                    if (filled($line['nama'] ?? null) && filled($line['uom'] ?? null)) {
                        $recipeUnits[mb_strtolower(trim($line['nama']))] = $line['uom'];
                    }
                }
            }
            $seedIngredient = function (string $code, string $name, ?string $sourceUnit, mixed $price, string $description, IngredientCategory $group) use ($unitFor): Ingredient {
                $unit = $unitFor($sourceUnit);
                $ingredient = Ingredient::withTrashed()->firstOrNew(['code' => $code]);
                if (! $ingredient->exists) {
                    $ingredient->fill([
                        'ingredient_category_id' => $group->id, 'unit_id' => $unit->id, 'name' => trim($name),
                        'buy_price' => number_format((float) $price, 8, '.', ''), 'stock' => 0, 'reorder_point' => 0,
                        'is_active' => true, 'description' => $description,
                    ])->save();
                }
                if ($ingredient->trashed()) {
                    $ingredient->restore();
                }

                return $ingredient;
            };
            foreach ($source['bahan_baku'] as $row) {
                if (! filled($row['nama'] ?? null) || ! is_numeric($row['harga_satuan'] ?? null)) {
                    continue;
                }
                $name = trim($row['nama']);
                // Recipe formulas use the price rate as their unit cost; source UOM labels can describe the purchase pack.
                $code = 'HPP-'.str_pad((string) ($row['no'] ?? 0), 3, '0', STR_PAD_LEFT);
                $seedIngredient($code, $name, $recipeUnits[mb_strtolower($name)] ?? $row['uom'] ?? null, $row['harga_satuan'], 'Sumber hpp.json · Master Bahan baris '.($row['sumber']['baris'] ?? '?').'; label satuan sumber: '.($row['uom'] ?? 'kosong').'; harga per satuan formula.', $category);
            }

            $knownNames = collect($source['bahan_baku'])->pluck('nama')->filter()->map(fn ($name) => mb_strtolower(trim($name)))->all();
            $recipeCategory = IngredientCategory::firstOrCreate(['name' => 'Komponen Resep HPP'], ['is_active' => true]);
            foreach ($source['resep'] ?? [] as $recipe) {
                foreach ($recipe['bahan'] ?? [] as $line) {
                    $name = trim((string) ($line['nama'] ?? ''));
                    $normalized = mb_strtolower($name);
                    if ($name === '' || in_array($normalized, $knownNames, true) || ! is_numeric($line['harga_satuan'] ?? null)) {
                        continue;
                    }
                    $hash = strtoupper(substr(hash('sha256', $normalized), 0, 10));
                    $seedIngredient('HPP-R-'.$hash, $name, $line['uom'] ?? 'gr', $line['harga_satuan'], 'Komponen dari hpp.json · resep '.($recipe['nama'] ?? 'tanpa nama').'; bahan ini tidak cocok dengan nama persis Master Bahan dan perlu verifikasi.', $recipeCategory);
                }
            }

            $packagingCategory = IngredientCategory::firstOrCreate(['name' => 'Kemasan HPP Workbook'], ['is_active' => true]);
            foreach ($source['kemasan'] as $row) {
                if (filled($row['nama'] ?? null) && is_numeric($row['harga_satuan'] ?? null)) {
                    $code = 'HPP-P-'.str_pad((string) ($row['no'] ?? 0), 3, '0', STR_PAD_LEFT);
                    $seedIngredient($code, trim($row['nama']), $row['uom'] ?? 'pcs', $row['harga_satuan'], 'Sumber hpp.json · Packaging baris '.($row['sumber']['baris'] ?? '?').'; harga per unit.', $packagingCategory);
                }
            }
            $missingBox = collect($source['kemasan'])->first(fn (array $row) => mb_strtolower(trim((string) ($row['nama'] ?? ''))) === 'kotak box dimsum 4 pcs');
            if ($missingBox && ! is_numeric($missingBox['harga_satuan'] ?? null)) {
                $code = 'HPP-P-'.str_pad((string) ($missingBox['no'] ?? 7), 3, '0', STR_PAD_LEFT);
                $seedIngredient($code, trim($missingBox['nama']), $missingBox['uom'] ?? 'pcs', 0, 'Kemasan dimsum 4 pcs yang belum memiliki harga pada sumber Excel; Finance perlu mengisi harga aktual sebelum digunakan untuk HPP.', $packagingCategory);
            }

            $categoryProduct = Category::firstOrCreate(['slug' => 'menu-hpp-workbook'], ['name' => 'Menu HPP Workbook', 'is_active' => true]);
            $unit = Unit::query()->where('code', 'PCS')->firstOrFail();
            $menuNumberCounts = collect($source['menu'])->countBy(fn (array $row) => (string) ($row['no'] ?? 0));
            $menuNumberSeen = [];
            $menuSkuByIndex = [];
            foreach ($source['menu'] as $index => $row) {
                $number = (string) ($row['no'] ?? 0);
                $baseSku = 'HPP-MENU-'.str_pad($number, 3, '0', STR_PAD_LEFT);
                $menuNumberSeen[$number] = ($menuNumberSeen[$number] ?? 0) + 1;
                $menuSkuByIndex[$index] = $menuNumberCounts[$number] > 1 && $menuNumberSeen[$number] > 1
                    ? $baseSku.'-'.str_pad((string) $menuNumberSeen[$number], 2, '0', STR_PAD_LEFT)
                    : $baseSku;
            }
            foreach ($source['menu'] as $index => $row) {
                if (! filled($row['nama'] ?? null)) {
                    continue;
                }
                $sku = $menuSkuByIndex[$index];
                $menuProduct = Product::withTrashed()->firstOrCreate(['sku' => $sku], [
                    'category_id' => $categoryProduct->id,
                    'unit_id' => $unit->id,
                    'title' => trim($row['nama']),
                    'buy_price' => round((float) ($row['total_cost'] ?? 0), 2),
                    'sell_price' => round((float) ($row['harga_jual'] ?? 0), 2),
                    'stock' => 0,
                    'is_active' => true,
                    'is_sellable' => true,
                    'track_stock' => false,
                    'is_semi_finished' => false,
                    'description' => 'Menu impor hpp.json; resep per unit harus ditinjau sebelum produksi. Ringkasan sumber: '.($row['sumber']['sheet'] ?? 'Menu Summary').' baris '.($row['sumber']['baris'] ?? '?').'.',
                ]);
                if ($menuProduct->trashed()) {
                    $menuProduct->restore();
                }
                // Menu packs are assembled at POS from their active recipe; the warehouse does not hold finished menu stock.
                $menuProduct->forceFill(['track_stock' => false, 'is_semi_finished' => false])->save();
            }

            $sourceRecipes = collect($source['resep'])->filter(fn ($row) => filled($row['nama'] ?? null))->keyBy(fn ($row) => mb_strtolower(trim($row['nama'])));
            $ingredientByName = Ingredient::query()->where('code', 'like', 'HPP-%')->orderBy('code')->get()->keyBy(fn (Ingredient $item) => mb_strtolower(trim($item->name)));
            $packagingByName = $ingredientByName;
            $componentCategory = Category::firstOrCreate(['slug' => 'hpp-semi-finished'], ['name' => 'Produk Setengah Jadi HPP', 'is_active' => true]);
            $componentProducts = [];
            $componentNames = collect($source['menu'])->flatMap(fn (array $menu) => collect($menu['rincian'] ?? [])->pluck('nama'))
                ->merge(['Dimsum ori topping wortel', 'Lumpia keju ori', 'Lumpia keju mentai'])->filter()->unique();
            foreach ($componentNames as $componentName) {
                $key = mb_strtolower(trim((string) $componentName));
                $recipeName = $key === 'bolognese sauce' ? 'saus bolognese' : $key;
                $sourceRecipe = $sourceRecipes->get($recipeName);
                if (! $sourceRecipe || empty($sourceRecipe['bahan'])) {
                    continue;
                }
                $sku = 'HPP-SF-'.strtoupper(substr(hash('sha256', $key), 0, 10));
                $product = Product::withTrashed()->firstOrCreate(['sku' => $sku], [
                    'category_id' => $componentCategory->id, 'unit_id' => $unit->id, 'title' => trim((string) ($sourceRecipe['nama'] ?? $componentName)),
                    'buy_price' => 0, 'sell_price' => 0, 'stock' => 0, 'is_active' => true,
                    'is_sellable' => false, 'is_semi_finished' => true, 'track_stock' => true,
                    'description' => 'Produk setengah jadi dari resep komponen hpp.json; stok bertambah melalui hasil produksi.',
                ]);
                if ($product->trashed()) {
                    $product->restore();
                }
                $product->forceFill(['is_sellable' => false, 'is_semi_finished' => true, 'track_stock' => true, 'is_active' => true])->save();
                $componentProducts[$key] = $product;
                if (RecipeVersion::query()->where('product_id', $product->id)->exists()) {
                    continue;
                }
                $yield = $this->sourceYield($sourceRecipe['keterangan'] ?? []);
                $rows = [];
                foreach ($sourceRecipe['bahan'] as $line) {
                    $name = mb_strtolower(trim((string) ($line['nama'] ?? '')));
                    $ingredient = $ingredientByName->get($name);
                    if (! $ingredient || ! is_numeric($line['qty'] ?? null) || ! is_numeric($line['harga_satuan'] ?? null)
                        || (filled($line['uom'] ?? null) && $this->normalizeUnit((string) $line['uom']) !== $this->normalizeUnit((string) $ingredient->unit?->symbol))) {
                        $rows = [];
                        break;
                    }
                    $quantity = round((float) $line['qty'] / $yield, 3);
                    if ($quantity <= 0) {
                        $rows = [];
                        break;
                    }
                    $rows[$ingredient->id] = ($rows[$ingredient->id] ?? 0) + $quantity;
                }
                if ($rows === []) {
                    $componentProducts[$key] = null;

                    continue;
                }
                $version = RecipeVersion::query()->create([
                    'product_id' => $product->id, 'unit_id' => $product->unit_id, 'version' => 1,
                    'yield_quantity' => 1, 'is_active' => true, 'starts_at' => now(),
                    'notes' => 'Resep per satu unit produk setengah jadi; kuantitas dinormalisasi dengan yield dari hpp.json.',
                ]);
                foreach ($rows as $ingredientId => $quantity) {
                    $ingredient = $ingredientByName->firstWhere('id', $ingredientId);
                    $version->items()->create([
                        'ingredient_id' => $ingredientId, 'unit_id' => $ingredient->unit_id,
                        'quantity' => number_format($quantity, 3, '.', ''), 'base_quantity' => number_format($quantity, 3, '.', ''), 'waste_percent' => 0,
                    ]);
                }
            }
            $importedRecipes = 0;
            $incompleteRecipes = 0;
            $incompleteDetails = [];
            foreach ($source['menu'] as $index => $menuRow) {
                $menuProduct = Product::withTrashed()->where('sku', $menuSkuByIndex[$index])->first();
                if (! $menuProduct) {
                    continue;
                }
                $existingActive = RecipeVersion::query()->where('product_id', $menuProduct->id)->where('is_active', true)->first();
                if ($existingActive && ! str_starts_with((string) $existingActive->notes, 'Impor hpp.json')) {
                    continue;
                }
                if ($existingActive?->productItems()->exists()) {
                    continue;
                }
                $bom = [];
                $bomProducts = [];
                $complete = true;
                $incompleteReason = '';
                foreach ($menuRow['rincian'] ?? [] as $component) {
                    $componentName = mb_strtolower(trim((string) ($component['nama'] ?? '')));
                    $recipeName = $componentName === 'bolognese sauce' ? 'saus bolognese' : $componentName;
                    $sourceRecipe = $sourceRecipes->get($recipeName);
                    $componentCount = $component['pcs'] ?? null;
                    $componentProduct = $componentProducts[$componentName] ?? null;
                    if (! $sourceRecipe || ! $componentProduct || ! is_numeric($componentCount) || (float) $componentCount <= 0 || empty($sourceRecipe['bahan'])) {
                        $complete = false;
                        $incompleteReason = "Komponen {$componentName} belum memiliki resep setengah jadi yang lengkap atau jumlah menu valid.";
                        break;
                    }
                    $bomProducts[$componentProduct->id] = ($bomProducts[$componentProduct->id] ?? 0) + (float) $componentCount;
                    $componentIncludesPackaging = false;
                    foreach ($sourceRecipe['bahan'] as $line) {
                        $materialName = trim((string) ($line['nama'] ?? ''));
                        $normalizedMaterialName = mb_strtolower($materialName);
                        if (str_contains($normalizedMaterialName, 'cup') || in_array($normalizedMaterialName, ['box pack dimsum', 'sumpit', 'plastik dimsum 24', 'pack chikuro', 'plastik chikuro'], true)) {
                            $componentIncludesPackaging = true;
                        }
                    }

                    $packCost = (float) ($component['hpp_pack'] ?? 0);
                    if ($packCost > 0 && ! $componentIncludesPackaging) {
                        $packParts = $this->packagingParts($packCost, $packagingByName);
                        if ($packParts === null) {
                            $complete = false;
                            $incompleteReason = "Rincian packaging Rp {$packCost} belum dapat direkonsiliasi ke bahan kemasan.";
                            break;
                        }
                        foreach ($packParts as $ingredientId => $quantity) {
                            $bom[$ingredientId] = ($bom[$ingredientId] ?? 0) + $quantity;
                        }
                    }
                }
                if (! $complete || $bomProducts === []) {
                    if ($existingActive) {
                        $existingActive->update(['is_active' => false, 'ends_at' => now()]);
                    }
                    $incompleteRecipes++;
                    $incompleteDetails[] = trim($menuRow['nama']).': '.($incompleteReason ?: 'BOM kosong.');

                    continue;
                }
                if ($existingActive) {
                    $existingActive->update(['is_active' => false, 'ends_at' => now()]);
                }
                $version = RecipeVersion::query()->create([
                    'product_id' => $menuProduct->id, 'unit_id' => $menuProduct->unit_id,
                    'version' => (int) RecipeVersion::query()->where('product_id', $menuProduct->id)->max('version') + 1,
                    'yield_quantity' => 1, 'is_active' => true, 'starts_at' => now(),
                    'notes' => 'Impor hpp.json - assembly per satu unit menu dari produk setengah jadi dan kemasan.',
                ]);
                foreach ($bom as $ingredientId => $quantity) {
                    $ingredient = $ingredientByName->firstWhere('id', $ingredientId);
                    $version->items()->create([
                        'ingredient_id' => $ingredientId, 'unit_id' => $ingredient->unit_id,
                        'quantity' => number_format($quantity, 3, '.', ''), 'base_quantity' => number_format($quantity, 3, '.', ''),
                        'waste_percent' => 0,
                    ]);
                }
                foreach ($bomProducts as $componentProductId => $quantity) {
                    $version->productItems()->create([
                        'product_id' => $componentProductId, 'unit_id' => $unit->id,
                        'quantity' => number_format($quantity, 3, '.', ''), 'waste_percent' => 0,
                    ]);
                }
                $importedRecipes++;
            }

            return ['recipes' => $importedRecipes, 'incomplete' => $incompleteRecipes, 'details' => $incompleteDetails];
        });

        $this->command?->info('Master bahan dan menu sumber HPP berhasil diimpor tanpa membuat saldo stok.');
        $this->command?->info("Resep menu lengkap yang dapat dipetakan: {$stats['recipes']}; menu yang perlu dilengkapi manual: {$stats['incomplete']}.");
        foreach ($stats['details'] as $detail) {
            $this->command?->warn($detail);
        }
        foreach ($source['kemasan'] as $packaging) {
            if (filled($packaging['nama'] ?? null) && ! is_numeric($packaging['harga_satuan'] ?? null)) {
                $this->command?->warn('Kemasan "'.trim($packaging['nama']).'" dilewati karena harga satuan belum tersedia pada sumber.');
            }
        }
        $this->command?->warn('Recipe lines with missing quantities and source units need review before production; this importer preserves costs and source notes but does not invent BOM quantities.');
    }

    private function sourceYield(array $notes): float
    {
        foreach ($notes as $note) {
            if (preg_match('/(?:^|\s)(\d+(?:[.,]\d+)?)\s*(?:pcs|cs)\s*\//i', (string) $note, $matches)) {
                return max(1, (float) str_replace(',', '.', $matches[1]));
            }
        }

        return 1;
    }

    private function normalizeUnit(string $unit): string
    {
        return match (mb_strtolower(trim($unit))) {
            'gr', 'gram', 'grams' => 'g',
            'lt', 'liter' => 'l',
            'pc' => 'pcs',
            default => mb_strtolower(trim($unit)),
        };
    }

    /** Source packaging rows are included only when their detailed rates reconcile to the stated component cost. */
    private function packagingParts(float $target, $ingredients): ?array
    {
        $groups = [
            ['names' => ['box pack dimsum', 'sumpit', 'plastik dimsum 24'], 'quantity' => 1],
            ['names' => ['cup'], 'quantity' => 1],
            ['names' => ['pack chikuro', 'plastik chikuro'], 'quantity' => 4],
        ];
        foreach ($groups as $group) {
            $parts = [];
            $total = 0.0;
            foreach ($group['names'] as $name) {
                $item = $ingredients->get($name);
                if (! $item) {
                    $parts = [];
                    break;
                }
                $parts[$item->id] = (float) $group['quantity'];
                $total += (float) $item->buy_price * (float) $group['quantity'];
            }
            if ($parts !== [] && abs($total - $target) <= 1.01) {
                return $parts;
            }
        }

        return null;
    }
}
