# Manajemen kas dan shift

## Pergerakan kas

Menu Mazer **Manajemen kas** membutuhkan `cash-management-access`. Tambah/kurangi kas shift memerlukan `cash-management-create`, shift sendiri pada outlet aktif, konfirmasi password terbaru, jumlah positif dan catatan. Kas keluar tidak boleh melebihi expected cash shift atau saldo ledger gudang. Kunci idempotensi mencegah ledger/movement ganda; payload berubah pada kunci yang sama ditolak.

Catat hanya uang fisik yang benar-benar masuk/keluar. Biaya, penjualan, hutang/piutang dan serah-terima menghasilkan ledger sendiri; jangan catat ulang sebagai kas manual. Riwayat/CSV dibatasi outlet aktif, ditambah pusat bagi super-admin/user `warehouses-access`. CSV membutuhkan `reports-export` dan menetralkan formula spreadsheet.

## Serah-terima dan pickup

1. **Pending**: pengirim memilih gudang sumber/tujuan yang dapat diakses, jumlah dan catatan. Sumber cabang harus berada di outlet aktif dan mempunyai shift sendiri terbuka. Kas belum berpindah; permintaan tidak mereservasi saldo.
2. **Approved / dalam perjalanan**: petugas `cash-management-approve` memeriksa ulang saldo, shift sumber dan akses tujuan, lalu menyetujui pengeluaran uang fisik. Ledger sumber dan expected cash berkurang. Pengirim tidak boleh menyetujui sendiri, kecuali super-admin.
3. **Confirmed / diterima**: penerima `cash-management-create` memilih konteks outlet tujuan, menggunakan shift sendiri di gudang tujuan dan konfirmasi password. Kas tujuan bertambah hanya setelah penerimaan lengkap. Pengirim tidak boleh mengonfirmasi sendiri, kecuali super-admin. Penerima tidak membutuhkan akses operasional outlet sumber.

Pusat tanpa outlet menggunakan ledger gudang tanpa shift cabang dan membutuhkan super-admin/`warehouses-access`. Transisi serta request idempotent dan seluruh ledger/movement/status/audit atomik. Bila shift tujuan belum tersedia, uang tetap dalam perjalanan.

Pending dapat dibatalkan oleh pengirim atau petugas approval yang juga memiliki create. Kas yang sudah dikirim tidak dapat dibatalkan; setelah diterima, catat serah-terima balik. Shift sumber yang ditutup sebelum approval mengharuskan pembatalan dan request ulang. Gudang nonaktif harus diaktifkan kembali sebelum transisi.

## Rekonsiliasi dan tutup paksa

Tutup normal memerlukan `cashier-shifts-close`, konfirmasi password, kas fisik, dan catatan wajib bila selisih. API meminta password saat ini dalam request tutup shift dan membatasi delapan request/menit; password tidak disimpan dalam audit/response.

Tombol **Tutup paksa** di daftar Shift membutuhkan `cashier-shifts-force-close`. Halaman/action memakai password confirmation. Service memeriksa ulang permission, outlet aktif, status open, nilai kas dan alasan wajib. Expected/actual cash, selisih, waktu, penutup, alasan dan `is_force_closed` disimpan; cart aktif/hold kedaluwarsa, penjualan dipertahankan. Manager bawaan tidak memiliki permission force-close.

Expected cash = kas awal + movement masuk − movement keluar + tender tunai penjualan. Sale tunai mencatat `sale_cash` pada ledger gudang. Sale dari versi sebelum pencatatan tersebut tidak dibackfill otomatis; audit ledger historis sebelum memakai saldo historis untuk operasi kas.

## Verifikasi

`CashManagementTest` mencakup tahap perpindahan, retry, perubahan saldo/shift, pembatalan, key conflict, self-approval, step-up, outlet, force-close/cart serta HTTP/Livewire. `MobilePosApiTest` mencakup tutup normal dengan password salah/benar. Suite SQLite; concurrent MySQL, uang fisik dan UI perangkat nyata belum diverifikasi. Pada instalasi dengan role kustom, tambahkan permission lewat halaman Role; jangan reseed dan menimpa konfigurasi operasional.
