# PROMPT REFACTOR TOTAL SISTEM WAREHOUSE, PRODUKSI, TRANSFER STOK, DAN SERAH TERIMA KAS

## TUJUAN UTAMA

Saya ingin melakukan **refactor atau perombakan menyeluruh terhadap sistem operasional Warehouse (Gudang)** yang saat ini sudah tersedia di aplikasi.

Perubahan ini harus mengintegrasikan tiga role utama:

1. **Warehouse / Gudang** — Mengelola resep bahan setengah jadi, produksi, stok bahan mentah, penerimaan barang, pengembalian stok outlet, dan penerimaan serta penyerahan uang tunai.
2. **Cashier / Outlet** — Mengambil dan mengembalikan stok bahan setengah jadi dari gudang serta melakukan serah terima uang hasil penjualan harian kepada Warehouse.
3. **Finance** — Menyetujui pembelian bahan mentah, mengelola harga pembelian, memantau laporan produksi, dan menerima serah terima uang dari Warehouse.

**PENTING:**
- Pelajari terlebih dahulu struktur project, database, API, authentication, role permissions, dan alur sistem yang sudah berjalan.
- Gunakan arsitektur, komponen UI, dan pola coding yang sudah diterapkan di project.
- Jangan membuat sistem terpisah apabila fitur tersebut bisa diintegrasikan dengan modul existing.
- Lakukan perubahan pada frontend, backend, database, dan perhitungan stok secara menyeluruh.
- Semua transaksi harus tersimpan dalam database dan memiliki riwayat yang bisa diaudit.
- Jangan menghapus data lama yang masih dibutuhkan.
- Pastikan perubahan tidak merusak fitur POS, stok outlet, Finance, atau modul lain yang sudah berjalan.
- Fokus pada sistem yang benar-benar berfungsi secara end-to-end, bukan sekadar mockup UI.

---

# BAGIAN 1 — SISTEM WAREHOUSE DAN PRODUKSI

## 1. HALAMAN RESEP BAHAN SETENGAH JADI

Buat atau perbarui halaman khusus Warehouse bernama:

**Resep Bahan Setengah Jadi**

Halaman ini digunakan untuk mengatur komposisi bahan mentah yang dibutuhkan untuk membuat produk setengah jadi.

Contoh produk setengah jadi:
- Dimsum topping wortel (belum dikukus/digoreng sesuai proses produksi).
- Chiquro Basic Mentah.
- Chiquro Cheese Mentah.
- Lumpia Isi Keju Mentah.
- Produk setengah jadi lainnya yang belum melalui tahap pengolahan akhir di outlet.

### A. Form Tambah / Edit Resep

Field yang dibutuhkan:

1. Nama bahan setengah jadi.
2. Satuan hasil produksi, seperti pcs atau satuan lain yang sesuai.
3. Jumlah hasil standar resep (contoh: resep ini menghasilkan 10 pcs).
4. Daftar bahan mentah yang digunakan.
5. Kuantitas masing-masing bahan mentah.
6. Satuan masing-masing bahan mentah.
7. Tombol untuk menambahkan baris bahan mentah secara dinamis.
8. Tombol untuk menghapus bahan mentah dari daftar.

**Ketentuan penting:**

- Nama bahan mentah tidak boleh diinput menggunakan teks bebas.
- Semua bahan mentah wajib dipilih melalui dropdown atau searchable dropdown dari tabel Master Bahan Mentah yang sudah tersedia.
- Simpan referensi menggunakan ID bahan mentah agar konsisten dengan master data.
- Satuan harus terhubung dengan satuan yang digunakan pada master bahan dan mendukung konversi satuan yang valid apabila diperlukan.
- Hindari bahan mentah yang sama terinput dua kali dalam satu resep.
- Tidak perlu menampilkan input harga pada halaman resep.
- Satu produk setengah jadi dapat menggunakan banyak bahan mentah.
- Sediakan fungsi tambah, edit, lihat detail, dan nonaktifkan resep.
- Perubahan resep tidak boleh mengubah histori produksi yang sudah selesai.

### B. Contoh Data Resep

Nama Produk: **Lumpia Isi Keju Mentah**

Hasil standar resep: 10 pcs.

| Bahan Mentah | Kebutuhan | Satuan |
|---|---:|---|
| Kulit Lumpia | 10 | pcs |
| Keju | 200 | gram |

Jika Warehouse ingin memproduksi 20 pcs, sistem otomatis menghitung:

- Kulit Lumpia: 20 pcs.
- Keju: 400 gram.

Formula:

Kebutuhan bahan = (Target produksi / Hasil standar resep) × Kuantitas bahan pada resep.

Semua perhitungan harus dilakukan secara otomatis menggunakan resep yang tersimpan.

---

## 2. HALAMAN PRODUKSI WAREHOUSE

Buat atau perbarui halaman bernama:

**Produksi Warehouse**

Saya ingin desain halaman ini mirip dengan sistem POS Kasir yang sudah ada, tetapi khusus untuk memilih dan memproduksi bahan setengah jadi.

### A. Memulai Shift Produksi

Warehouse harus dapat memulai shift produksi.

Saat shift dimulai, sistem mencatat:

- ID shift.
- Nama dan ID petugas Warehouse.
- Tanggal produksi.
- Jam mulai shift.
- Status shift.
- Daftar produksi yang dilakukan selama shift.

Satu shift dapat memiliki beberapa aktivitas produksi dengan produk dan jumlah berbeda.

### B. Memilih Produk untuk Diproduksi

Tampilkan daftar bahan setengah jadi dalam bentuk card atau katalog seperti POS.

Setiap produk menampilkan:
- Nama produk.
- Satuan produk.
- Input jumlah target produksi.
- Tombol tambah ke daftar produksi.

Ketika produk dipilih, sistem otomatis membaca resep dan menghitung seluruh kebutuhan bahan mentah.

Contoh:

Warehouse memilih:

**Lumpia Isi Keju Mentah — 20 pcs**

Sistem menampilkan:

| Bahan Mentah | Dibutuhkan | Stok Tersedia | Status |
|---|---:|---:|---|
| Kulit Lumpia | 20 pcs | 100 pcs | Cukup |
| Keju | 400 gram | 250 gram | Kurang 150 gram |

Status bahan:
- Cukup.
- Kurang.
- Habis.

Pemeriksaan stok harus mengambil data real-time dari stok bahan mentah gudang.

Jika satu shift memiliki beberapa rencana produksi, sistem harus menghitung total kebutuhan seluruh produk agar stok yang sama tidak dihitung tersedia berkali-kali.

### C. Jika Stok Bahan Mentah Cukup

Apabila semua bahan tersedia:

1. Warehouse dapat mengonfirmasi dan memulai produksi.
2. Sistem mencatat target jumlah produksi.
3. Sistem mencatat resep dan jumlah bahan mentah yang digunakan.
4. Sistem mengurangi stok bahan mentah secara otomatis ketika produksi benar-benar dikonfirmasi dimulai.
5. Pengurangan stok harus tercatat sebagai transaksi pemakaian bahan untuk produksi.
6. Status produksi berubah menjadi **Sedang Diproduksi**.

**PENTING:**
- Jangan mengurangi stok hanya karena produk dipilih atau dimasukkan ke daftar.
- Pengurangan stok dilakukan ketika tindakan Mulai Produksi berhasil dikonfirmasi.
- Cegah pengurangan stok ganda akibat tombol ditekan dua kali atau request dikirim ulang.
- Validasi ulang ketersediaan stok di backend saat transaksi dikonfirmasi.
- Jangan mengizinkan stok menjadi negatif.
- Jika proses produksi dibatalkan setelah bahan terpakai, jangan otomatis mengembalikan seluruh stok tanpa pencatatan. Sediakan mekanisme pembatalan atau penyesuaian yang dapat diaudit.

### D. Jika Stok Bahan Mentah Kurang

Apabila terdapat bahan mentah yang tidak mencukupi:

1. Tampilkan notifikasi bahwa stok tidak cukup.
2. Jelaskan bahan mana yang kurang.
3. Tampilkan jumlah stok tersedia dan jumlah tambahan yang dibutuhkan.
4. Jangan izinkan produksi dimulai sebelum seluruh bahan mencukupi.
5. Sediakan tombol **Request Restock ke Finance**.

Tombol tersebut membuka proses permintaan pembelian bahan mentah yang dijelaskan pada bagian berikutnya.

---

## 3. REQUEST RESTOCK BAHAN MENTAH KE FINANCE

Buat workflow permintaan restock yang terintegrasi antara Warehouse dan Finance.

### A. Pengajuan oleh Warehouse

Ketika stok kurang, Warehouse dapat membuat request berdasarkan rencana produksi.

Data yang harus tersimpan:

- Nomor request otomatis.
- Tanggal pengajuan.
- Nama Warehouse yang mengajukan.
- Nama produk setengah jadi yang ingin diproduksi.
- Target kuantitas produksi.
- Daftar bahan mentah yang diperlukan.
- Total kebutuhan setiap bahan.
- Stok tersedia saat pengecekan.
- Kuantitas kekurangan setiap bahan.
- Jumlah bahan yang diminta untuk dibeli.
- Status request.
- Catatan tambahan.

Apabila beberapa produk membutuhkan bahan mentah yang sama, kebutuhan restock dapat digabung berdasarkan ID bahan mentah untuk mencegah permintaan ganda.

Warehouse tidak perlu menginput harga pembelian.

Status awal:

**Menunggu Konfirmasi Finance**

### B. Persetujuan oleh Finance

Finance harus memiliki halaman untuk melihat seluruh request restock dari Warehouse.

Finance dapat melihat:
- Detail rencana produksi.
- Produk yang akan dibuat.
- Jumlah target produksi.
- Daftar bahan mentah.
- Jumlah kekurangan stok.
- Kuantitas pembelian yang diajukan.
- Harga satuan estimasi atau harga pembelian.
- Total biaya per bahan.
- Total keseluruhan biaya pembelian.

Finance dapat mengubah secara dinamis:
- Harga satuan bahan.
- Kuantitas pembelian yang disetujui.
- Catatan pembelian.
- Nominal biaya tambahan yang relevan, jika memang diperlukan.

Harga dapat berbeda dengan pembelian sebelumnya karena kondisi pasar.

Formula:

Subtotal bahan = Kuantitas pembelian × Harga satuan.

Total pengajuan = Jumlah seluruh subtotal + Biaya tambahan yang berlaku.

Sistem harus menghitung ulang total secara otomatis ketika Finance melakukan perubahan.

Finance mempunyai aksi:
- Setujui Request.
- Tolak Request.
- Ubah Detail Pembelian.

Jika ditolak, Finance wajib memberikan alasan agar Warehouse dapat melihatnya.

Semua perubahan harga dan kuantitas harus memiliki histori perubahan.

### C. Status Request Restock

Gunakan alur status:

**Menunggu Konfirmasi Finance → Stok Sedang Diantar → Telah Diterima**

Untuk request yang ditolak, gunakan status **Ditolak**.

Saat Finance menyetujui:
- Simpan siapa yang menyetujui.
- Simpan waktu persetujuan.
- Simpan harga dan total biaya yang disetujui.
- Ubah status menjadi Stok Sedang Diantar.

Status ini mengikuti kebutuhan operasional, tetapi tidak boleh dianggap bukti bahwa barang benar-benar telah dikirim secara fisik.

### D. Konfirmasi Penerimaan Barang oleh Warehouse

Ketika barang datang secara fisik, Warehouse harus mengonfirmasi penerimaan.

Tampilkan:
- Nomor request.
- Nama bahan mentah.
- Jumlah yang disetujui.
- Jumlah yang datang.
- Jumlah aktual yang diterima.
- Satuan.
- Catatan perbedaan atau kerusakan barang.

Warehouse menginput jumlah aktual yang diterima.

Setelah dikonfirmasi:
1. Sistem menambahkan jumlah aktual bahan ke stok gudang.
2. Mencatat transaksi stok masuk.
3. Menyimpan identitas penerima dan waktu penerimaan.
4. Mengubah status menjadi **Telah Diterima** apabila seluruh penerimaan selesai.

Jika jumlah yang datang belum lengkap, gunakan status tambahan **Diterima Sebagian** agar sistem tetap akurat.

Pastikan satu penerimaan barang tidak menambah stok dua kali.

### E. Akurasi Laporan Finance

Pisahkan dengan jelas:
- Estimasi nilai pengajuan.
- Nilai yang disetujui Finance.
- Nilai realisasi pembelian apabila diketahui.
- Jumlah barang aktual diterima.
- Selisih kuantitas dan biaya.

Jangan otomatis menganggap pengajuan yang disetujui sebagai pembelian yang sudah terealisasi.

Jika aplikasi memiliki modul pembelian atau pengeluaran Finance, integrasikan data ini tanpa menggandakan transaksi.

---

## 4. HASIL PRODUKSI AKTUAL DAN PENYELESAIAN SHIFT

Produksi di dunia nyata tidak selalu menghasilkan jumlah yang sama dengan target sistem.

Karena itu, Warehouse harus dapat menginput hasil produksi aktual.

### A. Penyelesaian Produksi

Contoh:

Target produksi sistem: 100 pcs Lumpia Isi Keju Mentah.

Hasil produksi aktual: 94 pcs.

Selisih: -6 pcs.

Data yang harus disimpan:
- ID produksi.
- Shift produksi.
- Nama produk.
- Target produksi.
- Hasil aktual.
- Selisih kuantitas.
- Persentase hasil produksi.
- Catatan selisih.
- Waktu selesai produksi.
- Petugas yang menyelesaikan produksi.

Formula:

Selisih = Hasil Aktual - Target Produksi.

Persentase Hasil = (Hasil Aktual / Target Produksi) × 100%.

Warehouse dapat memberikan catatan seperti:
- Bahan rusak.
- Produk gagal dibentuk.
- Hasil produksi kurang.
- Hasil produksi lebih.
- Penyebab lainnya.

### B. Penambahan Stok Bahan Setengah Jadi

Ketika produksi selesai dan hasil aktual dikonfirmasi:

1. Sistem menambahkan hasil aktual ke stok bahan setengah jadi di gudang.
2. Jangan menggunakan target produksi sebagai jumlah stok masuk.
3. Simpan transaksi stok masuk hasil produksi.
4. Hubungkan stok masuk dengan ID produksi dan ID shift.
5. Ubah status produksi menjadi **Selesai**.

Contoh:

Target: 100 pcs.

Aktual: 94 pcs.

Stok bahan setengah jadi bertambah **94 pcs**, bukan 100 pcs.

### C. Menutup Shift Warehouse

Warehouse dapat menutup shift setelah pekerjaan yang diperlukan diselesaikan.

Sebelum menutup shift, tampilkan ringkasan:
- Total aktivitas produksi.
- Produk yang dibuat.
- Total target produksi.
- Total aktual produksi.
- Selisih hasil.
- Bahan mentah yang digunakan.
- Produk gagal atau selisih.
- Jam mulai dan selesai shift.

Shift yang sudah ditutup tidak boleh diedit secara bebas. Koreksi setelah penutupan harus menggunakan mekanisme revisi atau penyesuaian dengan histori audit.

### D. Laporan Produksi untuk Finance

Finance harus dapat melihat hasil produksi Warehouse, tetapi dengan tampilan khusus laporan, bukan tampilan operasional produksi.

Finance dapat melihat:
- Tanggal dan periode produksi.
- Petugas Warehouse.
- ID shift.
- Produk yang diproduksi.
- Target produksi.
- Hasil aktual.
- Selisih.
- Persentase hasil.
- Pemakaian bahan mentah.
- Catatan produksi.

Sediakan filter berdasarkan tanggal, produk, shift, dan status.

Finance hanya dapat melihat laporan produksi dan detailnya, tanpa tombol untuk memulai atau menyelesaikan produksi.

---

# BAGIAN 2 — SISTEM TRANSFER STOK OUTLET

## 5. PERUBAHAN TOTAL ALUR TRANSFER STOK

Saat ini sistem sudah memiliki halaman Transfer Stock.

Saya ingin alurnya diubah.

**Alur lama:** Warehouse melakukan transfer stok ke Outlet.

**Alur baru:** Cashier / Outlet mengambil sendiri stok bahan setengah jadi dari gudang dan dapat mengembalikan sisa stok ke gudang.

Warehouse tetap dapat melihat histori dan memantau semua perpindahan stok.

### A. Halaman Transfer Stock pada Role Cashier

Gunakan tampilan halaman Transfer Stock yang sudah tersedia sebagai dasar desain, tetapi ubah logika dan hak aksesnya.

Tambahkan dua pilihan:
1. Ambil Stok dari Gudang.
2. Kembalikan Stok ke Gudang.

**PENTING:**
- Tidak perlu dropdown gudang sumber.
- Tidak perlu dropdown gudang tujuan.
- Sistem hanya menggunakan satu gudang bersama sebagai sumber dan tujuan stok.
- Akun Cashier sudah terhubung dengan outlet masing-masing.
- Sistem wajib mengambil outlet secara otomatis dari akun login.
- Cashier tidak boleh memilih atau memanipulasi outlet lain.

### B. Ambil Stok dari Gudang

Cashier dapat memilih bahan setengah jadi dan jumlah yang ingin diambil.

Contoh:

Cashier Outlet A mengambil:
- Lumpia Isi Keju Mentah: 30 pcs.
- Chiquro Basic Mentah: 20 pcs.
- Dimsum Topping Wortel: 40 pcs.

Ketika transaksi pengambilan dikonfirmasi:

1. Validasi ketersediaan stok bahan setengah jadi di gudang.
2. Kurangi stok gudang sesuai jumlah yang diambil.
3. Tambahkan stok ke outlet Cashier tersebut.
4. Simpan transaksi transfer.
5. Catat nama Cashier, outlet, tanggal, dan jam.
6. Perbarui riwayat stok kedua lokasi.

Contoh:

Stok Gudang: 100 pcs.

Outlet A mengambil: 30 pcs.

Hasil:
- Stok Gudang: 70 pcs.
- Stok Outlet A: bertambah 30 pcs.

Jika stok gudang tidak cukup, tampilkan notifikasi dan jangan izinkan transaksi melebihi stok tersedia.

### C. Pengembalian Stok dari Outlet

Cashier dapat mengembalikan bahan setengah jadi yang masih tersisa dan layak dikembalikan ke gudang.

Contoh:

Outlet A memiliki 10 pcs Lumpia Isi Keju Mentah yang tidak terpakai.

Cashier melakukan pengembalian sebanyak 10 pcs.

Saat dikonfirmasi:
- Stok Outlet A berkurang 10 pcs.
- Stok Gudang bertambah 10 pcs.
- Sistem mencatat transaksi return.
- Riwayat pengembalian tersimpan.

Pastikan:
- Jumlah pengembalian tidak boleh melebihi stok Outlet.
- Produk yang sudah terjual atau digunakan tidak boleh dikembalikan sebagai stok tersedia.
- Produk rusak atau tidak layak tidak otomatis ditambahkan ke stok gudang yang siap digunakan.
- Sediakan catatan dan alasan pengembalian.
- Pengembalian harus tetap terhubung dengan outlet dan Cashier terkait.

Gunakan validasi kondisi barang sebelum stok kembali dihitung sebagai stok yang dapat digunakan. Jika dibutuhkan pemeriksaan fisik oleh Warehouse, gunakan status Menunggu Pemeriksaan sebelum stok dikembalikan ke stok tersedia.

### D. Integrasi dengan POS

Stok setengah jadi yang sudah diambil Outlet harus tercatat sebagai stok milik Outlet tersebut.

Pastikan sistem dapat membedakan:
- Stok bahan mentah di gudang.
- Stok bahan setengah jadi di gudang.
- Stok bahan setengah jadi di outlet.

Integrasikan dengan logika penggunaan atau pengurangan stok pada POS yang sudah berjalan agar tidak terjadi pengurangan atau penambahan stok ganda.

Semua transfer wajib dicatat secara atomik: pengurangan di lokasi asal dan penambahan di lokasi tujuan harus sama-sama berhasil atau sama-sama dibatalkan.

### E. Monitoring oleh Warehouse

Warehouse memiliki halaman riwayat transfer untuk melihat:
- Outlet yang mengambil stok.
- Cashier yang melakukan transaksi.
- Produk.
- Kuantitas.
- Tanggal dan waktu.
- Jenis transaksi: pengambilan atau pengembalian.
- Status transaksi.

Gunakan alur pengambilan langsung ketika Cashier mengonfirmasi pengambilan sesuai kebutuhan ini. Jangan menambahkan proses approval Warehouse yang tidak diperlukan.

---

# BAGIAN 3 — SISTEM SERAH TERIMA UANG TUNAI

## 6. SERAH TERIMA KAS DARI CASHIER KE WAREHOUSE

Dalam operasional bisnis, Cashier menyerahkan uang tunai hasil omzet harian kepada Warehouse.

Saya ingin sistem mencatat uang yang benar-benar sudah diterima dan sedang dipegang Warehouse.

### A. Pengajuan oleh Cashier

Cashier memiliki halaman:

**Serah Terima Kas ke Warehouse**

Data yang diinput atau ditampilkan:
- Outlet otomatis dari akun Cashier.
- Nama Cashier.
- Tanggal transaksi.
- Shift kasir jika tersedia.
- Nominal tunai yang akan diserahkan.
- Catatan.
- Nomor transaksi otomatis.

Jika sistem POS sudah mempunyai data kas atau hasil penjualan, gunakan data tersebut untuk membantu menghitung nominal yang seharusnya diserahkan.

Nominal penyerahan tetap harus mewakili uang tunai, bukan total seluruh omzet yang mungkin mencakup pembayaran digital.

Setelah Cashier mengajukan, status:

**Menunggu Konfirmasi Warehouse**

### B. Konfirmasi oleh Warehouse

Warehouse melihat daftar serah terima kas yang diajukan Cashier.

Warehouse menerima uang secara fisik dan menghitungnya.

Tampilkan:
- Nama Outlet.
- Nama Cashier.
- Nominal yang diajukan.
- Input nominal uang aktual yang diterima.
- Selisih nominal.
- Catatan pemeriksaan.

Jika sesuai, Warehouse mengonfirmasi penerimaan.

Status berubah menjadi:

**Telah Diterima Warehouse**

Jika ada perbedaan, sistem wajib mencatat selisih dan alasan serta menyediakan proses konfirmasi selisih sebelum transaksi diselesaikan.

Jangan otomatis menganggap nominal pengajuan Cashier sebagai uang yang sudah diterima Warehouse.

### C. Saldo Uang Tunai yang Dipegang Warehouse

Buat pencatatan saldo kas Warehouse berdasarkan transaksi yang benar-benar sudah diterima.

Warehouse harus dapat melihat:
- Total uang tunai yang sedang dipegang.
- Total penerimaan dari Outlet hari ini.
- Total per Outlet.
- Total uang yang sudah diserahkan ke Finance.
- Riwayat transaksi penerimaan.
- Riwayat transaksi penyerahan.

Contoh:

| Outlet | Uang Diterima Warehouse |
|---|---:|
| Outlet A | Rp1.500.000 |
| Outlet B | Rp2.000.000 |
| Outlet C | Rp1.250.000 |
| Total | Rp4.750.000 |

Uang dari transaksi yang belum dikonfirmasi Warehouse tidak boleh masuk ke saldo kas Warehouse.

Gunakan pencatatan mutasi kas agar saldo dapat ditelusuri, bukan hanya menyimpan angka total yang bisa ditimpa.

---

## 7. SERAH TERIMA KAS DARI WAREHOUSE KE FINANCE

Buat sistem serah terima uang antara Warehouse dan Finance.

### A. Pengajuan oleh Warehouse

Warehouse dapat membuat transaksi penyerahan uang kepada Finance.

Field:
- Nomor transaksi.
- Nama Warehouse.
- Tanggal dan waktu.
- Nominal yang diserahkan.
- Rincian sumber dana per Outlet jika tersedia.
- Catatan penyerahan.

Nominal yang diajukan tidak boleh melebihi uang tunai yang tersedia untuk diserahkan oleh Warehouse.

Status awal:

**Menunggu Konfirmasi Finance**

### B. Konfirmasi oleh Finance

Finance menerima uang fisik dan memverifikasinya.

Finance dapat melihat:
- Nama Warehouse yang menyerahkan.
- Total nominal pengajuan.
- Rincian asal dana.
- Input nominal aktual yang diterima.
- Selisih nominal.
- Catatan verifikasi.
- Tanggal penerimaan.

Jika nominal sesuai, Finance mengonfirmasi transaksi.

Status berubah menjadi:

**Telah Diterima Finance**

Jika nominal tidak sesuai, catat selisih dan proses penyelesaiannya. Jangan diam-diam mengubah nominal pengajuan asli.

### C. Perhitungan Saldo Warehouse

Saldo kas Warehouse harus diperbarui secara otomatis berdasarkan penerimaan dan penyerahan yang telah dikonfirmasi.

Contoh:

Total uang diterima dari Outlet: Rp5.000.000.

Uang diserahkan dan diterima Finance: Rp3.000.000.

Saldo uang yang masih dipegang Warehouse: Rp2.000.000.

Formula:

**Saldo Kas Warehouse = Total Kas Diterima dari Outlet - Total Kas Diserahkan ke Finance + / - Penyesuaian Kas yang Sah.**

Request penyerahan yang sedang menunggu konfirmasi Finance harus dipisahkan sebagai dana dalam proses serah terima, supaya uang yang sama tidak dapat diajukan dua kali.

Sistem harus membedakan:
- Kas tersedia di Warehouse.
- Kas sedang dalam proses serah terima.
- Kas sudah dikonfirmasi diterima Finance.
- Selisih kas yang belum diselesaikan.

### D. Laporan Serah Terima Kas

Warehouse dan Finance dapat melihat laporan sesuai hak akses masing-masing.

Laporan mencakup:
- Tanggal transaksi.
- Outlet asal.
- Nama Cashier.
- Nama Warehouse penerima.
- Nama Finance penerima.
- Jumlah pengajuan.
- Jumlah aktual diterima.
- Selisih.
- Status.
- Waktu konfirmasi.

Setiap serah terima harus memiliki ID unik dan histori lengkap.

Jangan menghitung uang yang sama sebagai pemasukan baru dua kali. Perpindahan uang dari Cashier ke Warehouse lalu dari Warehouse ke Finance merupakan perpindahan tanggung jawab atau lokasi kas, bukan tambahan omzet.

---

# BAGIAN 4 — HAK AKSES DAN MENU SETIAP ROLE

Atur role-based access control berdasarkan user yang login.

## ROLE WAREHOUSE

Menu:
1. Dashboard Warehouse.
2. Master / Resep Bahan Setengah Jadi.
3. Produksi Warehouse.
4. Shift Produksi.
5. Stok Bahan Mentah.
6. Stok Bahan Setengah Jadi.
7. Request Restock.
8. Penerimaan Bahan Mentah.
9. Riwayat Transfer Stok Outlet.
10. Penerimaan Kas dari Outlet.
11. Serah Terima Kas ke Finance.
12. Laporan dan Riwayat Warehouse.

Warehouse dapat mengelola produksi dan stok, tetapi tidak dapat menyetujui request pembelian miliknya sendiri sebagai Finance.

## ROLE CASHIER / OUTLET

Menu tambahan:
1. Ambil Stok dari Gudang.
2. Kembalikan Stok ke Gudang.
3. Riwayat Transfer Stok.
4. Serah Terima Kas ke Warehouse.
5. Riwayat Serah Terima Kas.

Cashier hanya boleh mengakses transaksi dan data outlet miliknya, kecuali memang telah memiliki izin khusus pada sistem existing.

## ROLE FINANCE

Menu:
1. Request Restock Warehouse.
2. Approval dan Pengelolaan Harga Pembelian.
3. Riwayat Penerimaan Bahan Mentah.
4. Laporan Hasil Produksi Warehouse.
5. Serah Terima Kas dari Warehouse.
6. Laporan Kas Warehouse dan Outlet.
7. Riwayat Transaksi dan Audit.

Finance hanya melihat laporan produksi, bukan mengoperasikan shift produksi.

Seluruh pembatasan akses harus divalidasi di backend, bukan hanya menyembunyikan tombol di frontend.

---

# BAGIAN 5 — KETENTUAN TEKNIS SISTEM

## 8. INTEGRASI DATABASE DAN TRANSAKSI

Pelajari terlebih dahulu skema database existing.

Gunakan tabel dan relasi yang sudah tersedia apabila sesuai. Buat migration baru hanya apabila diperlukan.

Pastikan seluruh data saling terhubung melalui ID yang valid.

Entitas data yang harus didukung secara konseptual antara lain:
- Master bahan mentah.
- Master bahan setengah jadi.
- Resep dan detail resep.
- Shift Warehouse.
- Aktivitas produksi.
- Pemakaian bahan produksi.
- Hasil aktual produksi.
- Stok dan histori mutasi stok.
- Request restock dan detailnya.
- Approval Finance.
- Penerimaan bahan mentah.
- Transfer stok Warehouse ↔ Outlet.
- Pengajuan serah terima kas.
- Penerimaan kas.
- Mutasi atau ledger kas.
- Audit log.

Jangan membuat tabel duplikat jika data tersebut sudah tersedia di database.

## 9. VALIDASI DAN KONSISTENSI

Pastikan sistem menangani kondisi berikut:

1. Stok tidak boleh menjadi negatif.
2. Request yang sama tidak boleh dieksekusi dua kali.
3. Penerimaan bahan tidak boleh menambah stok dua kali.
4. Produk yang belum memiliki resep valid tidak boleh diproduksi.
5. Produksi tidak boleh dilakukan jika total stok bahan tidak cukup.
6. Semua perhitungan satuan bahan harus konsisten.
7. Semua perubahan stok memiliki histori mutasi.
8. Semua perpindahan uang memiliki histori mutasi.
9. Semua perubahan nominal oleh Finance tercatat.
10. Seluruh transaksi yang saling bergantung harus menggunakan database transaction.
11. Data dari UI harus divalidasi ulang oleh backend.
12. Data historis tidak berubah akibat perubahan master atau resep.
13. Sistem harus aman dari transaksi bersamaan oleh beberapa pengguna.
14. Semua perhitungan uang harus menggunakan tipe data desimal yang akurat, bukan floating point biasa.
15. Transaksi yang sudah final tidak boleh dihapus atau diubah sembarangan tanpa proses koreksi yang tercatat.

## 10. TAMPILAN UI/UX

Pertahankan gaya desain aplikasi yang sudah ada.

Saya ingin:
- Desain modern dan konsisten.
- Mudah digunakan petugas operasional.
- Responsif untuk desktop, tablet, dan mobile.
- Input jumlah cepat dan praktis.
- Searchable dropdown bahan mentah.
- Katalog produk setengah jadi seperti POS.
- Tabel yang rapi dengan filter pencarian.
- Badge status transaksi.
- Modal konfirmasi sebelum stok atau kas berubah.
- Notifikasi validasi yang jelas.
- Loading, empty state, dan error state yang informatif.
- Laporan yang mudah dibaca.
- Navigasi menu sesuai role.

Jangan mengganti seluruh visual aplikasi jika komponen existing masih dapat digunakan.

---

# BAGIAN 6 — ALUR SISTEM YANG HARUS BERFUNGSI

## FLOW A — PRODUKSI NORMAL

Warehouse Login → Mulai Shift → Pilih Produk Setengah Jadi → Input Target Jumlah → Sistem Hitung Resep → Sistem Cek Stok → Stok Cukup → Konfirmasi Mulai Produksi → Stok Bahan Mentah Berkurang → Input Hasil Aktual → Konfirmasi Selesai → Stok Bahan Setengah Jadi Bertambah Sesuai Aktual → Tutup Shift → Finance Dapat Melihat Laporan.

## FLOW B — PRODUKSI DENGAN STOK KURANG

Warehouse Pilih Produk → Sistem Hitung Kebutuhan Bahan → Stok Kurang → Buat Request Restock → Finance Melihat Detail → Finance Mengatur Harga dan Kuantitas → Finance Menyetujui → Status Stok Sedang Diantar → Barang Tiba → Warehouse Input Jumlah Aktual Diterima → Stok Bahan Mentah Bertambah → Status Telah Diterima → Warehouse Mengecek Ulang Stok → Produksi Dapat Dimulai.

## FLOW C — CASHIER MENGAMBIL STOK

Cashier Login → Sistem Identifikasi Outlet → Buka Ambil Stok → Pilih Produk Setengah Jadi → Input Jumlah → Sistem Cek Stok Gudang → Konfirmasi Pengambilan → Stok Gudang Berkurang → Stok Outlet Bertambah → Riwayat Transfer Tersimpan.

## FLOW D — CASHIER MENGEMBALIKAN STOK

Cashier Login → Buka Pengembalian Stok → Pilih Produk → Input Jumlah dan Kondisi → Sistem Validasi → Konfirmasi Pengembalian → Barang Layak Diterima Kembali → Stok Outlet Berkurang → Stok Gudang Bertambah → Riwayat Pengembalian Tersimpan.

Jika barang memerlukan pemeriksaan Warehouse, perubahan menjadi stok gudang yang tersedia dilakukan setelah pemeriksaan disetujui.

## FLOW E — SERAH TERIMA KAS OUTLET KE WAREHOUSE

Cashier Mengajukan Penyerahan Kas → Status Menunggu Konfirmasi Warehouse → Warehouse Menghitung Uang Fisik → Warehouse Input Nominal Aktual → Konfirmasi Penerimaan → Status Telah Diterima Warehouse → Saldo Kas Warehouse Bertambah → Transaksi Tercatat Berdasarkan Outlet.

## FLOW F — SERAH TERIMA KAS WAREHOUSE KE FINANCE

Warehouse Melihat Saldo Kas → Mengajukan Penyerahan → Dana Dicatat Sedang Dalam Proses → Finance Menghitung Uang Fisik → Finance Mengonfirmasi Nominal Aktual → Status Telah Diterima Finance → Saldo Kas Warehouse dan Ledger Diperbarui → Riwayat Transaksi Tersimpan.

---

# BAGIAN 7 — INSTRUKSI PENGERJAAN UNTUK AI DEVELOPER

Saya ingin perubahan ini benar-benar diimplementasikan pada sistem existing.

Kerjakan dengan urutan berikut:

### Tahap 1 — Analisis Sistem Existing

- Periksa struktur project.
- Periksa database dan hubungan tabel.
- Periksa authentication dan role permission.
- Pelajari modul Warehouse, Finance, Cashier, POS, stok, transfer, dan kas yang sudah ada.
- Identifikasi fitur yang dapat digunakan kembali.
- Identifikasi konflik atau bagian yang perlu dimigrasikan.

### Tahap 2 — Perancangan Sistem

- Tentukan perubahan skema database.
- Tentukan integrasi antar modul.
- Definisikan seluruh status dan transisinya.
- Definisikan logika stok masuk/keluar.
- Definisikan logika saldo dan perpindahan kas.
- Tentukan kebutuhan endpoint/API dan hak akses.

### Tahap 3 — Implementasi

Implementasikan:
- Backend dan API.
- Database migration.
- Validasi dan transaction handling.
- Frontend untuk Warehouse.
- Frontend untuk Cashier.
- Frontend untuk Finance.
- Laporan serta audit log.

### Tahap 4 — Pengujian

Uji minimal:
- Pembuatan dan perubahan resep.
- Perhitungan bahan berdasarkan target.
- Produksi dengan stok cukup.
- Produksi dengan stok kurang.
- Pengajuan dan approval restock.
- Perubahan harga oleh Finance.
- Penerimaan bahan parsial dan penuh.
- Pencatatan hasil produksi aktual.
- Penambahan stok hasil produksi.
- Pengambilan dan pengembalian stok Outlet.
- Serah terima kas Cashier ke Warehouse.
- Serah terima kas Warehouse ke Finance.
- Validasi selisih uang.
- Pengurangan dan penambahan stok agar tidak terjadi