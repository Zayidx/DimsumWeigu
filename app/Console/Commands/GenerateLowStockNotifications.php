<?php

namespace App\Console\Commands;

use App\Models\OperationalNotification;
use App\Models\ProductWarehouse;
use Illuminate\Console\Command;

class GenerateLowStockNotifications extends Command
{
    protected $signature = 'notifications:low-stock';
    protected $description = 'Buat notifikasi stok rendah per outlet secara idempoten';

    public function handle(): int
    {
        $created = 0;
        ProductWarehouse::query()->with(['product:id,title,sku,is_active', 'warehouse:id,name,outlet_id,is_active'])
            ->whereColumn('stock', '<=', 'reorder_point')->whereHas('product', fn ($query) => $query->where('is_active', true))
            ->whereHas('warehouse', fn ($query) => $query->where('is_active', true)->whereNotNull('outlet_id'))
            ->orderBy('id')->chunkById(200, function ($balances) use (&$created): void {
                foreach ($balances as $balance) {
                    $outletId = $balance->warehouse->outlet_id;
                    $exists = OperationalNotification::query()->where('outlet_id', $outletId)->where('type', 'stock-low')
                        ->where('reference_type', 'product_warehouse')->where('reference_id', $balance->id)->whereDate('created_at', today())
                        ->exists();
                    if ($exists) continue;
                    OperationalNotification::query()->create([
                        'outlet_id' => $outletId, 'type' => 'stock-low', 'title' => __('Stok rendah: :product', ['product' => $balance->product->title]),
                        'body' => __(':product (:sku) tersisa :stock di :warehouse; batas pesan ulang :reorder.', ['product' => $balance->product->title, 'sku' => $balance->product->sku, 'stock' => $balance->stock, 'warehouse' => $balance->warehouse->name, 'reorder' => $balance->reorder_point]),
                        'reference_type' => 'product_warehouse', 'reference_id' => $balance->id,
                    ]);
                    $created++;
                }
            });
        $this->info("Notifikasi dibuat: {$created}");
        return self::SUCCESS;
    }
}
