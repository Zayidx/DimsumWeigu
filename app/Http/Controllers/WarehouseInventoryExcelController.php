<?php

namespace App\Http\Controllers;

use App\Exports\WarehouseOpeningStockTemplateExport;
use App\Exports\WarehouseStockExport;
use App\Imports\WarehouseOpeningStockImport;
use App\Models\InventoryLedger;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\ProductWarehouse;
use App\Models\Warehouse;
use App\Services\Inventory\StockMutationService;
use App\Support\OutletAccess;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

class WarehouseInventoryExcelController extends Controller
{
    public function export(Request $request)
    {
        abort_unless($request->user()->can('inventory-access'), 403);
        $warehouse = $this->warehouseForUser((int) $request->query('warehouse_id'));

        return Excel::download(new WarehouseStockExport($warehouse), 'stok-'.$warehouse->code.'-'.today()->format('Ymd').'.xlsx');
    }

    public function template(Request $request)
    {
        abort_unless($request->user()->can('inventory-opening'), 403);
        $warehouse = $this->warehouseForUser((int) $request->query('warehouse_id'));
        abort_unless($warehouse->type === 'central' && $warehouse->outlet_id === null, 403, __('Stok awal hanya dapat diimpor ke Gudang Bersama.'));

        return Excel::download(new WarehouseOpeningStockTemplateExport, 'template-stok-awal-'.$warehouse->code.'.xlsx');
    }

    public function import(Request $request, StockMutationService $stock): RedirectResponse
    {
        abort_unless($request->user()->can('inventory-opening'), 403);
        $validated = $request->validate([
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'file' => ['required', 'file', 'mimes:xlsx', 'max:10240'],
        ]);
        $warehouse = $this->warehouseForUser((int) $validated['warehouse_id']);
        abort_unless($warehouse->type === 'central' && $warehouse->outlet_id === null, 403, __('Stok awal hanya dapat diimpor ke Gudang Bersama.'));
        $import = new WarehouseOpeningStockImport;
        try {
            Excel::import($import, $request->file('file'));
        } catch (\Throwable) {
            throw ValidationException::withMessages(['file' => __('File Excel tidak dapat dibaca. Unduh dan gunakan template warehouse terbaru.')]);
        }
        $rows = $import->rows ?? collect();
        if ($rows->isEmpty() || $rows->count() > 2000) {
            throw ValidationException::withMessages(['file' => __('File harus berisi 1 sampai 2.000 baris data stok.')]);
        }

        $input = [];
        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2;
            $sku = strtoupper(trim((string) ($row['sku'] ?? '')));
            $quantity = trim((string) ($row['stok_awal'] ?? ''));
            $reason = trim((string) ($row['alasan'] ?? ''));
            $batchNumber = trim((string) ($row['nomor_batch'] ?? ''));
            $expiryValue = $row['tanggal_kedaluwarsa'] ?? null;
            if ($sku === '' && $quantity === '' && $reason === '' && $batchNumber === '' && blank($expiryValue)) {
                continue;
            }
            if ($sku === '' || $quantity === '' || ! is_numeric($quantity) || (float) $quantity < 0 || (float) $quantity > 999999999999.999 || preg_match('/^\d+(?:\.\d{1,3})?$/', $quantity) !== 1) {
                throw ValidationException::withMessages(['file' => __('Baris :row harus berisi SKU dan stok awal numerik dengan maksimal 3 angka desimal.', ['row' => $rowNumber])]);
            }
            if (mb_strlen($reason) < 3 || mb_strlen($reason) > 500) {
                throw ValidationException::withMessages(['file' => __('Alasan wajib diisi (3–500 karakter) pada baris :row.', ['row' => $rowNumber])]);
            }
            if (isset($input[$sku])) {
                throw ValidationException::withMessages(['file' => __('SKU :sku muncul lebih dari sekali.', ['sku' => $sku])]);
            }
            $expiresAt = $this->parseExcelDate($expiryValue, $rowNumber);
            if ($expiresAt && $expiresAt < today()->toDateString()) {
                throw ValidationException::withMessages(['file' => __('Tanggal kedaluwarsa tidak boleh lampau pada baris :row.', ['row' => $rowNumber])]);
            }
            $input[$sku] = [
                'quantity' => $quantity, 'reason' => $reason, 'batch_number' => $batchNumber,
                'expires_at' => $expiresAt, 'row' => $rowNumber,
            ];
        }
        if ($input === []) {
            throw ValidationException::withMessages(['file' => __('Tidak ada baris stok yang dapat diimpor.')]);
        }

        $products = Product::query()->whereIn('sku', array_keys($input))->where('is_active', true)->where('track_stock', true)->where('is_semi_finished', true)->get()->keyBy('sku');
        if ($products->count() !== count($input)) {
            $missing = array_diff(array_keys($input), $products->keys()->all());
            throw ValidationException::withMessages(['file' => __('SKU produk tidak dikenal, nonaktif, bukan produk setengah jadi, atau tidak melacak stok: :skus.', ['skus' => implode(', ', $missing)])]);
        }
        foreach ($input as $sku => $line) {
            if ($products->get($sku)->track_batches && $line['batch_number'] === '') {
                throw ValidationException::withMessages(['file' => __('Nomor batch wajib untuk produk :sku pada baris :row.', ['sku' => $sku, 'row' => $line['row']])]);
            }
        }

        DB::transaction(function () use ($input, $products, $warehouse, $stock): void {
            foreach ($input as $sku => $line) {
                $product = Product::query()->lockForUpdate()->findOrFail($products->get($sku)->id);
                $balance = ProductWarehouse::query()->where('product_id', $product->id)->where('warehouse_id', $warehouse->id)->lockForUpdate()->first();
                if (($balance && ((float) $balance->stock !== 0.0 || (float) $balance->reserved_stock !== 0.0))
                    || InventoryLedger::query()->where('product_id', $product->id)->where('warehouse_id', $warehouse->id)->exists()) {
                    throw ValidationException::withMessages(['file' => __('Produk :sku memiliki histori stok. Impor stok awal hanya berlaku untuk produk yang belum pernah dimutasi pada gudang ini.', ['sku' => $sku])]);
                }
                if ((float) $line['quantity'] === 0.0) {
                    continue;
                }
                $batch = null;
                if ($product->track_batches) {
                    $batch = ProductBatch::query()->firstOrCreate([
                        'product_id' => $product->id, 'warehouse_id' => $warehouse->id, 'batch_number' => $line['batch_number'],
                    ], ['expires_at' => $line['expires_at'], 'quantity_received' => 0, 'quantity_remaining' => 0, 'status' => 'active']);
                    if ($batch->status === 'expired' || $batch->expires_at?->lt(today())) {
                        throw ValidationException::withMessages(['file' => __('Batch :batch sudah kedaluwarsa.', ['batch' => $line['batch_number']])]);
                    }
                }
                $stock->mutate($product->id, $warehouse->id, (string) $line['quantity'], 'opening', (string) Str::uuid(), $line['reason'], $batch?->id, 'excel_opening_stock');
                if ($batch) {
                    $batch->quantity_received = $line['quantity'];
                    $batch->save();
                }
            }
        }, attempts: 3);

        return redirect()->route('inventory.index', ['warehouse' => $warehouse->id])->with('status', __('Stok awal berhasil diimpor ke :warehouse.', ['warehouse' => $warehouse->name]));
    }

    private function parseExcelDate(mixed $value, int $row): ?string
    {
        if (blank($value)) {
            return null;
        }
        try {
            return is_numeric($value)
                ? ExcelDate::excelToDateTimeObject((float) $value)->format('Y-m-d')
                : Carbon::parse((string) $value)->toDateString();
        } catch (\Throwable) {
            throw ValidationException::withMessages(['file' => __('Tanggal kedaluwarsa tidak valid pada baris :row.', ['row' => $row])]);
        }
    }

    private function warehouseForUser(int $warehouseId): Warehouse
    {
        $warehouse = Warehouse::query()->whereKey($warehouseId)->where('is_active', true)->firstOrFail();
        $outletId = (int) session('active_outlet_id');
        if ($warehouse->outlet_id !== $outletId) {
            abort_unless($warehouse->outlet_id === null && (auth()->user()->hasRole('super-admin') || auth()->user()->can('warehouses-access')), 404);
        }
        if ($warehouse->outlet_id) {
            app(OutletAccess::class)->require(auth()->user(), $warehouse->outlet_id);
        }

        return $warehouse;
    }
}
