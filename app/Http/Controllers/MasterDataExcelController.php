<?php

namespace App\Http\Controllers;

use App\Exports\MasterDataExcelExport;
use App\Imports\MasterDataExcelImport;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Ingredient;
use App\Models\IngredientCategory;
use App\Models\InventoryBalance;
use App\Models\InventoryLedger;
use App\Models\Product;
use App\Models\ProductWarehouse;
use App\Models\RecipeVersion;
use App\Models\Supplier;
use App\Models\TransactionDetail;
use App\Models\Unit;
use App\Models\Warehouse;
use App\Support\AuditLogger;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Shared\Date;

class MasterDataExcelController extends Controller
{
    private const DATASETS = [
        'products' => ['permission' => 'products-access', 'headings' => ['SKU', 'Nama', 'Barcode', 'Kategori Slug', 'Kode Satuan', 'Harga Beli', 'Harga Jual', 'Pajak Persen', 'Pajak Termasuk', 'Stok Minimum', 'Titik Pemesanan', 'Aktif', 'Dijual Langsung', 'Setengah Jadi', 'Jual Satuan Dasar', 'Lacak Stok', 'Lacak Batch', 'Deskripsi']],
        'ingredients' => ['permission' => 'ingredients-access', 'headings' => ['Kode', 'Nama', 'Kode Satuan', 'Kategori Bahan', 'Harga Beli', 'Aktif', 'Deskripsi']],
        'customers' => ['permission' => 'customers-access', 'headings' => ['Kode', 'Nama', 'Email', 'Telepon', 'Tanggal Lahir', 'Limit Kredit', 'Tier Member', 'Izin Pemasaran', 'Aktif', 'Catatan']],
        'suppliers' => ['permission' => 'suppliers-access', 'headings' => ['Kode', 'Nama', 'Email', 'Telepon', 'Nomor Pajak', 'Alamat', 'Kota', 'Aktif', 'Catatan']],
    ];

    public function export(Request $request, string $dataset)
    {
        $this->dataset($dataset, $request);
        $rows = $this->rows($dataset);

        return Excel::download(new MasterDataExcelExport(self::DATASETS[$dataset]['headings'], $rows, ucfirst($dataset)), $dataset.'-'.today()->format('Ymd').'.xlsx');
    }

    public function template(Request $request, string $dataset)
    {
        $this->dataset($dataset, $request);

        return Excel::download(new MasterDataExcelExport(self::DATASETS[$dataset]['headings'], [], 'Template'), 'template-'.$dataset.'.xlsx');
    }

    public function import(Request $request, string $dataset, AuditLogger $audit): RedirectResponse
    {
        $this->dataset($dataset, $request);
        $request->validate(['file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:10240']]);
        $import = new MasterDataExcelImport;
        try {
            Excel::import($import, $request->file('file'));
        } catch (\Throwable) {
            throw ValidationException::withMessages(['file' => __('File Excel tidak dapat dibaca. Unduh template terbaru untuk modul ini.')]);
        }
        $rows = $import->rows ?? collect();
        if ($rows->isEmpty() || $rows->count() > 5000) {
            throw ValidationException::withMessages(['file' => __('File harus memiliki 1 sampai 5.000 baris data.')]);
        }
        $codeColumn = $dataset === 'products' ? 'sku' : 'kode';
        $codes = [];
        foreach ($rows as $index => $row) {
            $code = strtoupper(trim((string) ($row[$codeColumn] ?? '')));
            if ($code === '' || isset($codes[$code])) {
                throw ValidationException::withMessages(['file' => __('Kode wajib diisi dan tidak boleh ganda pada baris :row.', ['row' => $index + 2])]);
            }
            $codes[$code] = $index + 2;
            $this->validateRow($dataset, $row->all(), $index + 2);
        }

        DB::transaction(function () use ($dataset, $rows, $audit): void {
            foreach ($rows as $index => $row) {
                $this->{'import'.ucfirst($dataset)}($row->all(), $index + 2, $audit);
            }
        }, attempts: 3);

        return back()->with('status', __('Data :dataset berhasil diimpor. Stok dan histori transaksi tidak diubah.', ['dataset' => $dataset]));
    }

    private function dataset(string $dataset, Request $request): void
    {
        abort_unless(isset(self::DATASETS[$dataset]), 404);
        abort_unless($request->user()->can(self::DATASETS[$dataset]['permission']), 403);
    }

    private function rows(string $dataset): array
    {
        return match ($dataset) {
            'products' => Product::query()->with(['category:id,slug', 'unit:id,code'])->orderBy('sku')->get()->map(fn (Product $p) => [$p->sku, $p->title, $p->barcode, $p->category?->slug, $p->unit?->code, $p->buy_price, $p->sell_price, $p->tax_rate, (int) $p->tax_inclusive, $p->min_stock, $p->reorder_point, (int) $p->is_active, (int) $p->is_sellable, (int) $p->is_semi_finished, (int) $p->allow_single_unit_sale, (int) $p->track_stock, (int) $p->track_batches, $p->description])->all(),
            'ingredients' => Ingredient::query()->with(['unit:id,code', 'category:id,name'])->orderBy('code')->get()->map(fn (Ingredient $i) => [$i->code, $i->name, $i->unit?->code, $i->category?->name, $i->buy_price, (int) $i->is_active, $i->description])->all(),
            'customers' => Customer::query()->orderBy('code')->get()->map(fn (Customer $c) => [$c->code, $c->name, $c->email, $c->phone, $c->birth_date?->format('Y-m-d'), $c->credit_limit, $c->member_tier, (int) $c->marketing_consent, (int) $c->is_active, $c->notes])->all(),
            'suppliers' => Supplier::query()->orderBy('code')->get()->map(fn (Supplier $s) => [$s->code, $s->name, $s->email, $s->phone, $s->tax_number, $s->address, $s->city, (int) $s->is_active, $s->notes])->all(),
        };
    }

    private function validateRow(string $dataset, array $row, int $line): void
    {
        $rules = match ($dataset) {
            'products' => ['sku' => ['required', 'string', 'max:64'], 'nama' => ['required', 'string', 'max:160'], 'barcode' => ['nullable', 'string', 'max:96'], 'kode_satuan' => ['required', 'exists:units,code'], 'harga_beli' => ['required', 'numeric', 'min:0'], 'harga_jual' => ['required', 'numeric', 'min:0']],
            'ingredients' => ['kode' => ['required', 'string', 'max:40'], 'nama' => ['required', 'string', 'max:160'], 'kode_satuan' => ['required', 'exists:units,code'], 'harga_beli' => ['required', 'numeric', 'min:0']],
            'customers' => ['kode' => ['required', 'string', 'max:40'], 'nama' => ['required', 'string', 'max:160'], 'email' => ['nullable', 'email', 'max:255'], 'telepon' => ['nullable', 'string', 'max:32'], 'limit_kredit' => ['nullable', 'numeric', 'min:0', 'max:9999999999999.99'], 'tier_member' => ['nullable', 'in:regular,silver,gold,platinum'], 'tanggal_lahir' => ['nullable']],
            'suppliers' => ['kode' => ['required', 'string', 'max:40'], 'nama' => ['required', 'string', 'max:160'], 'email' => ['nullable', 'email', 'max:255'], 'telepon' => ['nullable', 'string', 'max:32']],
        };
        $validator = Validator::make($row, $rules);
        if ($validator->fails()) {
            throw ValidationException::withMessages(['file' => __('Data tidak valid pada baris :row: :errors', ['row' => $line, 'errors' => $validator->errors()->first()])]);
        }
        if ($dataset === 'products') {
            $category = trim((string) ($row['kategori_slug'] ?? ''));
            if ($category !== '' && ! Category::query()->where('slug', $category)->exists()) {
                throw ValidationException::withMessages(['file' => __('Kategori tidak dikenal pada baris :row.', ['row' => $line])]);
            }
            Validator::make($row, [
                'pajak_persen' => ['nullable', 'numeric', 'min:0', 'max:100'],
                'harga_jual' => ['required', 'numeric', 'min:0', 'max:9999999999999.99'],
                'harga_beli' => ['required', 'numeric', 'min:0', 'max:9999999999999.99'],
                'stok_minimum' => ['nullable', 'numeric', 'min:0'], 'titik_pemesanan' => ['nullable', 'numeric', 'min:0'],
            ])->validate();
        }
        if ($dataset === 'ingredients') {
            Validator::make($row, ['harga_beli' => ['required', 'numeric', 'min:0', 'max:9999999999.99999999']])->validate();
            $category = trim((string) ($row['kategori_bahan'] ?? ''));
            if ($category !== '' && ! IngredientCategory::query()->where('name', $category)->exists()) {
                throw ValidationException::withMessages(['file' => __('Kategori bahan tidak dikenal pada baris :row.', ['row' => $line])]);
            }
        }
        if ($dataset === 'customers' && filled($row['tanggal_lahir'] ?? null)) {
            $date = $this->dateValue($row['tanggal_lahir'], $line);
            if ($date > today()->toDateString()) {
                throw ValidationException::withMessages(['file' => __('Tanggal lahir tidak boleh mendatang pada baris :row.', ['row' => $line])]);
            }
        }
    }

    private function importProducts(array $row, int $line, AuditLogger $audit): void
    {
        $sku = strtoupper(trim((string) $row['sku']));
        $product = Product::query()->where('sku', $sku)->lockForUpdate()->first();
        $creating = ! $product;
        abort_unless(auth()->user()->can($creating ? 'products-create' : 'products-edit'), 403);
        $unit = Unit::query()->where('code', trim((string) $row['kode_satuan']))->firstOrFail();
        if ($product && (int) $product->unit_id !== $unit->id && (RecipeVersion::query()->where('product_id', $product->id)->exists() || TransactionDetail::query()->where('product_id', $product->id)->exists() || InventoryLedger::query()->where('product_id', $product->id)->exists() || ProductWarehouse::query()->where('product_id', $product->id)->where('stock', '<>', 0)->exists())) {
            throw ValidationException::withMessages(['file' => __('Satuan produk :sku tidak dapat diubah karena sudah memiliki resep atau histori.', ['sku' => $sku])]);
        }
        $barcode = trim((string) ($row['barcode'] ?? '')) ?: null;
        if ($barcode && Product::query()->where('barcode', $barcode)->where('id', '!=', $product?->id)->exists()) {
            throw ValidationException::withMessages(['file' => __('Barcode :barcode sudah dipakai produk lain.', ['barcode' => $barcode])]);
        }
        $before = $product?->only(['sku', 'title', 'sell_price', 'buy_price', 'is_active']);
        $product ??= new Product;
        $product->fill([
            'sku' => $sku, 'title' => trim((string) $row['nama']), 'barcode' => $barcode,
            'category_id' => Category::query()->where('slug', trim((string) ($row['kategori_slug'] ?? '')))->value('id'), 'unit_id' => $unit->id,
            'buy_price' => $row['harga_beli'], 'sell_price' => $row['harga_jual'], 'tax_rate' => $row['pajak_persen'] ?? null,
            'tax_inclusive' => $this->boolValue($row['pajak_termasuk'] ?? null, (bool) ($product?->tax_inclusive ?? false)), 'min_stock' => $this->valueOr($row, 'stok_minimum', $product?->min_stock ?? 0),
            'reorder_point' => $this->valueOr($row, 'titik_pemesanan', $product?->reorder_point ?? 0), 'is_active' => $this->boolValue($row['aktif'] ?? null, (bool) ($product?->is_active ?? true)),
            'is_sellable' => $this->boolValue($row['dijual_langsung'] ?? null, (bool) ($product?->is_sellable ?? true)), 'is_semi_finished' => $this->boolValue($row['setengah_jadi'] ?? null, (bool) ($product?->is_semi_finished ?? false)),
            'allow_single_unit_sale' => $this->boolValue($row['jual_satuan_dasar'] ?? null, (bool) ($product?->allow_single_unit_sale ?? false)), 'track_stock' => $this->boolValue($row['lacak_stok'] ?? null, (bool) ($product?->track_stock ?? true)),
            'track_batches' => $this->boolValue($row['lacak_batch'] ?? null, (bool) ($product?->track_batches ?? false)), 'description' => $row['deskripsi'] ?? null,
        ]);
        if ($product->is_semi_finished && ($product->is_sellable || ! $product->track_stock || $product->allow_single_unit_sale)) {
            throw ValidationException::withMessages(['file' => __('Flag produk setengah jadi tidak sesuai pada baris :row.', ['row' => $line])]);
        }
        $product->save();
        if ($creating) {
            foreach (Warehouse::query()->where('is_active', true)->get(['id']) as $warehouse) {
                ProductWarehouse::query()->create(['product_id' => $product->id, 'warehouse_id' => $warehouse->id, 'stock' => 0, 'reserved_stock' => 0, 'min_stock' => $product->min_stock, 'reorder_point' => $product->reorder_point]);
                InventoryBalance::query()->create(['product_id' => $product->id, 'warehouse_id' => $warehouse->id, 'calculated_quantity' => 0]);
            }
        }
        $audit->record($creating ? 'product.created' : 'product.updated', $product, $before, $product->only(['sku', 'title', 'sell_price', 'buy_price', 'is_active']));
    }

    private function importIngredients(array $row, int $line, AuditLogger $audit): void
    {
        $code = strtoupper(trim((string) $row['kode']));
        $ingredient = Ingredient::withTrashed()->where('code', $code)->lockForUpdate()->first();
        $creating = ! $ingredient;
        abort_unless(auth()->user()->can($creating ? 'ingredients-create' : 'ingredients-edit'), 403);
        $unit = Unit::query()->where('code', trim((string) $row['kode_satuan']))->firstOrFail();
        if ($ingredient && (int) $ingredient->unit_id !== $unit->id && $ingredient->recipeItems()->exists()) {
            throw ValidationException::withMessages(['file' => __('Satuan bahan :code tidak dapat diubah karena sudah dipakai resep.', ['code' => $code])]);
        }
        if ($ingredient?->trashed()) {
            $ingredient->restore();
        }
        $before = $ingredient?->only(['code', 'name', 'buy_price', 'is_active']);
        $ingredient ??= new Ingredient;
        $ingredient->fill([
            'code' => $code, 'name' => trim((string) $row['nama']), 'unit_id' => $unit->id,
            'ingredient_category_id' => IngredientCategory::query()->where('name', trim((string) ($row['kategori_bahan'] ?? '')))->value('id'),
            'buy_price' => $row['harga_beli'], 'description' => $row['deskripsi'] ?? null,
            'is_active' => $this->boolValue($row['aktif'] ?? null, (bool) ($ingredient?->is_active ?? true)),
        ])->save();
        $audit->record($creating ? 'ingredient.created' : 'ingredient.updated', $ingredient, $before, $ingredient->only(['code', 'name', 'buy_price', 'is_active']));
    }

    private function importCustomers(array $row, int $line, AuditLogger $audit): void
    {
        $this->importSimple(Customer::class, 'customers', $row, $line, $audit, [
            'code' => strtoupper(trim((string) $row['kode'])), 'name' => trim((string) $row['nama']), 'email' => $row['email'] ?? null,
            'phone' => $row['telepon'] ?? null, 'birth_date' => $this->dateValue($row['tanggal_lahir'] ?? null, $line),
            'credit_limit' => $row['limit_kredit'] ?? 0, 'member_tier' => ($row['tier_member'] ?? '') ?: 'regular',
            'marketing_consent' => $this->boolValue($row['izin_pemasaran'] ?? null, (bool) (Customer::query()->where('code', strtoupper(trim((string) $row['kode'])))->value('marketing_consent') ?? false)), 'is_active' => $this->boolValue($row['aktif'] ?? null, (bool) (Customer::query()->where('code', strtoupper(trim((string) $row['kode'])))->value('is_active') ?? true)), 'notes' => $row['catatan'] ?? null,
        ], 'customers-create', 'customers-edit', ['code', 'name', 'email', 'phone', 'credit_limit', 'member_tier', 'marketing_consent', 'is_active']);
    }

    private function importSuppliers(array $row, int $line, AuditLogger $audit): void
    {
        $this->importSimple(Supplier::class, 'suppliers', $row, $line, $audit, [
            'code' => strtoupper(trim((string) $row['kode'])), 'name' => trim((string) $row['nama']), 'email' => $row['email'] ?? null,
            'phone' => $row['telepon'] ?? null, 'tax_number' => $row['nomor_pajak'] ?? null, 'address' => $row['alamat'] ?? null,
            'city' => $row['kota'] ?? null, 'is_active' => $this->boolValue($row['aktif'] ?? null, (bool) (Supplier::query()->where('code', strtoupper(trim((string) $row['kode'])))->value('is_active') ?? true)), 'notes' => $row['catatan'] ?? null,
        ], 'suppliers-create', 'suppliers-update', ['code', 'name', 'email', 'phone', 'tax_number', 'is_active']);
    }

    private function importSimple(string $class, string $type, array $row, int $line, AuditLogger $audit, array $data, string $createPermission, string $editPermission, array $auditFields): void
    {
        $model = $class::query()->where('code', $data['code'])->lockForUpdate()->first();
        $creating = ! $model;
        abort_unless(auth()->user()->can($creating ? $createPermission : $editPermission), 403);
        $before = $model?->only($auditFields);
        $model ??= new $class;
        $model->fill($data)->save();
        $audit->record(rtrim($type, 's').($creating ? '.created' : '.updated'), $model, $before, $model->only($auditFields));
    }

    private function boolValue(mixed $value, bool $default): bool
    {
        if ($value === null || trim((string) $value) === '') {
            return $default;
        }
        $normalized = mb_strtolower(trim((string) $value));
        if (in_array($normalized, ['1', 'true', 'yes', 'ya', 'aktif'], true)) {
            return true;
        }
        if (in_array($normalized, ['0', 'false', 'no', 'tidak', 'nonaktif'], true)) {
            return false;
        }

        throw ValidationException::withMessages(['file' => __('Nilai boolean harus 1/0, Ya/Tidak, atau Aktif/Nonaktif.')]);
    }

    private function valueOr(array $row, string $key, mixed $default): mixed
    {
        return ! array_key_exists($key, $row) || $row[$key] === null || trim((string) $row[$key]) === '' ? $default : $row[$key];
    }

    private function dateValue(mixed $value, int $line): ?string
    {
        if (blank($value)) {
            return null;
        }
        try {
            return is_numeric($value) ? Date::excelToDateTimeObject((float) $value)->format('Y-m-d') : Carbon::parse((string) $value)->toDateString();
        } catch (\Throwable) {
            throw ValidationException::withMessages(['file' => __('Tanggal tidak valid pada baris :row.', ['row' => $line])]);
        }
    }
}
