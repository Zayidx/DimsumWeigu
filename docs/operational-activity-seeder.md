# Seeder aktivitas operasional

`OperationalActivitySeeder` adalah **simulasi khusus local/testing**. `DatabaseSeeder` tidak memanggilnya, sehingga `php artisan migrate:fresh --seed` dan `php artisan db:seed` biasa tidak membuat aktivitas simulasi.

Jalankan setelah seeder utama pada database local/testing yang memiliki gudang pusat dan empat outlet yang dibutuhkan. Seeder dapat ditambahkan ke database yang sudah berisi aktivitas; ia tidak menghapus transaksi lama dan batch data tambahan ini memakai kunci idempotensi `operational-demo-v2`, terpisah dari batch sebelumnya:

```powershell
php artisan db:seed --class=DatabaseSeeder
php artisan db:seed --class=OperationalActivitySeeder
```

Seeder ini memanggil `TestDataSeeder` untuk menyiapkan menu, resep, dan bahan uji yang dibutuhkan. Ia kemudian membuat empat skenario outlet dengan petugas simulasi beralamat `@example.invalid`:

- Warehouse mengajukan produksi, finance menyetujui kebutuhan, warehouse menyelesaikan produksi dan mengirim stok ke outlet.
- Finance membuat pembelian barang uji, warehouse menerima 9 dari 10 unit setelah QC menolak satu unit, lalu finance membayar sebagian utangnya lewat transfer bank.
- Kasir membuka shift dan menyelesaikan 25 transaksi POS per outlet, satu paket isi 4 per transaksi. Stok komponen dan pencatatan keuntungan berasal dari layanan checkout.
- Kasir menyetor Rp500.000 kas per outlet ke gudang pusat; finance menyetujui, warehouse menerima. Warehouse lalu menyerahkan Rp1.500.000 saldo gudang pusat ke lokasi **Kas Finance (Simulasi)**; finance menyetujui dan menerima.

Kas Finance dibuat sebagai lokasi penyimpanan uji karena aplikasi saat ini mencatat saldo kas pada gudang. Lokasi ini hanya untuk simulasi, bukan rancangan rekening atau brankas finance operasional.

Untuk simulasi stok dan POS, warehouse memproduksi dimsum wortel dan beef setengah jadi, masing-masing 100 pcs per outlet. Finance melihat bahan baku, jumlah, dan estimasi biayanya sebelum pembelian disetujui. POS menjual 25 paket Dimsum Mentai isi 4 per outlet: 24 transaksi tunai dan satu transaksi kredit. Tiap paket memakai dua dimsum wortel, dua beef, saus mentai, kemasan, dan sumpit dari stok outlet. Seeder lalu memproduksi dan mengirim tambahan 50 pcs tiap komponen per outlet untuk uji POS manual. Sesudah 25 paket simulasi dan stok tambahan, outlet memiliki 100 pcs masing-masing komponen.

Simulasi keuangan menghasilkan hutang pemasok dengan status partial dan unpaid, serta satu piutang kredit per outlet. Finance menagih sebagian Rp5.000 dari tiap piutang lewat transfer bank sehingga sisanya tetap terlihat sebagai piutang partial. Tiap outlet menyetor Rp500.000 kas penjualan ke gudang pusat; finance lalu mengambil Rp1.500.000 ke lokasi kas simulasi.

Seluruh skenario memakai layanan aplikasi, kunci idempotensi khusus, dan satu transaksi database. Menjalankan ulang seeder yang sudah berhasil tidak menambah aktivitas. Seeder menolak lingkungan selain `local`/`testing` dan tidak mereset data yang sudah ada. Jangan jalankan pada database operasional meskipun konfigurasi lingkungan tertulis `local`.

Test tersedia untuk memeriksa bahwa aktivitas lama tetap ada dan simulasi tidak terduplikasi. Jalankan bila ingin memverifikasi perubahan:

```powershell
php artisan test tests/Feature/OperationalActivitySeederTest.php
```
