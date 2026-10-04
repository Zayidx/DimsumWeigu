# Pemindahan seeder Dimsum Weigu

Sumber: `seedertamplate/seeders` dan repository `C:\Users\zayidx\Documents\point-of-sales`. Semua 15 file template cocok dengan file seeder repository sumber pada saat audit.

Menu, biaya, resep dan stok dari TestDataSeeder merupakan **simulasi lokal/uji**, bukan angka produksi atau histori transaksi aktual. Tidak ada password, hash, token atau kredensial sumber yang dipindahkan.

## Menjalankan

```powershell
php artisan migrate --force
php artisan db:seed --force
php tools/source-seeder-report.php
```

DatabaseSeeder memanggil ApplicationPermissionSeeder, PermissionSeeder, RoleSeeder, DimsumWeiguSeeder. UserSeeder dan TestDataSeeder otomatis hanya pada APP_ENV=local; lingkungan testing dapat memanggilnya eksplisit. TestDataSeeder menolak production. Gunakan database baru proyek ini; jangan menjalankan perintah tersebut di repository sumber. Migrasi normal mempertahankan data; migrasi awal dapat melanjutkan DDL MySQL yang terputus hanya atas tabel kosong.

## Pemetaan dan pengecualian

| Sumber | Implementasi baru / perbedaan |
|---|---|
| DimsumWeiguSeeder | Profil, 4 outlet, 5 gudang, kategori, satuan dan konversi; firstOrCreate mempertahankan penyesuaian existing |
| TestDataSeeder | 13 menu, 28 product_units, 32 versi resep, 79 item resep, 13 bahan dan saldo awal PUSAT |
| UserSeeder + OutletCashierSeeder | Digabung dalam UserSeeder: 8 akun; akun existing tidak diubah |
| PermissionSeeder + RoleSeeder | Permission sumber dipertahankan; alias action baru ditambahkan; role existing tidak di-reset; manager tetap baca saja |
| PaymentSettingSeeder | Provider global Midtrans/Xendit sandbox nonaktif tanpa kredensial; default_gateway=cash |
| DineInSettingsSeeder | Pengaturan dine-in/self-order aktif, online payment nonaktif |
| DemoSeeder, DemoOutletSeeder | Tidak dipanggil: akun/outlet demonstrasi tidak menjadi data bisnis Weigu |
| FeatureDemoSeeder, FeatureCoverageSeeder, OperationalCoreSeeder, SampleDataSeeder | Tidak dipindahkan/dipanggil: simulasi histori, factory/sample, penghapusan/reset dan dependensi workflow lama memerlukan adaptasi terpisah |
| unit.code / conversion_factor | Kode ditambahkan; konversi ke unit_conversions.factor. PAK dan PKT tetap berbeda walaupun sama-sama bersimbol paket |
| ingredient.base_unit_id / default_unit_cost | ingredients.unit_id / buy_price; description tetap tersimpan |
| recipe.version_number | recipe_versions.version; yield_quantity, notes, created_by, item unit_id/base_quantity ditambahkan |
| recipe.unit_id null pada resep dasar | Diisi satuan dasar menu karena schema baru mewajibkan unit |
| product_units.is_base / buy_price / sku_suffix | Ditambahkan; is_default mengikuti is_base; base unit juga disimpan pada products.unit_id |
| Gudang PUSAT type main + outlet PUSAT | type central, outlet_id null, bukan lokasi penjualan. Finance/warehouse terhubung ke empat cabang; akses PUSAT memakai warehouses-access melalui policy gudang baru, tanpa pivot user_warehouse |
| Kategori pengeluaran global | Enam kategori direplikasi per outlet, total 24 |
| Metode CASH, QRIS-1, GOFOOD | cash, qris, gofood mengikuti kode aplikasi baru; QRIS/GoFood membutuhkan konfirmasi. Gateway tetap nonaktif |
| Alias kategori bahan lama / bahan obsolete | Tidak menghapus atau memindahkan referensi existing secara otomatis |
| Stok awal | Hanya bahan di PUSAT; aggregate sama dengan saldo gudang; ledger idempotent. Saldo yang sudah mempunyai stok/ledger dilewati. Tidak membuat stok produk/cabang |
| Histori transaksi / runtime | Tidak disalin; cache, session, jobs dan log sementara tidak disertakan |

**Batas akses:** permission sumber untuk cash-handovers-confirm/cash-pickups-confirm, outlet-stock-returns, recipes/import/export dan action lain yang belum mempunyai padanan workflow tetap tercatat. Alias tidak memberikan hak cash-management-create kepada warehouse, ataupun hak menulis sales return kepada manager. Paritas seluruh modul lama belum tercapai.

## Jumlah data

| Tabel | Jumlah |
|---|---:|
| outlets | 4 |
| warehouses | 5 |
| categories | 4 |
| units | 16 |
| unit_conversions | 4 |
| products | 13 |
| product_units | 28 |
| ingredient_categories | 10 |
| ingredients | 13 |
| recipe_versions | 32 |
| recipe_items | 79 |
| ingredient_warehouses | 13 |
| ingredient_inventory_ledgers | 13 |
| users | 8 |
| user_outlets | 20 |
| roles | 54 |
| permissions | 215 |
| expense_categories | 24 |
| payment_methods | 3 |
| payment_settings | 2 |
| transactions | 0 |

## Menu dan harga jual

Harga rupiah; berlaku global tanpa periode promo/outlet khusus pada TestDataSeeder sumber.

| SKU | Menu | Satuan dasar / harga | Variasi satuan, faktor, harga |
|---|---|---|---|
| TEST-DOR | Dimsum Ori | PCS / Rp4.500 | P4 (x4) Rp16.000; P6 (x6) Rp24.000; P8 (x8) Rp30.000 |
| TEST-DME | Dimsum Mentai | PCS / Rp5.500 | P4 (x4) Rp21.000; P6 (x6) Rp28.000; P8 (x8) Rp38.000 |
| TEST-DCH | Dimsum Cheesemelt | PCS / Rp6.500 | P4 (x4) Rp25.000; P6 (x6) Rp35.000; P8 (x8) Rp48.000 |
| TEST-DMX | Dimsum Mix | PCS / Rp6.000 | P6 (x6) Rp30.000; P8 (x8) Rp45.000 |
| TEST-LOR | Lumpia Original | PCS / Rp9.000 | P3 (x3) Rp24.000; P5 (x5) Rp36.000 |
| TEST-LME | Lumpia Mentai | PCS / Rp10.000 | P3 (x3) Rp28.000; P5 (x5) Rp40.000 |
| TEST-CGA | Chiquro Garlic | PCS / Rp26.000 | — |
| TEST-CLA | Chiquro Lava | PCS / Rp26.000 | — |
| TEST-CME | Chiquro Mentai | PCS / Rp26.000 | — |
| TEST-CCH | Chiquro Cheese | PCS / Rp28.000 | — |
| TEST-BMX | Paket Besar Dimsum Mix | PKT / Rp90.000 | — |
| TEST-BME | Paket Besar Dimsum Mentai | PKT / Rp85.000 | — |
| TEST-BCH | Paket Besar Dimsum Cheesemelt | PKT / Rp100.000 | — |

## Resep/BOM

**Resep dasar v1 dan profil kemasan v2–v4 merupakan versi terpisah seperti sumber. Profil kemasan bukan resep produksi lengkap dan tidak digabung otomatis dengan bahan resep dasar. Dimsum Mix mempunyai resep kemasan P4, tetapi sumber tidak menyediakan harga jual P4; harga tidak dibuat.**

| Menu | Versi | Hasil / satuan | Pemakaian | Catatan sumber |
|---|---:|---|---|---|
| TEST-DOR | 1 | 1 PCS | TEST-FROZEN-DIMSUM: 1 PCS (base 1) | [DATA UJI] Dimsum frozen disiapkan kukus tanpa topping. |
| TEST-DOR | 2 | 1 P4 | TEST-BOX-KECIL: 1 PCS (base 1); TEST-SUMPIT: 1 PCS (base 1); TEST-CHILI-OIL: 50 GRAM (base 50) | [DATA UJI] Per porsi: 1 box, 1 sumpit, chili oil 50 gram. |
| TEST-DOR | 3 | 1 P6 | TEST-BOX-BESAR: 1 PCS (base 1); TEST-SUMPIT: 1 PCS (base 1); TEST-CHILI-OIL: 50 GRAM (base 50) | [DATA UJI] Per porsi: 1 box, 1 sumpit, chili oil 50 gram. |
| TEST-DOR | 4 | 1 P8 | TEST-BOX-BESAR: 1 PCS (base 1); TEST-SUMPIT: 1 PCS (base 1); TEST-CHILI-OIL: 50 GRAM (base 50) | [DATA UJI] Per porsi: 1 box, 1 sumpit, chili oil 50 gram. |
| TEST-DME | 1 | 1 PCS | TEST-FROZEN-DIMSUM: 1 PCS (base 1); TEST-SAUS-MENTAI: 5 GRAM (base 5) | [DATA UJI] Dimsum frozen dengan saus mentai siap pakai; gramasi simulasi. |
| TEST-DME | 2 | 1 P4 | TEST-BOX-KECIL: 1 PCS (base 1); TEST-SUMPIT: 1 PCS (base 1); TEST-CHILI-OIL: 50 GRAM (base 50) | [DATA UJI] Per porsi: 1 box, 1 sumpit, chili oil 50 gram. |
| TEST-DME | 3 | 1 P6 | TEST-BOX-BESAR: 1 PCS (base 1); TEST-SUMPIT: 1 PCS (base 1); TEST-CHILI-OIL: 50 GRAM (base 50) | [DATA UJI] Per porsi: 1 box, 1 sumpit, chili oil 50 gram. |
| TEST-DME | 4 | 1 P8 | TEST-BOX-BESAR: 1 PCS (base 1); TEST-SUMPIT: 1 PCS (base 1); TEST-CHILI-OIL: 50 GRAM (base 50) | [DATA UJI] Per porsi: 1 box, 1 sumpit, chili oil 50 gram. |
| TEST-DCH | 1 | 1 PCS | TEST-FROZEN-DIMSUM: 1 PCS (base 1); TEST-SAUS-CHEESE: 4 GRAM (base 4) | [DATA UJI] Dimsum frozen dengan saus keju siap pakai; gramasi simulasi. |
| TEST-DCH | 2 | 1 P4 | TEST-BOX-KECIL: 1 PCS (base 1); TEST-SUMPIT: 1 PCS (base 1); TEST-CHILI-OIL: 50 GRAM (base 50) | [DATA UJI] Per porsi: 1 box, 1 sumpit, chili oil 50 gram. |
| TEST-DCH | 3 | 1 P6 | TEST-BOX-BESAR: 1 PCS (base 1); TEST-SUMPIT: 1 PCS (base 1); TEST-CHILI-OIL: 50 GRAM (base 50) | [DATA UJI] Per porsi: 1 box, 1 sumpit, chili oil 50 gram. |
| TEST-DCH | 4 | 1 P8 | TEST-BOX-BESAR: 1 PCS (base 1); TEST-SUMPIT: 1 PCS (base 1); TEST-CHILI-OIL: 50 GRAM (base 50) | [DATA UJI] Per porsi: 1 box, 1 sumpit, chili oil 50 gram. |
| TEST-DMX | 1 | 1 PCS | TEST-FROZEN-DIMSUM: 1 PCS (base 1) | [DATA UJI] Campuran dimsum frozen; rasio simulasi. |
| TEST-DMX | 2 | 1 P4 | TEST-BOX-KECIL: 1 PCS (base 1); TEST-SUMPIT: 1 PCS (base 1); TEST-CHILI-OIL: 50 GRAM (base 50) | [DATA UJI] Per porsi: 1 box, 1 sumpit, chili oil 50 gram. |
| TEST-DMX | 3 | 1 P6 | TEST-BOX-BESAR: 1 PCS (base 1); TEST-SUMPIT: 1 PCS (base 1); TEST-CHILI-OIL: 50 GRAM (base 50) | [DATA UJI] Per porsi: 1 box, 1 sumpit, chili oil 50 gram. |
| TEST-DMX | 4 | 1 P8 | TEST-BOX-BESAR: 1 PCS (base 1); TEST-SUMPIT: 1 PCS (base 1); TEST-CHILI-OIL: 50 GRAM (base 50) | [DATA UJI] Per porsi: 1 box, 1 sumpit, chili oil 50 gram. |
| TEST-LOR | 1 | 1 PCS | TEST-FROZEN-LUMPIA: 1 PCS (base 1) | [DATA UJI] Satu lumpia frozen digoreng. |
| TEST-LOR | 2 | 1 P3 | TEST-BOX-BESAR: 1 PCS (base 1); TEST-SUMPIT: 1 PCS (base 1); TEST-CHILI-OIL: 50 GRAM (base 50) | [DATA UJI] Per porsi: 1 box, 1 sumpit, chili oil 50 gram. |
| TEST-LOR | 3 | 1 P5 | TEST-BOX-BESAR: 1 PCS (base 1); TEST-SUMPIT: 1 PCS (base 1); TEST-CHILI-OIL: 50 GRAM (base 50) | [DATA UJI] Per porsi: 1 box, 1 sumpit, chili oil 50 gram. |
| TEST-LME | 1 | 1 PCS | TEST-FROZEN-LUMPIA: 1 PCS (base 1); TEST-SAUS-MENTAI: 5 GRAM (base 5) | [DATA UJI] Lumpia frozen dengan saus mentai siap pakai; gramasi simulasi. |
| TEST-LME | 2 | 1 P3 | TEST-BOX-BESAR: 1 PCS (base 1); TEST-SUMPIT: 1 PCS (base 1); TEST-CHILI-OIL: 50 GRAM (base 50) | [DATA UJI] Per porsi: 1 box, 1 sumpit, chili oil 50 gram. |
| TEST-LME | 3 | 1 P5 | TEST-BOX-BESAR: 1 PCS (base 1); TEST-SUMPIT: 1 PCS (base 1); TEST-CHILI-OIL: 50 GRAM (base 50) | [DATA UJI] Per porsi: 1 box, 1 sumpit, chili oil 50 gram. |
| TEST-CGA | 1 | 1 PCS | TEST-FROZEN-CHIQURO: 1 PCS (base 1); TEST-SAUS-GARLIC: 5 GRAM (base 5) | [DATA UJI] Chiquro frozen dengan saus garlic siap pakai; gramasi simulasi. |
| TEST-CLA | 1 | 1 PCS | TEST-FROZEN-CHIQURO: 1 PCS (base 1); TEST-SAUS-LAVA: 6 GRAM (base 6) | [DATA UJI] Chiquro frozen dengan saus lava siap pakai; gramasi simulasi. |
| TEST-CME | 1 | 1 PCS | TEST-FROZEN-CHIQURO: 1 PCS (base 1); TEST-SAUS-MENTAI: 5 GRAM (base 5) | [DATA UJI] Chiquro frozen dengan saus mentai siap pakai; gramasi simulasi. |
| TEST-CCH | 1 | 1 PCS | TEST-FROZEN-CHIQURO: 1 PCS (base 1); TEST-SAUS-CHEESE: 5 GRAM (base 5) | [DATA UJI] Chiquro frozen dengan saus keju siap pakai; gramasi simulasi. |
| TEST-BMX | 1 | 1 PKT | TEST-FROZEN-DIMSUM: 20 PCS (base 20) | [DATA UJI] Paket besar simulasi; komposisi aktual perlu disesuaikan. |
| TEST-BMX | 2 | 1 PKT | TEST-BOX-BESAR: 1 PCS (base 1); TEST-SUMPIT: 1 PCS (base 1); TEST-CHILI-OIL: 50 GRAM (base 50) | [DATA UJI] Per porsi: 1 box, 1 sumpit, chili oil 50 gram. |
| TEST-BME | 1 | 1 PKT | TEST-FROZEN-DIMSUM: 20 PCS (base 20); TEST-SAUS-MENTAI: 100 GRAM (base 100) | [DATA UJI] Paket besar simulasi; komposisi aktual perlu disesuaikan. |
| TEST-BME | 2 | 1 PKT | TEST-BOX-BESAR: 1 PCS (base 1); TEST-SUMPIT: 1 PCS (base 1); TEST-CHILI-OIL: 50 GRAM (base 50) | [DATA UJI] Per porsi: 1 box, 1 sumpit, chili oil 50 gram. |
| TEST-BCH | 1 | 1 PKT | TEST-FROZEN-DIMSUM: 20 PCS (base 20); TEST-SAUS-CHEESE: 80 GRAM (base 80) | [DATA UJI] Paket besar simulasi; komposisi aktual perlu disesuaikan. |
| TEST-BCH | 2 | 1 PKT | TEST-BOX-BESAR: 1 PCS (base 1); TEST-SUMPIT: 1 PCS (base 1); TEST-CHILI-OIL: 50 GRAM (base 50) | [DATA UJI] Per porsi: 1 box, 1 sumpit, chili oil 50 gram. |

## Bahan dan stok awal PUSAT

| Kode | Bahan | Satuan | Biaya simulasi per satuan | Saldo saat laporan |
|---|---|---|---:|---:|
| TEST-FROZEN-DIMSUM | Dimsum frozen | PCS | 1600.00 | 3000.000 |
| TEST-FROZEN-LUMPIA | Lumpia frozen | PCS | 2800.00 | 3000.000 |
| TEST-FROZEN-CHIQURO | Chiquro frozen | PCS | 8000.00 | 3000.000 |
| TEST-SAUS-MENTAI | Saus mentai siap pakai | GRAM | 0.20 | 5000.000 |
| TEST-SAUS-GARLIC | Saus garlic siap pakai | GRAM | 0.15 | 1500.000 |
| TEST-SAUS-LAVA | Saus lava siap pakai | GRAM | 0.18 | 2000.000 |
| TEST-SAUS-CHEESE | Saus keju siap pakai | GRAM | 0.22 | 4000.000 |
| TEST-CHILI-OIL | Chili oil siap pakai | GRAM | 0.18 | 5000.000 |
| TEST-CUP-SAUS-PCS | Cup saus | PCS | 120.00 | 500.000 |
| TEST-BOX-KECIL | Box kecil | PCS | 250.00 | 500.000 |
| TEST-BOX-BESAR | Box besar | PCS | 450.00 | 250.000 |
| TEST-MINYAK-LITER | Minyak goreng | LITER | 20000.00 | 15.000 |
| TEST-SUMPIT | Sumpit | PCS | 100.00 | 1000.000 |

## Akun dan akses

Password sumber tidak dipakai. Akun baru memakai password acak yang tidak ditampilkan; administrator dapat memakai SUPER_ADMIN_PASSWORD dari konfigurasi proyek baru. Semua akun baru wajib mengganti password. Gunakan /forgot-password dengan pengiriman email yang telah dikonfigurasi. Seeder tidak mengirim email otomatis dan tidak menulis password ke dokumentasi/log.

| Nama | Login | Role | Outlet (default bertanda *) |
|---|---|---|---|
| System Administrator | admin@weigu.local | super-admin | GAL-BP*, GAL-HER, PEK-JAYA, JL-RAYA-PEK |
| Manager | manager@gmail.com | manager | GAL-BP*, GAL-HER, PEK-JAYA, JL-RAYA-PEK |
| Finance | finance@gmail.com | finance | GAL-BP*, GAL-HER, PEK-JAYA, JL-RAYA-PEK |
| Warehouse | warehouse@gmail.com | warehouse | GAL-BP*, GAL-HER, PEK-JAYA, JL-RAYA-PEK |
| Kasir Cabang 1 - Galaxy BP | cashier@gmail.com | cashier | GAL-BP* |
| Kasir Cabang 2 - Galaxy Hermina | cashier2@gmail.com | cashier | GAL-HER* |
| Kasir Cabang 3 - Pekayon Jaya | cashier3@gmail.com | cashier | PEK-JAYA* |
| Kasir Cabang 4 - Jalan Raya Pekayon | cashier4@gmail.com | cashier | JL-RAYA-PEK* |

### Permission per role

- **audit-logs-access**: audit-logs-access
- **cashier**: cash-handovers-access, cash-handovers-create, cash-management-access, cash-management-create, cashier-shifts-access, cashier-shifts-close, cashier-shifts-open, customers-access, customers-create, dashboard-access, dine-in-access, dine-in-process, dine-orders-access, dine-orders-process, expenses-access, expenses-create, outlet-operations-access, outlet-operations-create, outlet-stock-returns-access, outlet-stock-returns-create, payables-access, payables-pay, pos-access, pos-checkout, pos-hold, receivables-access, receivables-collect, receivables-pay, sales-analysis-access, suppliers-access, transactions-access, waste-access, waste-create
- **cashier-shifts-access**: cashier-shifts-access, cashier-shifts-close, cashier-shifts-force-close, cashier-shifts-open
- **categories-access**: categories-access, categories-create, categories-delete, categories-edit
- **crm-campaigns-access**: crm-campaigns-access, crm-campaigns-create, crm-campaigns-delete, crm-campaigns-update
- **crm-reminders-access**: crm-reminders-access
- **customer-segments-access**: customer-segments-access, customer-segments-create, customer-segments-delete, customer-segments-update
- **customer-vouchers-access**: customer-vouchers-access, customer-vouchers-create, customer-vouchers-delete, customer-vouchers-update
- **customers-access**: customers-access, customers-create, customers-delete, customers-edit, customers-export, customers-import
- **customers-export**: customers-export
- **customers-import**: customers-import
- **dine-orders-access**: dine-in-access, dine-in-process, dine-orders-access, dine-orders-process
- **dine-tables-access**: dine-tables-access, dine-tables-create, dine-tables-delete, dine-tables-update
- **discounts-approve**: discounts-approve
- **finance**: audit-logs-access, dashboard-access, goods-receivings-access, ingredients-access, ingredients-export, ingredients-import, payables-access, payables-pay, production-access, production-approve, production-orders-access, production-requests-access, production-requests-approve, production-requests-reject, profits-access, purchase-orders-access, purchase-orders-create, purchase-orders-update, receivables-access, recipes-access, recipes-export, reports-access, sales-analysis-access, stock-opnames-access, suppliers-access, suppliers-create, suppliers-delete, suppliers-export, suppliers-import, suppliers-update, warehouses-access
- **goods-receivings-access**: goods-receivings-access, goods-receivings-create
- **ingredients-export**: ingredients-export
- **ingredients-import**: ingredients-import
- **inventory-opening-stock-import**: inventory-opening, inventory-opening-stock-import
- **manager**: audit-logs-access, cash-handovers-access, cash-management-access, cash-pickups-access, cashier-shifts-access, categories-access, customers-access, dashboard-access, expenses-access, goods-receivings-access, ingredients-access, inventory-access, inventory-ledger-access, outlet-operations-access, outlet-stock-returns-access, payables-access, production-access, production-orders-access, production-requests-access, products-access, profits-access, purchase-orders-access, receivables-access, recipes-access, recipes-export, reports-access, sales-analysis-access, sales-returns-access, stock-mutations-access, stock-opnames-access, stock-transfers-access, suppliers-access, transactions-access, transfers-access, units-access, warehouses-access, waste-access
- **payables-access**: payables-access, payables-pay
- **payment-settings-access**: payment-settings-access, payment-settings-update
- **payment-settings-update**: payment-settings-update
- **permissions-access**: permissions-access, permissions-create, permissions-delete, permissions-update
- **price-lists-access**: price-lists-access, price-lists-create, price-lists-delete, price-lists-edit, price-lists-update
- **pricing-rules-access**: pricing-rules-access, pricing-rules-create, pricing-rules-delete, pricing-rules-update
- **products-access**: products-access, products-create, products-delete, products-edit, products-export, products-import
- **products-export**: products-export
- **products-import**: products-import
- **profits-access**: profits-access
- **purchase-orders-access**: purchase-orders-access, purchase-orders-cancel, purchase-orders-create, purchase-orders-delete, purchase-orders-order, purchase-orders-update
- **receivables-access**: receivables-access, receivables-collect, receivables-pay
- **recipes-export**: recipes-export
- **recipes-import**: recipes-import
- **reports-access**: reports-access, reports-export
- **roles-access**: roles-access, roles-create, roles-delete, roles-edit, roles-update
- **sales-returns-access**: sales-returns-access, sales-returns-complete, sales-returns-create
- **stock-mutations-access**: inventory-access, inventory-ledger-access, stock-mutations-access
- **stock-opnames-access**: stock-opnames-access, stock-opnames-create, stock-opnames-finalize, stock-opnames-review
- **stock-transfers-access**: stock-transfers-access, stock-transfers-cancel, stock-transfers-create, stock-transfers-receive, stock-transfers-send, transfers-access, transfers-cancel, transfers-create, transfers-receive, transfers-send
- **super-admin**: api-tokens-access, api-tokens-manage, audit-logs-access, bank-accounts-access, bank-accounts-manage, batches-access, batches-create, batches-update, cash-handovers-access, cash-handovers-confirm, cash-handovers-create, cash-management-access, cash-management-approve, cash-management-create, cash-pickups-access, cash-pickups-confirm, cash-pickups-create, cashier-shifts-access, cashier-shifts-close, cashier-shifts-force-close, cashier-shifts-open, categories-access, categories-create, categories-delete, categories-edit, crm-access, crm-campaigns-access, crm-campaigns-create, crm-campaigns-delete, crm-campaigns-update, crm-manage, crm-reminders-access, crm-send, customer-segments-access, customer-segments-create, customer-segments-delete, customer-segments-update, customer-vouchers-access, customer-vouchers-create, customer-vouchers-delete, customer-vouchers-update, customers-access, customers-create, customers-delete, customers-edit, customers-export, customers-import, dashboard-access, dine-in-access, dine-in-manage, dine-in-process, dine-orders-access, dine-orders-process, dine-tables-access, dine-tables-create, dine-tables-delete, dine-tables-update, discount-approvals-access, discount-approvals-decide, discounts-approve, expenses-access, expenses-create, exports-run, goods-receivings-access, goods-receivings-create, imports-access, imports-run, ingredients-access, ingredients-adjust, ingredients-create, ingredients-export, ingredients-import, ingredients-manage, ingredients-update, inventory-access, inventory-adjust, inventory-ledger-access, inventory-opening, inventory-opening-stock-import, loyalty-access, loyalty-adjust, loyalty-settings-access, loyalty-settings-update, notifications-access, notifications-manage, outlet-operations-access, outlet-operations-create, outlet-stock-returns-access, outlet-stock-returns-create, outlet-stock-returns-receive, outlets-access, outlets-create, outlets-delete, outlets-update, payables-access, payables-pay, payment-settings-access, payment-settings-update, permissions-access, permissions-create, permissions-delete, permissions-update, pos-access, pos-checkout, pos-discount, pos-hold, pos-returns, price-lists-access, price-lists-create, price-lists-delete, price-lists-edit, price-lists-update, pricing-rules-access, pricing-rules-create, pricing-rules-delete, pricing-rules-update, printer-settings-access, printer-settings-update, production-access, production-approve, production-complete, production-orders-access, production-orders-complete, production-orders-create, production-request, production-requests-access, production-requests-approve, production-requests-create, production-requests-reject, products-access, products-create, products-delete, products-edit, products-export, products-import, profits-access, promotions-access, promotions-manage, purchase-orders-access, purchase-orders-cancel, purchase-orders-create, purchase-orders-delete, purchase-orders-order, purchase-orders-update, receivables-access, receivables-collect, receivables-pay, recipes-access, recipes-create, recipes-export, recipes-import, recipes-manage, reports-access, reports-export, roles-access, roles-create, roles-delete, roles-edit, roles-update, sales-analysis-access, sales-returns-access, sales-returns-complete, sales-returns-create, sales-targets-access, sales-targets-update, stock-mutations-access, stock-opnames-access, stock-opnames-create, stock-opnames-finalize, stock-opnames-review, stock-transfers-access, stock-transfers-cancel, stock-transfers-create, stock-transfers-receive, stock-transfers-send, store-settings-access, store-settings-update, supplier-returns-access, supplier-returns-approve, supplier-returns-create, supplier-returns-update, suppliers-access, suppliers-create, suppliers-delete, suppliers-export, suppliers-import, suppliers-update, transactions-access, transactions-confirm-payment, transactions-refund, transfers-access, transfers-cancel, transfers-create, transfers-receive, transfers-send, units-access, units-create, units-delete, units-edit, units-update, users-access, users-create, users-delete, users-edit, users-update, vouchers-access, vouchers-manage, warehouses-access, warehouses-create, warehouses-delete, warehouses-update, waste-access, waste-create, whatsapp-settings-access, whatsapp-settings-update
- **supplier-returns-access**: supplier-returns-access, supplier-returns-approve, supplier-returns-create, supplier-returns-update
- **suppliers-access**: suppliers-access, suppliers-create, suppliers-delete, suppliers-export, suppliers-import, suppliers-update
- **suppliers-create**: suppliers-create
- **suppliers-delete**: suppliers-delete
- **suppliers-export**: suppliers-export
- **suppliers-import**: suppliers-import
- **suppliers-update**: suppliers-update
- **transactions-access**: transactions-access, transactions-confirm-payment, transactions-refund
- **transactions-confirm-payment**: transactions-confirm-payment
- **units-access**: units-access, units-create, units-delete, units-edit, units-update
- **users-access**: users-access, users-create, users-delete, users-edit, users-update
- **warehouse**: cash-handovers-access, cash-handovers-confirm, cash-management-access, cash-pickups-access, cash-pickups-confirm, categories-access, categories-create, categories-edit, dashboard-access, expenses-access, goods-receivings-access, goods-receivings-create, ingredients-access, ingredients-adjust, ingredients-create, ingredients-update, inventory-access, inventory-ledger-access, inventory-opening, inventory-opening-stock-import, outlet-operations-access, outlet-stock-returns-access, outlet-stock-returns-receive, production-access, production-complete, production-orders-access, production-orders-complete, production-orders-create, production-request, production-requests-access, production-requests-create, products-access, products-create, products-edit, purchase-orders-access, recipes-access, recipes-create, recipes-export, recipes-import, stock-mutations-access, stock-opnames-access, stock-opnames-create, stock-opnames-finalize, stock-transfers-access, stock-transfers-cancel, stock-transfers-create, stock-transfers-receive, stock-transfers-send, supplier-returns-access, supplier-returns-create, transfers-access, transfers-cancel, transfers-create, transfers-receive, transfers-send, units-access, units-create, units-edit, units-update, warehouses-access, waste-access
- **warehouses-access**: warehouses-access, warehouses-create, warehouses-delete, warehouses-update
