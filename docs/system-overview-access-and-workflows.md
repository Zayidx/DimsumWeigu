# Dokumentasi Sistem Dimsum Weigu

Dokumen ini merangkum fitur yang sudah tersedia di repository, batasan yang masih perlu dikerjakan, hak akses tiap peran, dan alur operasional utama. Hak akses akhir ditentukan oleh permission yang diberikan kepada user, bukan semata-mata nama role. Akses data juga dibatasi oleh outlet/konteks gudang.

## Ringkasan Implementasi

| Area | Yang sudah tersedia | Status / catatan |
|---|---|---|
| Akun dan akses | Login, reset/konfirmasi password, perubahan password wajib, role-permission, pembatasan akun aktif, pilihan outlet, audit log | Fondasi tersedia. Sejumlah skenario otorisasi dan redirect masih perlu dirapikan. |
| Master data | Produk/menu, kategori, satuan dan konversi, supplier, outlet/gudang, bahan baku, kategori bahan | Master Bahan bisa ditambah dan diedit. Kode bahan tidak berubah setelah dibuat; satuan dikunci jika sudah dipakai resep. |
| Resep dan HPP | Resep per satuan produk, komposisi berversi, bahan aktif, persentase waste, perkiraan HPP | Perkiraan biaya mengambil harga terkini dari Master Bahan; produksi menyimpan konsumsi dan biaya aktual sebagai histori. |
| Stok dan pembelian | Ledger stok produk, transfer, opname, PO produk existing; permintaan dan pembelian bahan untuk produksi; penerimaan/QC bahan | Penerimaan menambah stok hanya untuk kuantitas lolos QC. Alur bahan baku memakai tabel dan ledger khusus. |
| Produksi | Order produksi, snapshot bahan/resep, konsumsi aktual, sisa bahan, output aktual, HPP aktual | Output aktual masuk stok barang jadi gudang pusat dan dapat ditransfer ke outlet. |
| POS dan kas | Keranjang server-side, shift, checkout, metode pembayaran, mutasi kas, handover/pickup, penutupan shift | POS memakai layout khusus bertema homepage, bukan shell dashboard. Penjualan memotong stok melalui layanan checkout existing. |
| Keuangan dan laporan | Hutang/piutang, pembayaran, biaya operasional, waste, laporan penjualan/laba/inventori/aging, CSV terbatas dan audit | Pembayaran payable dari penerimaan bahan diintegrasikan ke halaman hutang. |

Seeder `HppWorkbookSeeder` membaca `hpp.json`, idempotent, menolak environment production, dan tidak menimpa stok/harga/resep yang sudah ada. Jalankan hanya di local/testing setelah meninjau data sumber: `php artisan db:seed --class=HppWorkbookSeeder`. Migrasi workflow sudah tersedia di `database/migrations`; pada instalasi baru/yang belum diperbarui jalankan `php artisan migrate` (jangan gunakan `migrate:fresh` atau `db:wipe`).

## Hak Akses per User

Role berikut adalah pembagian kerja standar dari seeder role. Admin dapat mengubah permission; karena itu daftar ini menjelaskan konfigurasi standar dan akses aktual tetap bergantung pada assignment permission serta outlet user.

### Super-admin

Mengelola user, role dan permission, master data, seluruh modul operasional, pengaturan, laporan, dan audit. Aksi sensitif tertentu meminta konfirmasi password ulang. Role `super-admin` dilindungi dari pengubahan permission.

### Manager

Memantau dashboard, produk/master operasional yang diizinkan, supplier, transaksi, pembelian/penerimaan, stok, produksi, laporan, audit, dan operasi kas/outlet yang tercantum pada permission. Role manager tidak otomatis memiliki akses administrasi user/role, pengaturan pembayaran, atau force-close shift.

### Finance (`finance`)

Melihat dashboard dan laporan keuangan; mengelola supplier serta pembelian bahan, menyetujui/menolak permintaan produksi, melihat penerimaan dan membayar payable; mengakses piutang, laporan, opname dan audit sesuai permission. Finance tidak menyelesaikan konsumsi produksi atau QC penerimaan kecuali permission terkait juga diberikan.

### Warehouse (`warehouse`)

Mengelola stok, opname, transfer, supplier/penerimaan yang diizinkan, Master Bahan, resep, permintaan produksi, order produksi, konsumsi aktual dan output. Warehouse adalah peran yang tepat bagi Gusti untuk mengajukan bahan dan menjalankan produksi. Approval permintaan serta pembayaran tetap menjadi tugas Finance.

### Cashier (`cashier`)

Mengoperasikan POS, pelanggan, transaksi, dan shift kasir; membuka/menutup shift dan membuat handover jika permission terkait diberikan. Kasir tidak boleh mengubah ledger stok atau transaksi completed secara langsung. Akses outlet dan shift aktif tetap menjadi prasyarat tindakan POS.

### Customer / publik

Role customer tidak memperoleh permission internal secara default. Homepage publik tersedia; fitur dine-in publik, pembayaran online, CRM/loyalty, dan order mandiri belum lengkap sebagai workflow end-to-end.

## Alur Sistem Operasional

### 1. Persiapan Master Bahan dan Resep

1. Warehouse atau admin membuat bahan baru di **Master Bahan**: kode, nama, kategori, satuan, harga beli, dan status aktif.
2. Warehouse menyusun resep per satu unit menu dengan memilih bahan Master Bahan, jumlah pemakaian, dan waste bila ada.
3. Resep baru membentuk versi baru; versi sebelumnya dipertahankan sebagai histori. Estimasi HPP memakai harga bahan master terkini sehingga perubahan harga memengaruhi estimasi resep.

### 2. Permintaan, Pembelian, dan QC Bahan

1. Warehouse membuat **Permintaan Produksi** dengan menu dan jumlah target. Sistem menghitung kebutuhan bahan dari versi resep aktif, memperhitungkan stok tersedia dan reservasi agar permintaan tidak mengklaim stok yang sama dua kali.
2. Finance meninjau kebutuhan. Jika stok cukup, permintaan dapat disetujui tanpa membuat PO. Jika kurang, Finance memilih supplier dan biaya satuan aktual; sistem membuat PO untuk kekurangan.
3. Warehouse mencatat barang datang, jumlah terkirim, jumlah diterima, status QC, dan referensi invoice.
4. Hanya jumlah yang diterima dan lolos QC menambah stok bahan serta ledger. Penerimaan membuat payable berdasarkan jumlah/harga yang diterima. Finance melunasi payable di modul Hutang.
5. Penerimaan memakai idempotency key agar retry tidak membukukan stok dua kali. Kekurangan/penolakan dapat menyisakan kebutuhan yang harus ditindaklanjuti Finance.

### 3. Produksi hingga Penjualan

1. Warehouse memulai order dari permintaan yang sudah disetujui dan bahan yang tersedia. Order mengunci referensi versi resep.
2. Saat selesai, Warehouse memasukkan konsumsi aktual per bahan dan output aktual per menu. Sistem memvalidasi ketersediaan, mengurangi bahan melalui ledger, melepas sisa reservasi, menghitung biaya aktual, dan menambah stok barang jadi gudang pusat berdasarkan output aktual.
3. Warehouse membuat transfer stok barang jadi ke gudang/outlet penjualan. Penerimaan transfer outlet mencatat stok outlet; penerimaan ganda ditolak oleh kontrol idempotency existing.
4. Kasir memilih outlet dan gudang aktif, membuka shift, membuat/menahan keranjang, lalu checkout. Checkout memvalidasi ulang stok dan pembayaran, mencatat transaksi serta detail biaya, dan mengurangi stok melalui layanan POS.
5. Kasir menutup shift dengan kas aktual. Selisih, pergerakan kas, handover/pickup, dan audit mengikuti layanan kas yang tersedia.

## Yang Masih Kurang atau Perlu Diverifikasi

- Cakupan keseluruhan belum memenuhi semua phase 0–14 di `newintruction.json`. Fitur pricing/CRM/loyalty, dine-in end-to-end, gateway payment/webhook, printer, WhatsApp, offline/browser sync, serta backup/restore belum lengkap.
- Laporan operasional khusus produksi, waste, kas, dan laba-rugi penuh masih perlu dilengkapi atau dipastikan kesesuaiannya. Import aman untuk semua master data juga belum menyeluruh.
- Belum dilakukan validasi langsung di browser untuk semua ukuran layar, integrasi payment provider nyata, perangkat cetak, dan konkurensi MySQL pada jam operasional.
- Suite penuh terakhir masih memiliki kegagalan pada tes role Livewire, force-close shift, dan respons permission route. Tes workflow permintaan/produksi yang difokuskan lulus, tetapi tiga kegagalan tersebut perlu ditangani terpisah sebelum menyatakan seluruh aplikasi bebas regresi.
- Audit permission perlu dipastikan pada database berjalan: role seeder memiliki alias permission untuk workflow produksi, sedangkan beberapa nama permission pada seeder alternatif berbeda. Setelah mengubah assignment, periksa ulang akses di halaman terkait untuk user warehouse dan finance.

## Panduan Operasional Ringkas

- Terapkan schema dengan migrasi maju biasa: `php artisan migrate`.
- Import data HPP hanya secara eksplisit di local/testing menggunakan seeder di atas; tinjau harga, satuan, dan resep yang tidak lengkap sebelum operasional.
- Pastikan user memiliki outlet yang benar dan memilih konteks outlet/gudang sebelum menjalankan workflow.
- Jangan mengubah stok atau kas langsung di database; gunakan aksi aplikasi agar ledger, audit, dan saldo tetap konsisten.
- Uji alur dari request sampai checkout pada database staging dengan role warehouse, finance, dan cashier sebelum dipakai transaksi nyata.
