# API mobile POS v1

Semua endpoint berada di `/api/v1/pos`, menggunakan Bearer token Sanctum dengan ability **pos-access** secara eksplisit, permission user saat ini, akun aktif, dan kata sandi yang tidak diwajibkan diganti. Token `user:read` saja tidak dapat memakai POS. Checkout membutuhkan permission `pos-checkout`, hold/resume/discard `pos-hold`, buka/tutup shift `cashier-shifts-open`/`cashier-shifts-close`.

Kirim `Accept: application/json`. Setiap request wajib menyertakan `outlet_id` pada query atau JSON body. User harus memiliki akses outlet; gudang serta cart harus berada di outlet tersebut. POS API membatasi cart dan riwayat pada kasir pemilik, termasuk ketika user adalah manager. Outlet yang dipilih tidak disimpan dalam cookie/session API. Audit mendapatkan konteks dari outlet request yang telah diperiksa.

| Method | Endpoint | Payload / hasil |
|---|---|---|
| GET | `/shift` | Shift terbuka milik kasir; null bila belum dibuka |
| POST | `/shift/open` | `warehouse_id`, `opening_cash`; hasil 201 |
| POST | `/shift/close` | `actual_cash`, `note` (wajib bila selisih), `password` user saat ini untuk konfirmasi aksi |
| GET | `/catalog` | `search`, barcode tepat `barcode`, `per_page` maks 100; shift wajib |
| GET | `/cart` | Mendapatkan/membuat cart aktif dan UUID server; shift wajib |
| PATCH | `/carts/{id}` | `customer_id` nullable, `notes` nullable; kirim keduanya untuk mempertahankan nilai |
| POST | `/carts/{id}/items` | `product_id`, `unit_id` opsional; tambah satu unit |
| PATCH | `/carts/{id}/items/{item}` | `quantity` maksimal tiga desimal |
| DELETE | `/carts/{id}/items/{item}` | Hapus item milik cart |
| GET | `/held` | Daftar hold shift aktif |
| POST | `/carts/{id}/hold` | `label`; cart kosong ditolak |
| POST | `/carts/{id}/resume` | Revalidasi shift, produk, unit, stok dan batch; cart aktif berisi item harus ditahan dahulu |
| DELETE | `/carts/{id}/hold` | Discard hold, tidak menghapus histori |
| POST | `/carts/{id}/checkout` | `method` cash/pay-later, `received` untuk cash, `client_uuid` UUID cart server |
| POST | `/sync` | Payload checkout ditambah `cart_id`; satu hasil per request |
| GET | `/transactions` | `from`, `to`, `per_page`; riwayat milik kasir di outlet |
| GET | `/transactions/{id}` | Detail, pembayaran dan total server; HPP internal tidak dikirim |

Harga, pajak, stok dan total selalu dihitung server. Field harga/total dari klien diabaikan. Tunai menulis sale, item, tender, profit, ledger stok, ledger kas gudang, expected cash shift dan audit secara atomik. Pay-later membutuhkan pelanggan aktif, metode aktif dan kredit cukup; mencatat piutang dan status unpaid tanpa kas masuk.

## Idempotensi dan sinkronisasi

Server menyediakan `uuid` saat membuat cart. Klien wajib menggunakan nilai ini sebagai `client_uuid`, bukan membuat cart atau UUID sendiri ketika offline. Retry checkout dari cart yang sudah selesai mengembalikan transaksi awal tanpa stok/kas ganda. Mengubah metode atau uang diterima pada retry ditolak. Retry yang hanya meminta pengakuan transaksi selesai masih dapat dilakukan setelah shift ditutup; cart belum dibayar pada shift tertutup tidak dapat menghasilkan sale baru.

Endpoint sync menggunakan isi cart server terakhir. Antrean IndexedDB, indikator konflik, service worker dan client mobile masih perlu dibuat; API ini sendiri tidak memberi kemampuan membuat cart atau mengubah item ketika offline. Checkout transfer/gateway belum tersedia; metode yang diterima saat ini hanya cash/pay-later.

HTTP 401 berarti token tidak valid, 403 ability/permission/akun/shift checkout tidak valid, 404 resource di luar scope, 422 validasi/stok/payment conflict, 429 rate limit. `/pos` dibatasi 120 request per menit. Respons paginated memakai `data.data` dan metadata pagination; cart/shift/detail memakai `data`.

## Verifikasi

`php artisan test --filter=MobilePosApiTest` memakai token Bearer nyata dengan ability yang ditetapkan: alur sampai tutup shift, replay setelah tutup, ledger kas sekali saja, harga klien diabaikan, hold/resume/discard, validasi stock berubah, permission live, akun nonaktif, password change, akses outlet/kasir, rollback, UUID conflict dan pay-later. Suite SQLite; pengujian concurrent MySQL dan aplikasi mobile/perangkat nyata belum dilakukan.
