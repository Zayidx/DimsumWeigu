<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\ProductWarehouse;
use App\Support\FixedDecimal;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ReconcileInventory extends Command
{
    protected $signature = 'inventory:reconcile {--fix : Sinkronkan products.stock dari jumlah product_warehouses}';
    protected $description = 'Bandingkan stok agregat produk dengan saldo per gudang';

    public function handle(): int
    {
        $mismatches = 0;
        $fixed = 0;
        Product::query()->orderBy('id')->chunkById(200, function ($products) use (&$mismatches, &$fixed): void {
            foreach ($products as $product) {
                $sum = ProductWarehouse::query()->where('product_id', $product->id)->sum('stock');
                if (FixedDecimal::quantity((string) $sum) === FixedDecimal::quantity((string) $product->stock)) continue;
                $mismatches++;
                $this->line("#{$product->id} {$product->sku} aggregate={$product->stock} warehouses={$sum}");
                if ($this->option('fix')) {
                    DB::transaction(function () use ($product, $sum): void {
                        $locked = Product::query()->lockForUpdate()->findOrFail($product->id);
                        $current = ProductWarehouse::query()->where('product_id', $locked->id)->sum('stock');
                        $locked->forceFill(['stock' => $current])->save();
                    }, attempts: 3);
                    $fixed++;
                }
            }
        });
        $this->info("Selisih: {$mismatches}; diperbaiki: {$fixed}");
        return $mismatches > $fixed ? self::FAILURE : self::SUCCESS;
    }
}
