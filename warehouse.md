# Warehouse: tugas, fitur, dan alur kerja

Dokumen ini menjelaskan pekerjaan gudang berdasarkan implementasi Laravel dan Livewire saat ini. “Warehouse” dapat berarti petugas gudang (role `warehouse`) atau lokasi penyimpanan (`warehouses`); keduanya berkaitan, tetapi izin dan saldo stok tetap ditentukan per pengguna dan per gudang.

**Batas lokasi stok:** bahan mentah dan seluruh produksi dikelola Gudang Bersama (`type=central`, `outlet_id=null`). Gudang outlet tidak membeli, menerima, atau memproduksi bahan mentah. Outlet hanya menerima produk setengah jadi dari Gudang Bersama lewat transfer; saldo outlet tidak boleh ditambah lewat stok awal, impor Excel, PO, atau penyesuaian manual.

## Tanggung jawab petugas warehouse

- Menjaga bahan baku, saus, dan produk setengah jadi yang dipakai untuk produksi dan perakitan menu.
- Mengajukan kebutuhan produksi untuk outlet, menerima bahan yang dipesan, menjalankan produksi, dan mengirim hasilnya ke gudang tujuan.
- Mencatat hasil hitung fisik, koreksi stok dengan alasan, barang rusak/kedaluwarsa, dan retur yang diterima.
- Mengonfirmasi serah-terima uang atau setoran yang memang ditugaskan kepada warehouse.
- Menyerahkan persetujuan permintaan produksi, keputusan pembelian, dan pembayaran utang kepada Finance sesuai pemisahan izin.

## Fitur yang tersedia

| Area | Fungsi warehouse | Catatan operasional |
| --- | --- | --- |
| Master bahan (`/ingredients`) | Kelola data bahan sesuai izin | Bahan mentah dan saldonya berada di Gudang Bersama; harga dan satuan dipakai untuk estimasi biaya resep. |
| Resep (`/recipes`) | Lihat, susun versi resep, impor/ekspor resep sesuai izin | Resep baru membuat versi; order produksi menyimpan referensi versi yang dipakai. Perubahan resep tidak mengubah order lama. |
| Persediaan produk (`/inventory`) | Lihat saldo produk setengah jadi per gudang dan catatan mutasi; lakukan pembukaan atau penyesuaian stok jika diizinkan | Menu siap jual seperti paket dimsum/lumpia bukan stok gudang. Produk setengah jadi memakai saldo gudang (`product_warehouse`) dan ledger. |
| Excel persediaan (`/inventory`) | Export saldo produk setengah jadi di gudang yang dipilih; template/impor stok awal hanya di Gudang Bersama | Impor hanya menerima produk aktif setengah jadi yang melacak stok dan belum pernah dimutasi pada gudang bersama. Saldo outlet bertambah melalui transfer saja. Menggunakan Maatwebsite Excel. |
| Permintaan produksi (`/production-requests`) | Buat target produk setengah jadi untuk Gudang Bersama | Sistem menghitung kebutuhan dari resep aktif serta stok/reservasi bahan di Gudang Bersama. Jika cukup, request langsung siap diproduksi; jika kurang, request menunggu Finance untuk harga dan pembelian. |
| Penerimaan barang (`/goods-receivings`) | Catat barang terkirim, diterima, hasil QC, dan referensi invoice | Hanya jumlah yang diterima dan lolos QC menambah stok bahan. Penerimaan juga menjadi dasar payable Finance; kunci idempotensi mencegah pembukuan ganda saat retry. |
| Order produksi (`/production-orders`) | Buka shift Gudang Bersama, mulai order dari permintaan yang siap, lalu catat konsumsi dan hasil aktual | Produksi hanya dapat memakai bahan dan menghasilkan produk setengah jadi di Gudang Bersama. Shift tidak dapat ditutup selama order masih berjalan. |
| Transfer stok (`/stock-transfers`) | Kirim produk setengah jadi dari Gudang Bersama ke gudang outlet | Transfer satu arah; barang keluar dari pusat saat dikirim dan baru menambah saldo outlet saat diterima. Pengiriman/penerimaan berulang dilindungi idempotensi. |
| Opname (`/stock-opnames`) | Buat snapshot, isi hitungan fisik, lalu finalisasi jika diizinkan | Semua item harus dihitung. Finalisasi menolak snapshot jika stok berubah sejak opname dibuat agar selisih tidak diterapkan pada saldo yang sudah berbeda. |
| Waste (`/waste`) | Catat barang/batch yang rusak atau tidak layak dan alasannya | Mutasi dicatat sebagai waste; batch kedaluwarsa tidak tersedia untuk penjualan. |
| Retur supplier (`/supplier-returns`) | Buat retur barang dan ikuti persetujuan/penyelesaian sesuai izin | Pengurangan stok dan penyelesaian kredit/refund mengikuti workflow retur dan Finance. Refund tunai memerlukan shift kasir sumber yang terbuka. |
| Kas dan handover (`/cash-management`) | Lihat ledger dan buat/konfirmasi pergerakan kas yang diizinkan | Route ini memerlukan `cash-management-access` dan konfirmasi aksi tetap memakai permission kas terkait. Handover/setoran masuk ledger kas gudang; persetujuan lanjutan mengikuti permission terpisah. |

Halaman yang tersedia sebagai route ada di `routes/web.php`. Akses halaman menggunakan middleware konteks outlet dan permission `*-access`; aksi Livewire juga memeriksa permission aksi di server. Menyembunyikan tombol saja bukan kontrol akses.

### Impor dan ekspor Excel

1. Pilih gudang yang benar pada halaman Inventori.
2. **Export stok** mengunduh saldo fisik, reservasi, saldo tersedia, satuan, gudang, dan titik pemesanan dalam format `.xlsx`.
3. Untuk pemasukan saldo awal, unduh **Template stok awal**. Isi kolom `Stok Awal` dan `Alasan`; SKU sudah terisi. Isi nomor batch untuk produk yang memakai pelacakan batch dan tanggal kedaluwarsa bila ada.
4. Unggah template yang sudah diisi. Sistem menolak SKU ganda/tidak dikenal, SKU bukan produk setengah jadi, file dengan baris invalid, jumlah negatif, batch wajib yang kosong, serta produk yang memiliki saldo atau histori mutasi pada gudang tersebut.
5. Impor diproses sebagai satu transaksi. Jika satu baris tidak valid, seluruh impor dibatalkan; jika berhasil, tiap perubahan tercatat sebagai mutasi stok awal dan ledger.

Fitur ini ditujukan untuk memasukkan stok awal saat menyiapkan gudang. Perubahan stok operasional selanjutnya dilakukan lewat penerimaan, produksi, transfer, opname, waste, atau mutasi yang sesuai. Hak export memakai `inventory-access`; template dan impor memakai `inventory-opening`.

## Alur kerja utama

### 1. Siapkan master data

1. Pastikan satuan dan kategori tersedia.
2. Buat atau perbarui bahan dengan kode, nama, satuan, harga beli, dan status aktif.
3. Susun resep per satu unit keluaran, lengkap dengan kuantitas serta waste bila relevan.
4. Periksa versi resep aktif dan kecocokan satuan sebelum membuat permintaan produksi.

### 2. Ajukan kebutuhan dan terima bahan

1. Warehouse memilih produk setengah jadi dan jumlah target pada Permintaan Produksi. Request ini juga dapat dipakai sebagai rencana produksi internal saat bahan sisa masih tersedia.
2. Permintaan dibuat untuk Gudang Bersama; sistem menghitung kebutuhan bahan menggunakan versi resep dan saldo/reservasi bahan gudang pusat.
3. Jika kebutuhan bahan sudah tersedia, request langsung berstatus siap produksi tanpa menunggu Finance. Jika ada kekurangan, request tampil kepada Finance dengan bahan, satuan, kebutuhan, stok tersedia, jumlah kurang, estimasi biaya, dan harga satuan terakhir.
4. Finance dapat memperbarui harga beli dan menentukan jumlah pembelian minimal sebesar kekurangan. Jumlah dapat ditambah agar ada stok bahan untuk produksi berikutnya. Sistem membuat PO dan menampilkan status pembelian berjalan sebagai `purchasing` (sedang dikirim).
5. Setelah barang datang, warehouse mencatat jumlah terkirim, jumlah diterima, status QC per baris, catatan, dan nomor invoice.
6. Periksa ulang jumlah yang lolos QC. Hanya jumlah tersebut yang menambah saldo bahan dan ledger; bahan ekstra menjadi stok gudang yang tersedia untuk produksi berikutnya.

### 3. Produksi dan kirim ke outlet

1. Mulai order dari permintaan berstatus siap produksi. Request berbahan stok tersedia dapat langsung dimulai; request pembelian baru siap setelah barang diterima dan lolos QC.
2. Saat menyelesaikan order, masukkan konsumsi aktual setiap bahan dan jumlah output aktual setiap produk.
3. Sistem menolak konsumsi yang melebihi stok tersedia setelah reservasi permintaan lain diperhitungkan.
4. Tinjau stok barang setengah jadi di Gudang Bersama, kemudian buat transfer ke gudang outlet. Transfer tidak dapat dibuat dari outlet ke pusat atau antar-outlet.
5. Pihak penerima memeriksa dan menerima transfer. Saldo outlet baru bertambah setelah penerimaan dicatat.
6. Outlet merakit menu jual dari stok setengah jadi sesuai resep assembly aktif POS. Penjualan mengurangi komponen resep; paket menu tidak disimpan sebagai stok produk jadi gudang.

### 4. Opname, penyesuaian, dan waste

1. Buat opname untuk gudang yang benar; sistem menyimpan snapshot saldo produk terlacak.
2. Hitung fisik dan simpan jumlah setiap produk, termasuk nol bila rak kosong.
3. Review/finalisasi hanya dapat dilakukan setelah hitungan lengkap dan izin mencukupi.
4. Jika saldo berubah setelah snapshot, buat opname baru. Jangan memaksakan selisih dari snapshot lama.
5. Untuk kerusakan atau kedaluwarsa, catat waste dengan alasan, bukan penyesuaian tanpa referensi.

## Stok, ledger, dan batas akses

- Stok produk setengah jadi adalah saldo per pasangan produk-gudang di `product_warehouse`; menu siap jual dirakit dari resep pada checkout POS dan tidak perlu saldo gudang produk jadi.
- Saldo bahan disimpan per gudang pada `ingredient_warehouses`; perubahan harus disertai ledger bahan. Jangan mengedit kolom saldo langsung di database.
- Mutasi operasional memakai transaksi database, penguncian baris, dan kunci idempotensi pada alur terkait untuk mencegah saldo ganda atau stok negatif.
- Gudang outlet dibatasi oleh outlet yang boleh diakses pengguna. Gudang pusat (`outlet_id` kosong) memiliki pembatasan akses tambahan; pemilihan ID gudang tidak melewati pemeriksaan server.
- Stok, pembayaran, hasil QC, dan histori audit adalah catatan operasional. Perbaiki melalui workflow yang menyediakan ledger/audit, jangan menghapus histori untuk “merapikan” saldo.

Role `warehouse` yang dibuat `RoleSeeder` memperoleh akses untuk sejumlah tugas di atas, termasuk permintaan/order produksi, transfer, opname, bahan/resep, penerimaan, retur, dan konfirmasi serah kas. Permission aktual pada database bisa berbeda jika seeder alternatif atau assignment manual telah dipakai. Periksa permission user dan guard outlet yang aktif bila tombol atau route tidak tersedia.

## Batas implementasi yang perlu diketahui

### Shift dan rekap produksi harian

- Halaman `/production-orders` memiliki shift kerja warehouse tersendiri. Shift ini mencatat waktu mulai/selesai dan gudang kerja; shift ini tidak membuka atau merekonsiliasi kas seperti shift kasir.
- Petugas membuka shift untuk gudang yang dipilih sebelum memulai production order. Order yang dimulai pada gudang tersebut ditautkan ke shift agar hasilnya dapat ditelusuri per petugas dan tanggal.
- Shift tidak dapat ditutup selama masih ada production order yang berjalan. Selesaikan dan catat konsumsi aktual serta output terlebih dahulu.
- Tabel rekap dapat difilter berdasarkan tanggal. Rekap menampilkan shift, petugas, gudang, status, nama produk setengah jadi yang selesai dibuat, dan total kuantitas aktualnya.
- Produksi tetap menggunakan permintaan produksi dan resep aktif. Bila bahan tersedia, warehouse dapat mengajukan rencana internal tanpa menunggu pembelian Finance; bila kurang, alur permintaan pembelian Finance tetap berlaku.
- Order lama yang dibuat sebelum fitur shift memiliki `production_shift_id` kosong dan tetap dapat ditampilkan di riwayat production order; tidak otomatis dimasukkan ke rekap shift.

- Beberapa fungsi berada di modul Purchasing/Finance walaupun warehouse mengerjakan langkah penerimaan fisiknya. Warehouse tidak otomatis mendapat izin menyetujui permintaan atau membayar payable.
- Layar penyesuaian `/inventory` mengelola stok **produk**. Saldo **bahan** memiliki ledger/workflow tersendiri melalui penerimaan dan penyelesaian produksi; jangan menganggap satu halaman mengubah keduanya.
- Seeder HPP membuat bahan, produk setengah jadi dan resep assembly dari rincian workbook tanpa saldo pembukaan. Resep menu hanya aktif bila setiap komponennya memiliki resep sumber yang lengkap; menu dengan komponen belum terpetakan (misalnya resep Lumpia generik yang tidak ada di workbook) perlu dilengkapi sebelum POS dapat menjualnya.
- Kemasan kotak dimsum 4 pcs tidak memiliki harga di workbook, sehingga masuk sebagai master kemasan dengan harga Rp0 dan harus diperbarui Finance sebelum dipakai untuk perhitungan biaya.
- Pelacakan batch/kedaluwarsa berlaku pada produk yang mengaktifkan pelacakan batch. Jangan mencampur stok batch yang sudah kedaluwarsa kembali ke stok layak jual.
- Finance tetap bertanggung jawab atas persetujuan pembelian/produksi dan pembayaran; kewenangan tambahan hanya ada bila permission eksplisit diberikan.

## Rujukan kode

- Route dan middleware: `routes/web.php`
- Penetapan role: `database/seeders/RoleSeeder.php`, `database/seeders/PermissionSeeder.php`
- Saldo dan mutasi barang jadi: `app/Models/ProductWarehouse.php`, `app/Services/Inventory/StockMutationService.php`
- Transfer dan opname: `app/Services/Inventory/StockTransferService.php`, `app/Services/Inventory/StockOpnameService.php`
- Permintaan dan order produksi: `app/Services/Production/ProductionRequestService.php`, `app/Services/Production/ProductionOrderService.php`
- Penerimaan/QC: `app/Livewire/Inventory/ProductionRequestsIndex.php`, `app/Livewire/Purchasing/GoodsReceivingsIndex.php`
- Ringkasan akses dan workflow: `docs/system-overview-access-and-workflows.md`
