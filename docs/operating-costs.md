# Pengeluaran dan waste outlet

## Pengeluaran

- Menu **Pengeluaran** memakai layout Mazer dan membutuhkan `expenses-access`; pencatatan serta kategori membutuhkan `expenses-create`. Ekspor membutuhkan `reports-export` sekaligus akses modul.
- Tambahkan kategori, pilih gudang aktif pada outlet yang sedang dipilih, jumlah, tanggal, metode dan keterangan. Kategori dapat dinonaktifkan tanpa mengubah nama kategori yang tersimpan pada riwayat.
- Transfer bank membutuhkan referensi bukti pembayaran. Modul mencatat pembayaran yang sudah dilakukan; tidak mengirim transfer bank.
- Tunai membutuhkan shift terbuka milik petugas di outlet/gudang yang sama dan tanggal hari ini. Saldo shift dan ledger kas gudang harus cukup. Pengeluaran, pergerakan kas, ledger, expected cash dan audit disimpan dalam satu transaksi.
- Pengeluaran yang tersimpan tidak dapat diedit atau dihapus. Tanggal mendatang ditolak. Referensi bukan unggahan bukti; penyimpanan berkas bukti belum tersedia.
- Kunci permintaan mencegah pencatatan ulang; perubahan payload pada kunci yang sama ditolak. Retry memerlukan petugas dan outlet yang sama.

## Waste

- Menu **Waste** membutuhkan `waste-access`; pencatatan membutuhkan `waste-create`. Pilih gudang outlet, cari nama/SKU produk, pilih produk stok, isi kuantitas dalam satuan dasar, alasan dan catatan pembuangan.
- Produk dengan batch wajib memilih batch yang dibuang. Batch harus milik produk dan gudang yang sama. Batch kedaluwarsa dapat dibuang, tetapi tetap tidak dapat dijual. Alasan kedaluwarsa hanya diterima bila tanggal batch sudah lewat.
- Stok gudang, agregat produk, batch, ledger inventori, snapshot balance, record waste dan audit disimpan atomik. Kekurangan stok membatalkan seluruh proses. Record waste tidak dapat dihapus.
- HPP menggunakan snapshot `products.buy_price` saat pencatatan; nilai waste adalah HPP dikali kuantitas, dibulatkan ke dua desimal. Ini bukan penelusuran harga historis per batch. Perubahan harga berikutnya tidak mengubah nilai record atau hasil retry.
- Waste tidak memotong kas; pengeluaran produksi/operasional yang dibayar dicatat secara terpisah agar tidak terjadi pengeluaran tunai ganda.

## Laporan dan akses

Kedua halaman menyediakan rentang tanggal, total, riwayat dengan pagination dan CSV yang menetralkan formula spreadsheet. Semua daftar, kategori, dropdown dan ekspor dibatasi outlet aktif. Gudang pusat tanpa outlet tidak termasuk modul outlet ini; kerugian bahan baku produksi belum ditangani modul waste produk.

Role pada instalasi baru: manager mengakses kedua modul; finance mencatat pengeluaran dan membaca waste; warehouse mencatat waste. Pada instalasi yang sudah berjalan, tambahkan izin melalui pengelolaan role sesuai kebutuhan. Jangan menjalankan ulang seeder role untuk menimpa kustomisasi izin.

## Verifikasi

`php artisan test --filter=OperatingCostsTest` memeriksa workflow HTTP/Livewire, kas sekali saja, bank tanpa kas, retry konflik, kategori nonaktif, tanggal tidak valid, stok/global/ledger, batch kedaluwarsa, rollback, akses lintas outlet, permission dan CSV formula-safe. SQLite digunakan dalam suite. Perilaku concurrent row locking MySQL dan QA tampilan pada viewport/perangkat nyata masih perlu diverifikasi sebelum rilis.
