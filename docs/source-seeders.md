# Data HPP dan seeder uji

## Seeder

`DatabaseSeeder` hanya menyiapkan permission, role, data outlet/gudang, dan konfigurasi aplikasi. Data HPP serta aktivitas simulasi tidak otomatis dijalankan saat `migrate:fresh --seed` atau `db:seed`.

Jalankan data katalog dan BOM uji secara eksplisit pada database lokal yang disiapkan untuk pengujian:

```powershell
php artisan db:seed --class=TestDataSeeder
php artisan db:seed --class=OperationalActivitySeeder
```

`OperationalActivitySeeder` mensimulasikan permintaan bahan, produksi setengah jadi, transfer ke outlet, POS, QC, piutang, utang, dan setoran kas. Keduanya menolak lingkungan production. Seeder aktivitas menggunakan kunci idempotensi; jangan gunakan untuk mencampur data simulasi ke transaksi bisnis aktif.

## Data HPP yang diterapkan

- Harga bahan dibaca per unit resep gram, mililiter, atau pcs. Ayam fillet memakai Rp57/gram walau label pembelian di sumber tertulis kg.
- Dimsum diproduksi gudang per buah sebagai Ori polos, Wortel, dan Beef. Resep topping Wortel/Beef memakai bahan dasar yang sama; Ori polos tidak memakai topping.
- Paket POS isi 4 dimsum memakai 3 Ori polos dan 1 Wortel. Mentai memakai 30 gram saus per dumpling; Bolognese memakai 35 gram per dumpling.
- Dimsum isi 4 memakai kotak kecil, plastik dimsum 24, sumpit, dan satu cup chili oil. Isi 6/8 memakai kotak besar dengan komponen lain sama. Kotak kecil Rp150.000/379 pcs = sekitar Rp395,78/pcs; kotak besar mengikuti workbook Rp200.000/425 pcs.
- Lumpia adalah produk setengah jadi. Paket isi 3/5 memakai kotak besar, plastik dimsum 24, sumpit, dan satu cup chili oil; Lumpia Mentai memakai saus 30 gram per lumpia.
- Chiquro Garlic/Mentai/Lava dijual per satu item seharga Rp26.000; Cheese Rp28.000. Produk memakai Chiquro bolong atau Chiquro Cheese dari gudang, satu kertas Chiquro, satu plastik kecil, dan saus sesuai resep. Chiquro Cheese tidak memakai saus cheese di outlet.
- Penjualan satuan dasar hanya diizinkan untuk Chiquro. Dimsum dan Lumpia wajib memakai paket.

## Batas data

Rasio komponen dimsum isi 6/8 dan komposisi paket Mix belum dikonfigurasi karena belum ada komposisi yang dikonfirmasi. Checkout menolak satuan paket yang belum memiliki resep rakitan produk setengah jadi agar stok tidak berkurang keliru. Yield produksi Chiquro dan takaran cheese pada Chiquro Cheese juga perlu dicocokkan sebelum dianggap sebagai HPP final.

Seeder memperbarui master bahan dan BOM berkode `TEST-`, mengarsipkan versi resep uji lama, dan mempertahankan saldo stok/ledger yang telah ada. Jangan menjalankan seeder pada data operasional aktif.
