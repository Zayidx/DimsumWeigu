<div x-data="homepage" class="min-h-screen bg-[#fffaf4] text-[#34251f]">
    <header class="absolute inset-x-0 top-0 z-20">
        <nav class="mx-auto flex max-w-7xl items-center justify-between px-5 py-5 sm:px-8">
            <a href="#beranda" class="flex items-center gap-3" aria-label="{{ ($business['name']) . ' — beranda' }}">
                <img src="/assets/logo/logo-no-background.png" alt="Weigu Dimsum &amp; Gyoza" class="h-12 w-auto max-w-[170px] object-contain sm:h-14" />
            </a>
            <div class="hidden items-center gap-8 text-sm font-medium text-[#695850] md:flex">
                <a href="#cerita" class="transition hover:text-[#a84127]">Cerita Kami</a>
                <a href="#menu" class="transition hover:text-[#a84127]">Menu</a>
                <a href="#outlet" class="transition hover:text-[#a84127]">Outlet</a>
            </div>
        </nav>
    </header>

    <main>
        <section id="beranda" class="relative isolate overflow-hidden">
            <div class="absolute inset-0 -z-10 bg-[#f7ecdf]"></div>
            <div class="absolute inset-0 -z-10 bg-cover bg-center md:bg-[position:center_54%]" style="background-image: url(&#x27;/images/dimsum-hero.png&#x27;)"></div>
            <div class="absolute inset-0 -z-10 bg-gradient-to-r from-[#fffaf4]/95 via-[#fffaf4]/80 to-[#fffaf4]/20 md:to-transparent"></div>
            <div class="mx-auto flex min-h-[680px] max-w-7xl items-center px-5 pb-20 pt-32 sm:px-8 md:min-h-[760px]">
                <div class="scroll-reveal max-w-2xl">
                    <p class="mb-6 inline-flex items-center gap-2 rounded-full border border-[#d8bca8] bg-white/70 px-4 py-2 text-xs font-bold uppercase tracking-[0.18em] text-[#9b482d]">
                        Dimsum hangat, setiap hari
                    </p>
                    <h1 class="font-serif text-5xl font-semibold leading-[1.08] tracking-tight text-[#34251f] sm:text-6xl md:text-7xl">
                        Momen kecil,
                        <span class="block text-[#a84127]">rasa yang istimewa.</span>
                    </h1>
                    <p class="mt-6 max-w-xl text-base leading-7 text-[#695850] sm:text-lg sm:leading-8">
                        {{ ($business['description'] ?: "Dibuat hangat dengan bahan pilihan dan penuh perhatian. Temukan dimsum favorit untuk dinikmati bersama orang-orang tersayang.") }}
                    </p>
                    <div class="mt-9 flex flex-col gap-3 sm:flex-row">
                        <a href="#menu" class="inline-flex items-center justify-center gap-2 rounded-full bg-[#a84127] px-7 py-3.5 font-semibold text-white shadow-lg shadow-[#a84127]/20 transition hover:-translate-y-0.5 hover:bg-[#87351f]">
                            Lihat menu <x-icon name="arrow-down" size="{{ 18 }}" />
                        </a>
                        <a href="{{ $gofoodUrl }}" target="_blank" rel="noreferrer" class="inline-flex items-center justify-center gap-2 rounded-full border border-[#b99c8b] bg-white/80 px-7 py-3.5 font-semibold text-[#49372f] transition hover:bg-white">Pesan lewat GoFood <x-icon name="arrow-up-right" size="{{ 17 }}" /></a>
                    </div>
                    <div class="mt-12 flex items-center gap-3 text-sm text-[#695850]">
                        <span class="flex -space-x-2" aria-hidden="true">
                            <span class="h-8 w-8 rounded-full border-2 border-[#fffaf4] bg-[#d99765]"></span><span class="h-8 w-8 rounded-full border-2 border-[#fffaf4] bg-[#e9bd8a]"></span><span class="h-8 w-8 rounded-full border-2 border-[#fffaf4] bg-[#b97858]"></span>
                        </span>
                        <span>Diracik segar, disajikan dengan hangat</span>
                    </div>
                </div>
            </div>
            <a href="#cerita" aria-label="Lanjut ke cerita kami" class="absolute bottom-7 left-1/2 hidden -translate-x-1/2 items-center gap-2 text-xs font-semibold uppercase tracking-widest text-[#695850] md:flex">
                Jelajahi <x-icon name="arrow-down" size="{{ 15 }}" />
            </a>
        </section>

        <section id="cerita" class="mx-auto grid max-w-7xl gap-12 px-5 py-24 sm:px-8 md:grid-cols-[0.8fr_1.2fr] md:items-center md:py-32">
            <div class="scroll-reveal">
                <p class="text-xs font-bold uppercase tracking-[0.2em] text-[#a84127]">Dari dapur kami</p>
                <h2 class="mt-4 font-serif text-4xl font-semibold leading-tight sm:text-5xl">Rasa rumahan, dibuat sepenuh hati.</h2>
            </div>
            <div class="scroll-reveal space-y-5 text-base leading-8 text-[#695850]" style="--reveal-delay: 120ms">
                <p>{{ ($business['description'] ?: (($business['name'] ?: "Kami")) . ' percaya hidangan yang baik dimulai dari bahan yang baik dan dibuat dengan penuh perhatian. Setiap sajian kami siapkan agar terasa hangat, nyaman, dan selalu ingin dinikmati lagi.') }}</p>
                <p>Kami menyambut Anda untuk menikmati dimsum bersama keluarga, teman, atau sebagai teman istirahat di tengah kesibukan.</p>
                <a href="#menu" class="inline-flex items-center gap-2 font-semibold text-[#a84127] hover:text-[#87351f]">Kenali menu kami <x-icon name="arrow-up-right" size="{{ 18 }}" /></a>
            </div>
        </section>

        <section id="menu" class="bg-[#f7eee5] px-5 py-20 sm:px-8 md:py-24">
            <div class="mx-auto max-w-5xl">
                <div class="scroll-reveal mb-8">
                    <p class="text-xs font-bold uppercase tracking-[0.2em] text-[#a84127]">Menu</p>
                    <div class="mt-2 flex flex-col justify-between gap-3 sm:flex-row sm:items-end">
                        <div><h2 class="font-serif text-4xl font-semibold sm:text-5xl">Dimsum, lumpia &amp; lainnya.</h2><p class="mt-3 text-sm text-[#75645c]">Pilih kategori untuk melihat menu dan harga.</p></div>
                        <a href="{{ $gofoodUrl }}" target="_blank" rel="noreferrer" class="inline-flex w-fit items-center gap-2 rounded-full bg-[#a84127] px-5 py-3 text-sm font-semibold text-white transition hover:bg-[#87351f]">Pesan lewat GoFood <x-icon name="arrow-up-right" size="{{ 17 }}" /></a>
                    </div>
                </div>
                @include('livewire.public-site.partials.menu-prices')
            </div>
        </section>

        <section id="outlet" class="border-t border-[#ead9c8] bg-white px-5 py-24 sm:px-8 md:py-28">
            <div class="mx-auto max-w-7xl">
                <div class="scroll-reveal mb-8 max-w-xl"><p class="text-xs font-bold uppercase tracking-[0.2em] text-[#a84127]">Kami menanti Anda</p><h2 class="mt-3 font-serif text-4xl font-semibold sm:text-5xl">Mampir atau sapa kami.</h2><p class="mt-3 text-sm leading-6 text-[#75645c]">Pilih cabang terdekat dan cek jam buka kami.</p></div>
                <div class="grid gap-3 sm:grid-cols-2">
                    @foreach ($displayBranches as $branch)

                        <details class="group rounded-2xl border border-[#f0e4d8] bg-[#fffaf4] shadow-[0_6px_20px_rgba(80,47,31,0.04)] transition hover:border-[#dfc5b2] hover:shadow-[0_12px_28px_rgba(80,47,31,0.08)]">
                            <summary class="flex cursor-pointer list-none items-center gap-3 p-4 marker:hidden [&amp;::-webkit-details-marker]:hidden">
                                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-[#f4e4d5] text-[#a84127]"><x-icon name="map-pin" size="{{ 19 }}" /></span>
                                <span class="min-w-0 flex-1">
                                    <span class="block font-serif text-base font-semibold leading-5">{{ $branch['name'] }}</span>
                                    <span class="mt-1 block truncate text-xs text-[#75645c]">{{ $branch['address'] }}</span>
                                </span>
                                <x-icon name="arrow-down" size="{{ 18 }}" class="shrink-0 text-[#a84127] transition-transform group-open:rotate-180" aria-hidden="true" />
                            </summary>
                            <div class="space-y-3 border-t border-[#ead9c8] px-4 pb-4 pt-3">
                                <a href="{{ $branch['map'] }}" target="_blank" rel="noreferrer" class="inline-flex items-center gap-1 rounded-full border border-[#d9c5b7] px-3 py-1.5 text-xs font-semibold text-[#8f3825] transition hover:border-[#a84127] hover:bg-white" aria-label="{{ 'Buka peta ' . ($branch['name']) }}">
                                    Lihat peta <x-icon name="arrow-up-right" size="{{ 14 }}" />
                                </a>
                                <div class="space-y-2">
                                    @foreach ($branch['hours'] as $schedule)

                                        <div class="grid grid-cols-[minmax(0,1fr)_auto] items-center gap-x-4 text-sm">
                                            <span class="flex items-center gap-2 text-[#75645c]"><x-icon name="clock" size="{{ 15 }}" class="shrink-0" />{{ $schedule['days'] }}</span>
                                            <span class="font-semibold tabular-nums text-[#49372f]">{{ $schedule['time'] }} WIB</span>
                                        </div>
                                    
@endforeach
                                </div>
                            </div>
                        </details>
                    
@endforeach
                </div>
                <div class="scroll-reveal mt-6 flex flex-col justify-between gap-6 rounded-3xl bg-[#34251f] p-7 text-white sm:flex-row sm:items-center sm:p-9">
                    <div class="flex items-start gap-4">
                        <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-white/10 text-[#f0c3a7]"><x-icon name="phone" size="{{ 23 }}" /></span>
                        <div><p class="text-xs font-bold uppercase tracking-[0.18em] text-[#e9b99f]">Kontak</p><h3 class="mt-1 font-serif text-2xl font-semibold">Kami siap menyapa.</h3><div class="mt-3 flex flex-col gap-1 text-sm text-white/75 sm:flex-row sm:gap-5"><a href="{{ 'tel:' . ($phone) }}" class="transition hover:text-white">{{ $phone }}</a><a href="{{ 'mailto:' . ($email) }}" class="transition hover:text-white">{{ $email }}</a></div></div>
                    </div>
                    <div class="w-full space-y-3 sm:max-w-xl">
                        <a href="{{ 'https://wa.me/' . ($whatsappNumber) }}" target="_blank" rel="noreferrer" class="inline-flex items-center justify-center rounded-full bg-[#a84127] px-5 py-3 text-sm font-semibold text-white transition hover:bg-[#87351f]">Hubungi CS via WhatsApp</a>
                        <details class="rounded-2xl border border-white/15 bg-white/5 p-4">
                            <summary class="cursor-pointer list-none font-semibold text-white marker:hidden [&amp;::-webkit-details-marker]:hidden">Laporkan produk ke CS</summary>
                            <div class="mt-4 rounded-xl bg-[#fffaf4] p-4 text-[#34251f] sm:p-5">
                                <p class="text-sm leading-6 text-[#695850]">Isi detail laporan. WhatsApp akan terbuka dengan pesan yang bisa Anda tinjau sebelum dikirim.</p>
                                <form x-data="productReport('{{ $whatsappNumber }}')" @submit.prevent="openReport($event)" class="mt-4 space-y-3">
                                    <label class="block text-sm font-semibold">Produk yang dilaporkan
                                        <select required x-model="report.product" class="mt-1.5 h-11 w-full rounded-xl border border-[#e2d3c6] bg-white px-3 font-normal text-[#34251f] focus:border-[#a84127] focus:outline-none focus:ring-2 focus:ring-[#a84127]/15">
                                            <option value="">Pilih produk</option>
                                            @foreach ($menuCategories as $category)
@foreach ($category['items'] as $item)
<option value="{{ $item['name'] }}">{{ $item['name'] }}</option>
@endforeach
@endforeach
                                        </select>
                                    </label>
                                    <div class="grid gap-3 sm:grid-cols-2">
                                        <label class="block text-sm font-semibold">Cabang <span class="font-normal text-[#9a8275]">(opsional)</span>
                                            <select x-model="report.branch" class="mt-1.5 h-11 w-full rounded-xl border border-[#e2d3c6] bg-white px-3 font-normal text-[#34251f] focus:border-[#a84127] focus:outline-none focus:ring-2 focus:ring-[#a84127]/15">
                                                <option value="">Pilih cabang</option>
                                                @foreach ($displayBranches as $branch)
<option value="{{ $branch['name'] }}">{{ $branch['name'] }}</option>
@endforeach
                                            </select>
                                        </label>
                                        <label class="block text-sm font-semibold">Waktu pembelian <span class="font-normal text-[#9a8275]">(opsional)</span>
                                            <input type="date" x-model="report.purchasedAt" class="mt-1.5 h-11 w-full rounded-xl border border-[#e2d3c6] bg-white px-3 font-normal text-[#34251f] focus:border-[#a84127] focus:outline-none focus:ring-2 focus:ring-[#a84127]/15" />
                                        </label>
                                    </div>
                                    <label class="block text-sm font-semibold">Ceritakan kendala atau aduan
                                        <textarea required minlength="10" maxlength="1000" rows="{{ 4 }}" x-model="report.details" placeholder="Tuliskan kondisi produk dan kronologi singkat…" class="mt-1.5 w-full resize-y rounded-xl border border-[#e2d3c6] bg-white px-3 py-2.5 font-normal text-[#34251f] placeholder:text-[#a89589] focus:border-[#a84127] focus:outline-none focus:ring-2 focus:ring-[#a84127]/15"></textarea>
                                    </label>
                                    <label class="flex items-start gap-2.5 text-xs leading-5 text-[#695850]">
                                        <input required type="checkbox" x-model="report.agreed" class="mt-1 h-4 w-4 shrink-0 accent-[#a84127]" />
                                        <span>Saya memastikan laporan ini benar dan terkait produk Weigu. Saya paham pesan baru terkirim setelah saya meninjaunya lalu menekan tombol kirim di WhatsApp. Saya tidak mencantumkan kata sandi, data pembayaran, atau informasi sensitif.</span>
                                    </label>
                                    <button type="submit" class="inline-flex w-full items-center justify-center rounded-full bg-[#a84127] px-5 py-3 text-sm font-semibold text-white transition hover:bg-[#87351f] sm:w-auto">Buka WhatsApp &amp; Tinjau Pesan</button>
                                </form>
                            </div>
                        </details>
                    </div>
                </div>
            </div>
        </section>

    </main>

    <footer class="border-t border-white/10 bg-[#2e211c] px-5 py-10 text-white sm:px-8">
        <div class="mx-auto flex max-w-7xl flex-col items-center justify-between gap-6 text-center sm:flex-row sm:text-left">
            <div class="flex items-center gap-4"><img src="/assets/logo/logo-square.png" alt="Logo Weigu Dimsum &amp; Gyoza" class="h-14 w-14 rounded-2xl object-cover shadow-lg shadow-black/20" /><div><p class="font-serif text-xl font-semibold">{{ ($business['name'] ?: "Weigu Dimsum & Gyoza") }}</p><p class="mt-1 text-sm text-white/60">Dimsum hangat, dinikmati bersama.</p></div></div>
            <nav aria-label="Navigasi footer" class="flex flex-wrap justify-center gap-x-5 gap-y-3 text-sm text-white/70">
                <a href="#menu" class="transition hover:text-white">Lihat menu</a>
                <a href="#outlet" class="transition hover:text-white">Lokasi cabang</a>
                <a href="{{ 'https://wa.me/' . ($whatsappNumber) }}" target="_blank" rel="noreferrer" class="transition hover:text-white">WhatsApp CS</a>
                <a href="{{ 'mailto:' . ($email) }}" class="transition hover:text-white">Email</a>
            </nav>
            <p class="text-xs text-white/50">© {{ now()->year }} {{ ($business['name'] ?: "Dimsum") }}</p>
        </div>
    </footer>
</div>
@assets
<script src="{{ \App\Support\StaticAssets::url('homepage.js') }}"></script>
@endassets
