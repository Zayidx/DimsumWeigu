# Impor dan ekspor Excel

Aplikasi menggunakan **Maatwebsite Excel** (PhpSpreadsheet) untuk file `.xlsx`, `.xls`, dan `.csv`. Fitur tersedia pada master data, laporan, dan inventori gudang. Akses mengikuti permission modul terkait; ekspor laporan tetap memerlukan `reports-export`.

## Master data

Halaman Produk, Bahan, Pelanggan, dan Pemasok menyediakan **Export Excel**, **Template**, dan **Import**.

| Modul | Kunci pencocokan | Data yang dapat diimpor |
| --- | --- | --- |
| Produk | SKU | Nama, barcode, kategori slug, kode satuan, harga beli/jual, pajak, batas stok, status aktif/jual, flag semi-jadi, pelacakan stok/batch, deskripsi |
| Bahan | Kode bahan | Nama, kode satuan, kategori bahan, harga beli, status aktif, deskripsi |
| Pelanggan | Kode pelanggan | Nama, email, telepon, tanggal lahir, limit kredit, tier, izin pemasaran, status aktif, catatan |
| Pemasok | Kode pemasok | Nama, email, telepon, nomor pajak, alamat, kota, status aktif, catatan |

Impor mencocokkan baris dengan SKU/kode. Baris yang cocok memperbarui master; kode baru membuat data baru jika pengguna memiliki permission create. Kode yang sama berulang, referensi kategori/satuan tidak dikenal, nilai tidak valid, atau permission tidak cukup membatalkan seluruh file. File dibatasi 10 MB dan 5.000 baris.

> Impor master tidak mengubah saldo gudang, stok bahan, ledger, resep, penjualan, atau histori pembelian. Produk baru memperoleh saldo nol di gudang aktif. Perubahan satuan produk/bahan ditolak jika sudah digunakan pada histori/resep. Harga beli dan jual, limit kredit, serta consent pelanggan termasuk field yang dapat diperbarui; tinjau file sebelum mengunggahnya.

Ekspor master mencakup semua kolom yang didukung. File hasil ekspor dapat dipakai sebagai dasar pembaruan massal setelah kolom yang tidak ingin diubah dibiarkan utuh.

## Laporan

Tombol ekspor pada laporan Penjualan, Stok, Laba, Aging, Pengeluaran, Waste, dan Ledger Kas mengunduh `.xlsx`. Laporan memakai filter yang sedang dipilih, termasuk tanggal, outlet, gudang, kasir, metode, dan pencarian sesuai modul. Hak ekspor laporan memerlukan `reports-export`; ekspor ledger kas juga mempertahankan pemeriksaan password step-up.

## Inventori gudang

Ekspor stok dan template stok awal dibahas di [warehouse.md](../warehouse.md). Import stok awal hanya menerima produk setengah jadi aktif tanpa histori mutasi di gudang tersebut. Setiap baris dicatat sebagai mutasi stok dan ledger; saldo berjalan tidak ditimpa.
