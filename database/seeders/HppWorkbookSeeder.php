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
        if (app()->environment('production')) {
            throw new RuntimeException('HppWorkbookSeeder hanya boleh dijalankan di local/testing.');
        }

        $path = base_path('hpp.json');
        if (! is_file($path)) {
            throw new RuntimeException('File hpp.json tidak ditemukan di root repository.');
        }
        $source = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
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

            $categoryProduct = Category::firstOrCreate(['slug' => 'menu-hpp-workbook'], ['name' => 'Menu HPP Workbook', 'is_active' => true]);
            $unit = Unit::query()->where('code', 'PCS')->firstOrFail();
            foreach ($source['menu'] as $row) {
                if (! filled($row['nama'] ?? null)) {
                    continue;
                }
                $sku = 'HPP-MENU-'.str_pad((string) ($row['no'] ?? 0), 3, '0', STR_PAD_LEFT);
                Product::withTrashed()->firstOrCreate(['sku' => $sku], [
                    'category_id' => $categoryProduct->id,
                    'unit_id' => $unit->id,
                    'title' => trim($row['nama']),
                    'buy_price' => round((float) ($row['total_cost'] ?? 0), 2),
                    'sell_price' => round((float) ($row['harga_jual'] ?? 0), 2),
                    'stock' => 0,
                    'is_active' => true,
                    'is_sellable' => true,
                    'track_stock' => true,
                    'description' => 'Menu impor hpp.json; resep per unit harus ditinjau sebelum produksi. Ringkasan sumber: '.($row['sumber']['sheet'] ?? 'Menu Summary').' baris '.($row['sumber']['baris'] ?? '?').'.',
                ]);
            }

            $sourceRecipes = collect($source['resep'])->filter(fn ($row) => filled($row['nama'] ?? null))->keyBy(fn ($row) => mb_strtolower(trim($row['nama'])));
            $ingredientByName = Ingredient::query()->where('code', 'like', 'HPP-%')->orderBy('code')->get()->keyBy(fn (Ingredient $item) => mb_strtolower(trim($item->name)));
            $packagingByName = $ingredientByName;
            $importedRecipes = 0;
            $incompleteRecipes = 0;
            foreach ($source['menu'] as $menuRow) {
                $menuProduct = Product::query()->where('sku', 'HPP-MENU-'.str_pad((string) ($menuRow['no'] ?? 0), 3, '0', STR_PAD_LEFT))->first();
                if (! $menuProduct || RecipeVersion::query()->where('product_id', $menuProduct->id)->exists()) {
                    continue;
                }
                $bom = [];
                $complete = true;
                foreach ($menuRow['rincian'] ?? [] as $component) {
                    $componentName = mb_strtolower(trim((string) ($component['nama'] ?? '')));
                    $recipeName = $componentName === 'bolognese sauce' ? 'saus bolognese' : $componentName;
                    $sourceRecipe = $sourceRecipes->get($recipeName);
                    $componentCount = $component['pcs'] ?? null;
                    if (! $sourceRecipe || ! is_numeric($componentCount) || (float) $componentCount <= 0 || empty($sourceRecipe['bahan'])) {
                        $complete = false;
                        break;
                    }
                    $yield = $this->sourceYield($sourceRecipe['keterangan'] ?? []);
                    $componentIncludesPackaging = false;
                    foreach ($sourceRecipe['bahan'] as $line) {
                        $materialName = trim((string) ($line['nama'] ?? ''));
                        $ingredient = $ingredientByName->get(mb_strtolower($materialName));
                        if (! $ingredient || ! is_numeric($line['qty'] ?? null) || ! is_numeric($line['harga_satuan'] ?? null)) {
                            $complete = false;
                            break 2;
                        }
                        $sourceUnit = trim((string) ($line['uom'] ?? ''));
                        $normalizedMaterialName = mb_strtolower($materialName);
                        if (str_contains($normalizedMaterialName, 'cup') || in_array($normalizedMaterialName, ['box pack dimsum', 'sumpit', 'plastik dimsum 24', 'pack chikuro', 'plastik chikuro'], true)) {
                            $componentIncludesPackaging = true;
                        }
                        if ($sourceUnit !== '' && $this->normalizeUnit($sourceUnit) !== $this->normalizeUnit((string) $ingredient->unit?->symbol)) {
                            $complete = false;
                            break 2;
                        }
                        $quantity = round(((float) $line['qty'] * (float) $componentCount) / $yield, 3);
                        if ($quantity <= 0) {
                            $complete = false;
                            break 2;
                        }
                        $bom[$ingredient->id] = ($bom[$ingredient->id] ?? 0) + $quantity;
                    }

                    $packCost = (float) ($component['hpp_pack'] ?? 0);
                    if ($packCost > 0 && ! $componentIncludesPackaging) {
                        $packParts = $this->packagingParts($packCost, $packagingByName);
                        if ($packParts === null) {
                            $complete = false;
                            break;
                        }
                        foreach ($packParts as $ingredientId => $quantity) {
                            $bom[$ingredientId] = ($bom[$ingredientId] ?? 0) + $quantity;
                        }
                    }
                }
                if (! $complete || $bom === []) {
                    $incompleteRecipes++;

                    continue;
                }
                $version = RecipeVersion::query()->create([
                    'product_id' => $menuProduct->id, 'unit_id' => $menuProduct->unit_id, 'version' => 1,
                    'yield_quantity' => 1, 'is_active' => true, 'starts_at' => now(),
                    'notes' => 'Impor hpp.json · BOM per 1 unit menu, diratakan dari rincian resep. Estimasi HPP memakai harga Master Bahan terkini; bandingkan dengan ringkasan sumber karena ongkos energi dan selisih formula dapat berbeda.',
                ]);
                foreach ($bom as $ingredientId => $quantity) {
                    $ingredient = $ingredientByName->firstWhere('id', $ingredientId);
                    $version->items()->create([
                        'ingredient_id' => $ingredientId, 'unit_id' => $ingredient->unit_id,
                        'quantity' => number_format($quantity, 3, '.', ''), 'base_quantity' => number_format($quantity, 3, '.', ''),
                        'waste_percent' => 0,
                    ]);
                }
                $importedRecipes++;
            }

            return ['recipes' => $importedRecipes, 'incomplete' => $incompleteRecipes];
        });

        $this->command?->info('Master bahan dan menu sumber HPP berhasil diimpor tanpa membuat saldo stok.');
        $this->command?->info("Resep menu lengkap yang dapat dipetakan: {$stats['recipes']}; menu yang perlu dilengkapi manual: {$stats['incomplete']}.");
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
            ['box pack dimsum', 'sumpit', 'plastik dimsum 24'],
            ['cup'],
        ];
        foreach ($groups as $names) {
            $parts = [];
            $total = 0.0;
            foreach ($names as $name) {
                $item = $ingredients->get($name);
                if (! $item) {
                    $parts = [];
                    break;
                }
                $parts[$item->id] = 1.0;
                $total += (float) $item->buy_price;
            }
            if ($parts !== [] && abs($total - $target) <= 1.01) {
                return $parts;
            }
        }

        return null;
    }
}
