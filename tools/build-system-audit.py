"""Generate the requested JSON audit from inspected source and local browser artifacts."""
import json
from collections import Counter
from datetime import datetime, timedelta, timezone
from pathlib import Path


def reference(path, needle):
    lines = Path(path).read_text(encoding='utf-8').splitlines()
    return {'file': path, 'line': next((index for index, text in enumerate(lines, 1) if needle in text), 1)}


findings = []


def finding(key, category, severity, status, title, evidence, impact, action, path, needle, validation='Review kode; tes regresi otomatis belum dijalankan pada perubahan ini.'):
    findings.append({'id': key, 'kategori': category, 'tingkat': severity, 'status': status, 'judul': title,
                     'bukti': evidence, 'dampak': impact, 'tindakan': action, 'referensi': reference(path, needle),
                     'validasi': validation})


finding('SEC-01', 'akses', 'tinggi', 'diperbaiki', 'Pengelola user dapat menargetkan akun yang hak aksesnya lebih tinggi',
        'Sebelumnya save memeriksa role baru tetapi tidak melindungi identitas akun super-admin yang sedang diedit; edit/deactivate/sendResetLink juga tidak memeriksa hierarki target.',
        'Potensi perubahan email, reset atau penurunan akses akun lebih berhak oleh pengelola user yang bukan super-admin.',
        'protectTarget menolak target super-admin dan permission target di luar permission pelaku, termasuk saat ID diubah melalui payload Livewire.',
        'app/Livewire/Admin/UsersIndex.php', 'private function protectTarget')
finding('SEC-02', 'akses', 'tinggi', 'diperbaiki', 'Middleware custom tidak otomatis persisten pada pembaruan Livewire',
        'Daftar persistent middleware bawaan Livewire tidak mencakup EnsureActiveAccount, RequirePasswordChange, EnsureOutletContext, Spatie permission/role, atau konfirmasi password aplikasi.',
        'Sesi komponen lama berpotensi tetap mengirim action setelah akun nonaktif, outlet dicabut, atau password wajib diganti.',
        'Daftarkan middleware tersebut secara persisten berdasarkan route asal; homepage publik tetap dapat dirender tanpa auth.',
        'app/Providers/AppServiceProvider.php', 'addPersistentMiddleware', 'Review kode dan pembukaan formulir Livewire pada database preview terpisah.')
finding('SEC-03', 'sesi', 'tinggi', 'diperbaiki', 'Reset/ganti password belum mencabut akses lama',
        'Sebelumnya password berubah tanpa mencabut token API, merotasi remember token, atau memvalidasi hash password pada sesi web.',
        'Token atau sesi yang sudah dikuasai pihak lain dapat tetap dipakai setelah pemulihan akun.',
        'Aktifkan AuthenticateSession; rotasi remember token, cabut token API dan sesi database lain saat ganti password; reset mencabut semua sesi database.',
        'app/Http/Controllers/Auth/PasswordResetController.php', 'tokens()->delete()')
finding('SEC-04', 'audit_log', 'sedang', 'diperbaiki', 'Redaksi audit hanya mengenali sebagian nama rahasia',
        'Daftar awal tidak menangani credentials plural, current_password, server_key, atau nama token dengan prefix/suffix.',
        'Payload audit baru dapat memasukkan rahasia bila pengembang memakai nama field berbeda.',
        'Tambahkan pencocokan nama sensitif secara rekursif. Nilai rahasia tidak dimasukkan ke laporan ini.',
        'app/Support/AuditLogger.php', 'preg_match')
finding('SEC-05', 'header', 'rendah', 'diperbaiki', 'Header keamanan belum diterapkan ke API',
        'AddSecurityHeaders semula hanya berada pada grup web.',
        'Respons API tidak mendapat nosniff dan kebijakan header aplikasi yang konsisten.',
        'Tambahkan AddSecurityHeaders ke grup API.', 'bootstrap/app.php', '$middleware->api')
finding('SEC-06', 'token', 'sedang', 'terbuka', 'Token Sanctum belum memiliki masa berlaku default',
        'config/sanctum.php menetapkan expiration null; createToken tidak memberikan expiresAt.',
        'Token yang tidak dicabut dapat berlaku tanpa batas waktu.',
        'Tentukan TTL sesuai kebutuhan perangkat, rotasi token dan jalankan pembersihan token kedaluwarsa; perubahan kebijakan belum diterapkan.',
        'config/sanctum.php', "'expiration'")
finding('SEC-07', 'csp', 'sedang', 'terbuka', 'CSP masih mengizinkan unsafe-inline dan unsafe-eval',
        'Kebijakan script-src mengizinkan inline/eval untuk kompatibilitas Livewire/Alpine dan skrip template.',
        'Pertahanan tambahan terhadap XSS masih lebih lemah; ini bukan bukti adanya eksploit XSS.',
        'Rancang nonce dan varian CSP yang kompatibel sebelum memperketat. Jangan mematikan Alpine/Livewire secara sepihak.',
        'app/Http/Middleware/AddSecurityHeaders.php', 'Content-Security-Policy')
finding('SEC-08', 'konkurensi', 'tinggi', 'perlu_validasi', 'Perlindungan super-admin terakhir belum diserialisasi pada seluruh jalur',
        'Pemeriksaan jumlah admin aktif pada deactivate dan penghapusan profil belum memakai satu lock bersama antarrequest.',
        'Dua perubahan administratif bersamaan berpotensi menyisakan nol admin aktif.',
        'Tambahkan penguncian bersama untuk operasi administratif dan validasi race pada database MySQL terisolasi.',
        'app/Livewire/Admin/UsersIndex.php', 'public function deactivate', 'Risiko dari review kode; race belum direproduksi.')
finding('SEC-09', 'operasional', 'sedang', 'terbuka', 'Akun lokal menggunakan password bersama atas permintaan pengguna',
        'Password tujuh akun staf sebelumnya disamakan untuk akses lokal; nilai password tidak disimpan dalam JSON ini.',
        'Bila konfigurasi lokal dibawa ke deployment, identitas pelaku dan keamanan akses menjadi lemah.',
        'Pertahankan akses lokal sesuai instruksi; gunakan kredensial unik dan reset terkelola ketika menyiapkan production.',
        'database/seeders/UserSeeder.php', 'Str::random')
finding('BUG-01', 'pos', 'tinggi', 'diperbaiki', 'Jumlah item paket dihitung dari harga dasar pada tampilan',
        'Blade lama menampilkan product.sell_price dikali quantity meski item memakai harga paket khusus.',
        'Kasir melihat nominal item berbeda dari total pembayaran server.',
        'Gunakan previewLines dengan pilihan ProductUnit, faktor, diskon dan pajak yang sama dengan preview total.',
        'app/Livewire/Pos/Checkout.php', '$previewLines =', 'Browser preview: dua P4 Dimsum Ori menampilkan Rp32.000 pada item dan total.')
finding('BUG-02', 'pos', 'tinggi', 'diperbaiki', 'Pelanggan/catatan baru belum otomatis tersimpan ketika bayar atau hold',
        'Sebelumnya pengguna harus menekan Simpan detail sebelum pay/payLater/hold.',
        'Catatan atau pelanggan bisa hilang; pembayaran piutang dapat menolak pelanggan yang baru dipilih.',
        'Validasi dan persistDetails dipanggil sebelum pembayaran dan hold; tombol simpan terpisah tetap tersedia.',
        'app/Livewire/Pos/Checkout.php', 'private function persistDetails')
finding('BUG-03', 'pos', 'sedang', 'diperbaiki', 'Detail keranjang lama tidak dipulihkan ke formulir',
        'mount/resume sebelumnya tidak mengisi customerId dan notes dari keranjang tersimpan.',
        'Reload atau melanjutkan hold menampilkan pelanggan/catatan kosong walaupun data server ada.',
        'Pulihkan detail pada mount/resume, bersihkan detail ketika hold selesai dan buka panel keranjang setelah resume.',
        'app/Livewire/Pos/Checkout.php', 'public function mount')
finding('BUG-04', 'katalog', 'sedang', 'diperbaiki', 'Produk POS dibatasi 12 tanpa navigasi halaman',
        'Query POS semula limit(12)->get tanpa tombol lanjut.',
        'Produk urutan berikutnya hanya dapat ditemukan melalui pencarian.',
        'Gunakan paginate(12), reset halaman saat pencarian berubah dan tampilkan pagination Bootstrap.',
        'app/Livewire/Pos/Checkout.php', 'paginate(12)', 'Browser preview memuat katalog dari 13 produk dengan pagination.')
finding('BUG-05', 'kinerja', 'sedang', 'diperbaiki', 'Preview POS melakukan query satuan per baris',
        'Relasi satuan dasar dan productUnits item belum diload bersama; preview meminta query unit pada setiap item.',
        'Waktu respons membesar saat keranjang ramai atau jaringan HP lambat.',
        'Eager-load unit/productUnits dan gunakan koleksi relasi saat menghitung harga.',
        'app/Livewire/Pos/Checkout.php', "items.product.productUnits")
finding('BUG-06', 'katalog', 'sedang', 'diperbaiki', 'Satuan dasar hasil seeder masuk ke editor satuan alternatif',
        'ProductUnit sumber menyimpan baris BASE; editor sebelumnya memuat seluruh ProductUnit dan menolak unit yang sama dengan unit dasar.',
        'Produk hasil pemindahan seeder gagal disimpan atau kehilangan metadata baris BASE.',
        'Filter BASE dari alternatif di editor, pertahankan baris BASE saat sinkronisasi, dan hilangkan tombol paket BASE duplikat pada POS.',
        'app/Livewire/Catalog/ProductsIndex.php', "where('unit_id', '!=', $product->unit_id)")
finding('BUG-07', 'satuan', 'sedang', 'diperbaiki', 'Satuan PAK/PKT berbagi simbol dan gagal diedit',
        'Schema baru mendukung kode PAK dan PKT dengan simbol paket, sedangkan validasi editor masih mewajibkan simbol unik pada setiap save.',
        'Mengubah nama atau status satuan sumber dapat gagal meski simbol tidak berubah.',
        'Pertahankan simbol bersama yang sudah ada; validasi unik tetap berlaku untuk simbol baru/berubah.',
        'app/Livewire/Catalog/UnitsIndex.php', '$symbolRules')
finding('BUG-08', 'dashboard', 'rendah', 'diperbaiki', 'Jumlah stok perlu dicek berhenti pada delapan baris',
        'Kartu metrik menghitung koleksi lowStock yang query-nya limit(8).',
        'Dashboard memberi jumlah lebih rendah bila lebih dari delapan produk perlu perhatian.',
        'Pisahkan count seluruh hasil dari delapan baris preview.',
        'app/Http/Controllers/DashboardController.php', '$lowStockCount')
finding('BUG-09', 'dashboard', 'rendah', 'diperbaiki', 'Tombol Lihat shift menuju formulir buka shift',
        'Label berganti ketika shift aktif tetapi URL tetap shifts.create.',
        'Kasir diarahkan ke tindakan yang tidak sesuai dengan shift aktif.',
        'Gunakan shifts.index untuk shift aktif dan tambahkan menu Shift kasir.',
        'resources/views/dashboard/index.blade.php', 'shifts.index')
finding('BUG-10', 'integritas', 'tinggi', 'diperbaiki', 'Satuan dasar dapat berubah pada produk yang sudah dipakai',
        'Editor sebelumnya mengganti unit_id tanpa pemeriksaan resep, saldo gudang, ledger atau histori penjualan.',
        'Makna stok dan komposisi resep dapat berubah tanpa konversi yang sah.',
        'Tolak perubahan satuan dasar jika produk sudah mempunyai referensi tersebut; identitas dan snapshot lama dipertahankan.',
        'app/Livewire/Catalog/ProductsIndex.php', 'Satuan dasar tidak dapat diubah')
finding('BUG-11', 'akses', 'sedang', 'diperbaiki', 'Detail transaksi ditampilkan ke semua pemilik dashboard-access',
        'Query transaksi terbaru dan tabel pelanggan tidak membatasi transactions-access.',
        'Role dashboard saja berpotensi membaca detail pelanggan/transaksi di luar izin modul transaksi.',
        'Batasi query dan kartu transaksi terbaru dengan transactions-access.',
        'app/Http/Controllers/DashboardController.php', "can('transactions-access')")
finding('UX-01', 'navigasi', 'tinggi', 'diperbaiki', 'Header terlalu padat untuk HP',
        'Selector outlet, tombol pilih, profil, logout, tema dan nama pengguna berada pada satu baris.',
        'Kontrol saling mendesak dan berpotensi membuat halaman melebar.',
        'Pisahkan pemilih outlet, kelompokkan akun dalam menu, gunakan header sticky dan sediakan navigasi bawah sesuai permission.',
        'resources/views/layouts/dashboard.blade.php', 'workspace-topbar', 'Browser: halaman yang diperiksa pas pada lebar 320/390; sampel kasir juga diperiksa pada 768/1280.')
finding('UX-02', 'tabel', 'tinggi', 'diperbaiki', 'Tabel memerlukan geser horizontal untuk membaca tiap record',
        'Modul memakai table-responsive desktop pada inventori, transaksi, user, laporan dan finance.',
        'Informasi dan aksi penting sulit dicocokkan pada HP.',
        'Ubah tabel sederhana menjadi kartu berlabel di bawah 768px; tabel kompleks tetap dapat digeser; label diambil dari header tanpa mengubah data.',
        'public/assets/dashboard-mobile.js', 'function prepareTables', 'Browser preview pada record pelanggan, produk, pengguna dan satuan; pembaruan DOM Livewire tetap mendapat label.')
finding('UX-03', 'sentuhan', 'tinggi', 'diperbaiki', 'Tombol kecil dan keyboard angka belum konsisten',
        'btn-sm, tombol ikon dan kontrol kuantitas berukuran kecil; field angka belum memakai inputmode.',
        'Kesalahan sentuh dan entri nominal/kuantitas menjadi lebih sering.',
        'Target sentuh minimal 44px, font input 16px, keyboard decimal, wrapping filter dan safe-area HP.',
        'public/assets/dashboard-mobile.css', 'min-width: 44px', 'Browser inspeksi target tombol pada sampel modul; kontrol modal juga mendapat ukuran minimum.')
finding('UX-04', 'pos', 'tinggi', 'diperbaiki', 'Kasir harus menggulir daftar panjang untuk mencapai keranjang',
        'Kolom keranjang semula berada setelah seluruh daftar produk ketika layout menjadi satu kolom.',
        'Kasir sering kehilangan konteks produk dan pembayaran.',
        'Grid produk dua kolom, tombol total tetap terlihat, panel keranjang mobile penuh, fokus terkelola dan pembayaran uang pas.',
        'resources/views/livewire/pos/checkout.blade.php', 'pos-mobile-cart-button', 'Browser preview: buka/tutup keranjang, total paket, body scroll lock dan Escape diperiksa.')
finding('UX-05', 'feedback', 'sedang', 'diperbaiki', 'Event toast tidak mempunyai penerima di layout',
        'Banyak komponen dispatch toast tetapi layout belum memasang listener/region notifikasi.',
        'Pengguna tidak mengetahui apakah save/hold/aksi telah berhasil.',
        'Tambahkan notifikasi aria-live yang bisa ditutup, gunakan textContent dan tampilkan error POS secara menyeluruh.',
        'public/assets/dashboard-mobile.js', 'function notify')
finding('UX-06', 'pagination', 'sedang', 'diperbaiki', 'Pagination Tailwind muncul di dashboard Mazer',
        'Default Livewire adalah tailwind sementara CSS dashboard memakai Bootstrap Mazer.',
        'Pagination dapat tidak terstyle dan target sentuh tidak konsisten.',
        'Atur pagination Livewire dan paginator Laravel ke Bootstrap; tambahkan ukuran sentuh pada page-link.',
        'app/Providers/AppServiceProvider.php', 'pagination_theme', 'Browser preview memakai 24 pelanggan sehingga pagination benar-benar muncul.')
finding('UX-07', 'javascript', 'sedang', 'diperbaiki', 'File sidebar sumber ES module dimuat sebagai script biasa',
        'layouts.dashboard memuat static/js/components/sidebar.js yang diawali import dan juga sudah memakai app.js hasil compile.',
        'Error sintaks/initialisasi ganda dari file yang tidak cocok dimuat langsung.',
        'Gunakan app.js template hasil compile dan skrip aplikasi statis; hapus pemanggilan sidebar sumber dan kelola tema secara persisten.',
        'resources/views/layouts/dashboard.blade.php', 'dashboard-mobile.js')
finding('UX-08', 'javascript', 'sedang', 'diperbaiki', 'Sidebar Mazer memanggil getBoundingClientRect pada elemen null',
        'Browser sempat mencatat TypeError di Mr.forceElementVisibility ketika tidak ada item yang cocok.',
        'Navigasi dapat menyisakan error JavaScript pada pembukaan halaman.',
        'Tambahkan pemeriksaan null pada salinan app.js template di public; folder template sumber tidak diubah.',
        'public/mazer/compiled/js/app.js', 'forceElementVisibility', 'Inspeksi browser berikutnya tidak menemukan error tersebut pada halaman yang diperiksa.')
finding('UX-09', 'autentikasi', 'sedang', 'diperbaiki', 'Login besar dan beberapa input tidak memiliki label',
        'Ukuran auth-title/padding template terlalu besar; email/password memakai placeholder dan avatar tidak mempunyai label.',
        'Form lambat dijangkau dan kurang jelas bagi teknologi bantu.',
        'Perkecil proporsi login secara responsif, muat ikon secara eksplisit dan beri label pada input.',
        'public/assets/dashboard-mobile.css', '.workspace-auth')
finding('UX-10', 'koneksi', 'sedang', 'sebagian', 'Tidak ada feedback ketika koneksi HP terputus',
        'Alur sekarang memerlukan koneksi untuk request Livewire; belum ada antrean transaksi offline di browser.',
        'Kasir dapat menekan simpan/bayar ketika koneksi terputus tanpa memahami statusnya.',
        'Tampilkan status offline/online. Antrean offline, IndexedDB, sinkronisasi dan printer masih pekerjaan terpisah sesuai feature-traceability.',
        'public/assets/dashboard-mobile.js', "addEventListener('offline'")
finding('REG-01', 'regresi_ui', 'tinggi', 'diperbaiki', 'Penempatan inputmode sempat merusak ekspresi Blade berisi panah objek',
        'Saat perbaikan keyboard angka, regex awal membaca tanda > pada ekspresi PHP sebagai akhir tag; preview mendeteksi parse error pada penerimaan dan opname.',
        'Dua halaman sempat tidak dapat dirender selama pengerjaan.',
        'Ganti pendekatan: tambahkan inputmode langsung setelah type=number, pulihkan ekspresi dan lint seluruh PHP hasil kompilasi Blade.',
        'resources/views/livewire/purchasing/goods-receivings-index.blade.php', 'inputmode', 'Seluruh compiled Blade PHP valid; browser berikutnya merender penerimaan dan opname tanpa error.')
finding('OPS-01', 'deployment', 'sedang', 'terbuka', 'Konfigurasi lokal belum menjadi konfigurasi production',
        'Lingkungan kerja ini memakai local development dan email log; artefak browser berada di storage/app/ui-audit.',
        'Reset password belum terkirim lewat email nyata dan data simulasi tidak cocok untuk transaksi operasional.',
        'Siapkan SMTP, HTTPS, APP_DEBUG=false, konfigurasi secure cookie, backup dan data stok aktual ketika menyiapkan deployment; jangan memindahkan DB preview.',
        'docs/source-seeders.md', 'simulasi')

folder = Path('storage/app/ui-audit')
reports = {}
for name in ['cashier', 'preview-cashier', 'preview-admin', 'preview-admin-corrections', 'preview-users-final', 'preview-pos-final', 'auth-final']:
    path = folder / f'{name}.json'
    if path.exists():
        reports[name] = json.loads(path.read_text(encoding='utf-8'))

# The final correction artifacts replace earlier findings for the same route/viewport.
latest = {}
interactions = []
for name, report in reports.items():
    for page in report['pages']:
        if page.get('interaction'):
            interactions.append({'sumber': name, **page})
        elif page.get('requested'):
            viewport = page.get('requested_width', page.get('width'))
            if 320 <= viewport <= 330: viewport = 320
            latest[(name.startswith('preview'), page['requested'], viewport)] = {'sumber': name, **page}
pages = list(latest.values())
summary = Counter(item['status'] for item in findings)
document = {
    'judul': 'Analisis potensi bug, celah akses dan UI/UX mobile Dimsum Weigu',
    'dibuat_pada': datetime.now(timezone(timedelta(hours=7))).isoformat(timespec='seconds'),
    'bahasa': 'id',
    'framework': {'laravel': '12.69.3', 'frontend': 'Livewire 3 + Blade + Alpine', 'ui': 'Mazer Bootstrap dan aset statis', 'node_digunakan': False},
    'cakupan': ['Autentikasi, sesi, middleware web/API/Livewire', 'User, role, audit log, akses outlet', 'POS, harga satuan, keranjang dan detail pelanggan', 'Editor produk/satuan dan dashboard', 'Layout bersama, tabel/formulir modul, modal dan navigasi mobile'],
    'batasan': ['Audit kode dan inspeksi browser lokal; bukan pengujian penetrasi lengkap.',
                'Tidak menjalankan suite tes otomatis perubahan ini; validasi meliputi lint PHP/Blade, browser dan Composer audit.',
                'Race condition MySQL, perangkat HP fisik, virtual keyboard iOS, hardware printer/kamera dan beban banyak kasir belum diperiksa.',
                'Preview menggunakan SQLite terpisah; tidak membuat shift, saldo atau transaksi uji pada database operasional MySQL.',
                'Status diperbaiki menunjukkan perubahan telah diterapkan; validasi tiap temuan dijelaskan tersendiri.'],
    'ringkasan': {'total_temuan': len(findings), 'status': dict(summary)},
    'temuan': findings,
    'perubahan_ui': ['Header sticky dua baris dan menu akun', 'Navigasi bawah berbasis permission serta sidebar dengan keyboard/fokus',
                      'Tabel sederhana menjadi kartu berlabel di HP', 'Target tombol 44px, input 16px, decimal keyboard dan safe-area',
                      'Kasir dua kolom produk dengan panel keranjang dan tombol total', 'Harga paket konsisten, uang pas dan loading pembayaran',
                      'Toast, ringkasan error dan status koneksi', 'Pagination Bootstrap, formulir responsif dan login ringkas'],
    'validasi': {'composer_audit': {'advisories': 0, 'abandoned': 0, 'hasil': 'Tidak ada advisory yang dilaporkan pada saat pemeriksaan; bukan jaminan bebas celah.'},
                 'php_blade': 'Sintaks PHP hasil kompilasi seluruh Blade valid.',
                 'browser': {'mesin': 'Microsoft Edge headless / CDP', 'viewport': [320, 390, 768, 1280],
                             'ringkasan': {'kombinasi_halaman_viewport': len(pages),
                                           'overflow_horizontal': sum(bool(page.get('overflow')) for page in pages),
                                           'halaman_error_javascript': sum(bool(page.get('javascript_errors')) for page in pages),
                                           'halaman_input_tanpa_label': sum(bool(page.get('unlabeledInputs')) for page in pages),
                                           'halaman_tombol_terlalu_kecil': sum(bool(page.get('smallButtons')) for page in pages)},
                             'halaman_terakhir': pages, 'interaksi': interactions}},
    'artefak': {'json': 'analisis-system-ui-ux.json', 'stylesheet': 'public/assets/dashboard-mobile.css', 'script': 'public/assets/dashboard-mobile.js',
                'screenshot_preview': 'storage/app/ui-audit', 'panduan': 'README.md'},
    'prioritas_berikutnya': ['Validasi race admin terakhir dan konkurensi stok/uang pada MySQL terisolasi.',
                           'Tetapkan TTL token dan siapkan konfigurasi deployment/email nyata.',
                           'Lakukan regresi otomatis untuk perubahan akses/sesi/POS ketika diminta.',
                           'Periksa keyboard, orientasi, kamera dan printer pada HP fisik.',
                           'Lanjutkan modul offline/produksi/gateway/dine-in sesuai feature-traceability.']
}
Path('analisis-system-ui-ux.json').write_text(json.dumps(document, ensure_ascii=False, indent=2) + '\n', encoding='utf-8')
print(json.dumps({'file': 'analisis-system-ui-ux.json', 'temuan': len(findings), 'status': dict(summary), 'browser_pages': len(pages)}))
