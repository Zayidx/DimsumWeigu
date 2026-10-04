<?php

use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

// Read-only business report. Never select credentials, password hashes or tokens.
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$db = DB::connection();
if ($db->getDriverName() === 'mysql') {
    $db->statement('SET TRANSACTION READ ONLY');
}
$db->beginTransaction();
try {
    $lines = [
        '# Pemindahan seeder Dimsum Weigu', '',
        'Sumber: `seedertamplate/seeders` dan repository `C:\\Users\\zayidx\\Documents\\point-of-sales`. Semua 15 file template cocok dengan file seeder repository sumber pada saat audit.', '',
        'Menu, biaya, resep dan stok dari TestDataSeeder merupakan **simulasi lokal/uji**, bukan angka produksi atau histori transaksi aktual. Tidak ada password, hash, token atau kredensial sumber yang dipindahkan.', '',
        '## Menjalankan', '', '```powershell', 'php artisan migrate --force', 'php artisan db:seed --force', 'php tools/source-seeder-report.php', '```', '',
        'DatabaseSeeder memanggil ApplicationPermissionSeeder, PermissionSeeder, RoleSeeder, DimsumWeiguSeeder. UserSeeder dan TestDataSeeder otomatis hanya pada APP_ENV=local; lingkungan testing dapat memanggilnya eksplisit. TestDataSeeder menolak production. Gunakan database baru proyek ini; jangan menjalankan perintah tersebut di repository sumber. Migrasi normal mempertahankan data; migrasi awal dapat melanjutkan DDL MySQL yang terputus hanya atas tabel kosong.', '',
        '## Pemetaan dan pengecualian', '',
        '| Sumber | Implementasi baru / perbedaan |', '|---|---|',
        '| DimsumWeiguSeeder | Profil, 4 outlet, 5 gudang, kategori, satuan dan konversi; firstOrCreate mempertahankan penyesuaian existing |',
        '| TestDataSeeder | 13 menu, 28 product_units, 32 versi resep, 79 item resep, 13 bahan dan saldo awal PUSAT |',
        '| UserSeeder + OutletCashierSeeder | Digabung dalam UserSeeder: 8 akun; akun existing tidak diubah |',
        '| PermissionSeeder + RoleSeeder | Permission sumber dipertahankan; alias action baru ditambahkan; role existing tidak di-reset; manager tetap baca saja |',
        '| PaymentSettingSeeder | Provider global Midtrans/Xendit sandbox nonaktif tanpa kredensial; default_gateway=cash |',
        '| DineInSettingsSeeder | Pengaturan dine-in/self-order aktif, online payment nonaktif |',
        '| DemoSeeder, DemoOutletSeeder | Tidak dipanggil: akun/outlet demonstrasi tidak menjadi data bisnis Weigu |',
        '| FeatureDemoSeeder, FeatureCoverageSeeder, OperationalCoreSeeder, SampleDataSeeder | Tidak dipindahkan/dipanggil: simulasi histori, factory/sample, penghapusan/reset dan dependensi workflow lama memerlukan adaptasi terpisah |',
        '| unit.code / conversion_factor | Kode ditambahkan; konversi ke unit_conversions.factor. PAK dan PKT tetap berbeda walaupun sama-sama bersimbol paket |',
        '| ingredient.base_unit_id / default_unit_cost | ingredients.unit_id / buy_price; description tetap tersimpan |',
        '| recipe.version_number | recipe_versions.version; yield_quantity, notes, created_by, item unit_id/base_quantity ditambahkan |',
        '| recipe.unit_id null pada resep dasar | Diisi satuan dasar menu karena schema baru mewajibkan unit |',
        '| product_units.is_base / buy_price / sku_suffix | Ditambahkan; is_default mengikuti is_base; base unit juga disimpan pada products.unit_id |',
        '| Gudang PUSAT type main + outlet PUSAT | type central, outlet_id null, bukan lokasi penjualan. Finance/warehouse terhubung ke empat cabang; akses PUSAT memakai warehouses-access melalui policy gudang baru, tanpa pivot user_warehouse |',
        '| Kategori pengeluaran global | Enam kategori direplikasi per outlet, total 24 |',
        '| Metode CASH, QRIS-1, GOFOOD | cash, qris, gofood mengikuti kode aplikasi baru; QRIS/GoFood membutuhkan konfirmasi. Gateway tetap nonaktif |',
        '| Alias kategori bahan lama / bahan obsolete | Tidak menghapus atau memindahkan referensi existing secara otomatis |',
        '| Stok awal | Hanya bahan di PUSAT; aggregate sama dengan saldo gudang; ledger idempotent. Saldo yang sudah mempunyai stok/ledger dilewati. Tidak membuat stok produk/cabang |',
        '| Histori transaksi / runtime | Tidak disalin; cache, session, jobs dan log sementara tidak disertakan |', '',
        '**Batas akses:** permission sumber untuk cash-handovers-confirm/cash-pickups-confirm, outlet-stock-returns, recipes/import/export dan action lain yang belum mempunyai padanan workflow tetap tercatat. Alias tidak memberikan hak cash-management-create kepada warehouse, ataupun hak menulis sales return kepada manager. Paritas seluruh modul lama belum tercapai.', '',
        '## Jumlah data', '', '| Tabel | Jumlah |', '|---|---:|',
    ];
    foreach (['outlets', 'warehouses', 'categories', 'units', 'unit_conversions', 'products', 'product_units', 'ingredient_categories', 'ingredients', 'recipe_versions', 'recipe_items', 'ingredient_warehouses', 'ingredient_inventory_ledgers', 'users', 'user_outlets', 'roles', 'permissions', 'expense_categories', 'payment_methods', 'payment_settings', 'transactions'] as $table) {
        $lines[] = '| '.$table.' | '.$db->table($table)->count().' |';
    }
    $lines = array_merge($lines, ['', '## Menu dan harga jual', '', 'Harga rupiah; berlaku global tanpa periode promo/outlet khusus pada TestDataSeeder sumber.', '', '| SKU | Menu | Satuan dasar / harga | Variasi satuan, faktor, harga |', '|---|---|---|---|']);
    foreach ($db->table('products')->join('units', 'units.id', '=', 'products.unit_id')->select('products.id', 'products.sku', 'products.title', 'products.sell_price', 'units.code')->orderBy('products.id')->get() as $product) {
        $variants = $db->table('product_units')->join('units', 'units.id', '=', 'product_units.unit_id')->where('product_id', $product->id)->where('is_base', false)->select('units.code', 'conversion_factor', 'sell_price')->orderBy('product_units.id')->get()->map(fn ($unit) => $unit->code.' (x'.(float) $unit->conversion_factor.') Rp'.number_format((float) $unit->sell_price, 0, ',', '.'))->implode('; ');
        $lines[] = '| '.$product->sku.' | '.$product->title.' | '.$product->code.' / Rp'.number_format((float) $product->sell_price, 0, ',', '.').' | '.($variants ?: '—').' |';
    }
    $lines = array_merge($lines, ['', '## Resep/BOM', '', '**Resep dasar v1 dan profil kemasan v2–v4 merupakan versi terpisah seperti sumber. Profil kemasan bukan resep produksi lengkap dan tidak digabung otomatis dengan bahan resep dasar. Dimsum Mix mempunyai resep kemasan P4, tetapi sumber tidak menyediakan harga jual P4; harga tidak dibuat.**', '', '| Menu | Versi | Hasil / satuan | Pemakaian | Catatan sumber |', '|---|---:|---|---|---|']);
    foreach ($db->table('recipe_versions')->join('products', 'products.id', '=', 'recipe_versions.product_id')->join('units', 'units.id', '=', 'recipe_versions.unit_id')->select('recipe_versions.id', 'products.sku', 'version', 'yield_quantity', 'units.code', 'notes')->orderBy('products.id')->orderBy('version')->get() as $recipe) {
        $items = $db->table('recipe_items')->join('ingredients', 'ingredients.id', '=', 'recipe_items.ingredient_id')->join('units', 'units.id', '=', 'recipe_items.unit_id')->where('recipe_version_id', $recipe->id)->select('ingredients.code', 'quantity', 'base_quantity', 'units.code as unit_code')->orderBy('recipe_items.id')->get()->map(fn ($item) => $item->code.': '.(float) $item->quantity.' '.$item->unit_code.' (base '.(float) $item->base_quantity.')')->implode('; ');
        $lines[] = '| '.$recipe->sku.' | '.$recipe->version.' | '.(float) $recipe->yield_quantity.' '.$recipe->code.' | '.$items.' | '.$recipe->notes.' |';
    }
    $lines = array_merge($lines, ['', '## Bahan dan stok awal PUSAT', '', '| Kode | Bahan | Satuan | Biaya simulasi per satuan | Saldo saat laporan |', '|---|---|---|---:|---:|']);
    foreach ($db->table('ingredients')->join('units', 'units.id', '=', 'ingredients.unit_id')->select('ingredients.code', 'ingredients.name', 'units.code as unit_code', 'buy_price', 'stock')->orderBy('ingredients.id')->get() as $item) {
        $lines[] = '| '.$item->code.' | '.$item->name.' | '.$item->unit_code.' | '.$item->buy_price.' | '.$item->stock.' |';
    }
    $lines = array_merge($lines, ['', '## Akun dan akses', '', 'Password sumber tidak dipakai. Akun baru memakai password acak yang tidak ditampilkan; administrator dapat memakai SUPER_ADMIN_PASSWORD dari konfigurasi proyek baru. Semua akun baru wajib mengganti password. Gunakan /forgot-password dengan pengiriman email yang telah dikonfigurasi. Seeder tidak mengirim email otomatis dan tidak menulis password ke dokumentasi/log.', '', '| Nama | Login | Role | Outlet (default bertanda *) |', '|---|---|---|---|']);
    foreach (User::select('id', 'name', 'email')->orderBy('id')->get() as $user) {
        $roles = $user->roles()->pluck('name')->implode(', ');
        $outlets = $user->outlets()->get()->map(fn ($outlet) => $outlet->code.($outlet->pivot->is_default ? '*' : ''))->implode(', ');
        $lines[] = '| '.$user->name.' | '.$user->email.' | '.$roles.' | '.$outlets.' |';
    }
    $lines = array_merge($lines, ['', '### Permission per role', '']);
    foreach (Role::where('guard_name', 'web')->orderBy('name')->get() as $role) {
        $lines[] = '- **'.$role->name.'**: '.$role->permissions()->orderBy('name')->pluck('name')->implode(', ');
    }
    file_put_contents(__DIR__.'/../docs/source-seeders.md', implode("\n", $lines)."\n");
    echo "Report written: docs/source-seeders.md\n";
} finally {
    $db->rollBack();
}
