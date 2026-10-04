# Homepage Livewire: Weigu Dimsum & Gyoza

Paket ini membawa homepage dari proyek sumber ke aplikasi Laravel baru. Implementasi menggunakan Blade dan Livewire; tidak memerlukan frontend bundler.

## Isi paket

- app/Livewire/PublicSite/Home.php: Livewire homepage, menu dan data kontak/cabang fallback.
- app/Queries/PublicSite/HomepageData.php: mengambil profil toko, outlet aktif dan produk dari database.
- resources/views/livewire/public-site/: markup homepage dan daftar menu/harga.
- resources/views/components/icon.blade.php beserta SVG Tabler yang digunakan.
- app/Support/StaticAssets.php: URL aset dengan hash file.
- public/assets/application.css, theme.js, homepage.js, logo dan gambar hero.
- Lisensi CSS dan ikon yang disertakan.

## Pasang ke proyek baru

Ekstrak isi direktori homepage/ ke root Laravel, gabungkan file dengan struktur yang sama, lalu tambahkan route publik:

    use App\Livewire\PublicSite\Home;
    use Illuminate\Support\Facades\Route;

    Route::get('/', Home::class)->name('home');

Pastikan proyek menggunakan Livewire 3 dan auto-discovery komponen Blade Laravel. Aset berada di public/assets dan gambar hero di public/images. StaticAssets menghitung hash dari public/assets/{nama-file}, sehingga semua aset yang dirujuk harus disalin bersama.

## Data dan konfigurasi yang dibutuhkan

Homepage mengambil model aplikasi baru App\Models\Setting, App\Models\Outlet, dan App\Models\Product. Jangan mengganti seluruh model proyek baru dengan model dari sumber tanpa meninjau migrasi/domain barunya.

- settings: metode Setting::get($key, $default) dan nilai store_name, store_description, store_phone, store_email, serta store_logo. File logo toko disimpan pada disk public dan dapat diakses melalui php artisan storage:link.
- outlets: kolom is_active, is_sales_enabled, name, address, map_url, dan opening_hours (JSON berisi jadwal cabang).
- products (opsional untuk halaman ini): id, title, description, sell_price, image, dan updated_at. Menu harga yang ditampilkan menggunakan menu/varian yang didefinisikan pada Home.php; produk database tidak menggantikan daftar tersebut.
- Extension PHP intl diperlukan hanya bila aplikasi/format angka memerlukannya; homepage memakai number_format.

Homepage menampilkan empat alamat/jadwal fallback bila belum ada outlet aktif yang menjual. Kontak CS, nomor WhatsApp, dan tautan GoFood saat ini adalah nilai contoh Weigu pada Home.php; ganti bila digunakan bisnis lain. Nomor WhatsApp untuk URL wa.me harus dalam format internasional tanpa tanda plus.

## Integrasi layout

Komponen memakai layouts.application, menyertakan @livewireStyles/@livewireScripts, CSS statis dan theme.js. Bila proyek tujuan sudah memiliki layout global, gabungkan meta/Livewire/assets yang diperlukan alih-alih menimpa layout proyek. Link manifest generik tidak disertakan agar bundle ini tidak mengganti manifest/PWA aplikasi tujuan. Google Fonts dipanggil layout dari Google Fonts; bila deployment memerlukan font lokal/offline, sediakan font berlisensi lokal dan sesuaikan tag font.

## Pemeriksaan setelah dipasang

1. Jalankan migration dan seeder bisnis pada aplikasi tujuan, lalu buka /.
2. Periksa logo, hero, seluruh menu/harga, detail outlet/jam/maps, kontak, form laporan WhatsApp dan tampilan mobile.
3. Pastikan model/kolom dan URL aset tidak menghasilkan 404. Periksa halaman dengan dan tanpa outlet aktif.
4. Uji preferensi reduced motion dan browser tanpa IntersectionObserver; homepage.js menonaktifkan reveal dengan aman.
5. Pertahankan lisensi pada resources/css/LICENSE dan resources/views/components/icons/LICENSE.

Paket ini membawa homepage publik dan aset pendukung. Skema database, model, route marketing lain, auth, dashboard admin, dan konfigurasi bisnis tetap menjadi bagian proyek baru.
