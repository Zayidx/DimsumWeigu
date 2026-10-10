<div x-data="homepage" class="homepage-page min-h-screen bg-[#fffaf4] text-[#34251f]">
    <header class="homepage-header sticky top-0 z-20 border-b border-[#ead9c8] bg-[#fffaf4]/95 backdrop-blur">
        <nav class="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-y-3 px-5 py-5 sm:px-8">
            <a href="#beranda" class="flex items-center gap-3" aria-label="{{ ($business['name']) . ' â€” beranda' }}">
                <img src="/assets/logo/logo-no-background.png" alt="Weigu Dimsum &amp; Gyoza" class="h-10 w-auto max-w-[160px] object-contain sm:h-12" />
            </a>
            <div class="hidden items-center gap-8 text-sm font-medium text-[#695850] md:flex">
                <a href="#cerita" class="transition hover:text-[#a84127]">Cerita Kami</a>
                <a href="#menu" class="transition hover:text-[#a84127]">Menu</a>
                <a href="#outlet" class="transition hover:text-[#a84127]">Outlet</a>
            </div>
        </nav>
    </header>

    <main>
        <section id="beranda" class="homepage-hero">
            <div class="homepage-hero-shell">
                <div class="homepage-hero-copy scroll-reveal">
                    <p class="homepage-eyebrow">{{ $homepage['hero_eyebrow'] }}</p>
                    <h1>{{ $homepage['hero_title'] }}</h1>
                    <p class="homepage-hero-caption">{{ $homepage['hero_caption'] }}</p>
                    <div class="homepage-hero-actions">
                        <a href="#menu" class="homepage-button homepage-button-primary">Lihat menu <x-icon name="arrow-down" size="{{ 18 }}" /></a>
                        <a href="{{ $orderUrl }}" target="_blank" rel="noreferrer" class="homepage-button homepage-button-secondary">{{ $orderLabel }} <x-icon name="arrow-up-right" size="{{ 17 }}" /></a>
                    </div>
                    <p class="homepage-hero-signature"><span aria-hidden="true"></span>{{ $business['name'] }}</p>
                </div>
                <figure class="homepage-hero-visual scroll-reveal" aria-label="Hidangan dimsum Weigu">
                    @if (!empty($homepage['hero_image']))
                        <img src="{{ asset('storage/'.$homepage['hero_image']) }}" alt="Hidangan Weigu" fetchpriority="high" decoding="async">
                    @else
                        <img src="{{ asset('images/dimsum-hero-960.webp') }}"
                            srcset="{{ asset('images/dimsum-hero-640.webp') }} 640w, {{ asset('images/dimsum-hero-960.webp') }} 960w, {{ asset('images/dimsum-hero-1440.webp') }} 1440w"
                            sizes="(max-width: 767px) calc(100vw - 40px), (max-width: 1280px) 50vw, 600px"
                            width="1536" height="1024" alt="Dimsum dalam kukusan bambu disajikan hangat" fetchpriority="high" decoding="async">
                    @endif
                    <figcaption>Weigu Dimsum &amp; Gyoza</figcaption>
                </figure>
            </div>
        </section>

        <section id="cerita" class="mx-auto grid max-w-7xl gap-12 px-5 py-24 sm:px-8 md:grid-cols-[0.8fr_1.2fr] md:items-center md:py-32">
            <div class="scroll-reveal">
                <p class="text-xs font-bold uppercase tracking-[0.2em] text-[#a84127]">{{ $homepage['story_eyebrow'] }}</p>
                <h2 class="mt-4 font-serif text-4xl font-semibold leading-tight sm:text-5xl">{{ $homepage['story_title'] }}</h2>
            </div>
            <div class="scroll-reveal space-y-5 text-base leading-8 text-[#695850]" style="--reveal-delay: 120ms">
                <p>{{ $homepage['story_body'] }}</p>
                <p>{{ $homepage['story_second'] }}</p>
                <a href="#menu" class="inline-flex items-center gap-2 font-semibold text-[#a84127] hover:text-[#87351f]">Kenali menu kami <x-icon name="arrow-up-right" size="{{ 18 }}" /></a>
            </div>
        </section>

        <section id="menu" class="bg-[#f7eee5] px-5 py-20 sm:px-8 md:py-24">
            <div class="mx-auto max-w-5xl">
                <div class="scroll-reveal mb-8">
                    <p class="text-xs font-bold uppercase tracking-[0.2em] text-[#a84127]">Menu</p>
                    <div class="mt-2 flex flex-col justify-between gap-3 sm:flex-row sm:items-end">
                        <div><h2 class="font-serif text-4xl font-semibold sm:text-5xl">{{ $homepage['menu_title'] }}</h2><p class="mt-3 text-sm text-[#75645c]">{{ $homepage['menu_caption'] }}</p></div>
                        <a href="{{ $orderUrl }}" target="_blank" rel="noreferrer" class="inline-flex w-fit items-center gap-2 rounded-full bg-[#a84127] px-5 py-3 text-sm font-semibold text-white transition hover:bg-[#87351f]">{{ $orderLabel }} <x-icon name="arrow-up-right" size="{{ 17 }}" /></a>
                    </div>
                </div>
                @include('livewire.public-site.partials.menu-prices')
            </div>
        </section>

        <section id="outlet" class="border-t border-[#ead9c8] bg-white px-5 py-24 sm:px-8 md:py-28">
            <div class="mx-auto max-w-7xl">
                <div class="scroll-reveal mb-8 max-w-xl"><p class="text-xs font-bold uppercase tracking-[0.2em] text-[#a84127]">Lokasi dan jam buka</p><h2 class="mt-3 font-serif text-4xl font-semibold sm:text-5xl">{{ $homepage['outlet_title'] }}</h2><p class="mt-3 text-sm leading-6 text-[#75645c]">{{ $homepage['outlet_caption'] }}</p></div>
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
                        <div><p class="text-xs font-bold uppercase tracking-[0.18em] text-[#e9b99f]">Kontak</p><h3 class="mt-1 font-serif text-2xl font-semibold">Hubungi Weigu.</h3><div class="mt-3 flex flex-col gap-1 text-sm text-white/75 sm:flex-row sm:gap-5"><a href="{{ 'tel:' . ($phone) }}" class="transition hover:text-white">{{ $phone }}</a><a href="{{ 'mailto:' . ($email) }}" class="transition hover:text-white">{{ $email }}</a></div></div>
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
                                        <textarea required minlength="10" maxlength="1000" rows="{{ 4 }}" x-model="report.details" placeholder="Tuliskan kondisi produk dan kronologi singkatâ€¦" class="mt-1.5 w-full resize-y rounded-xl border border-[#e2d3c6] bg-white px-3 py-2.5 font-normal text-[#34251f] placeholder:text-[#a89589] focus:border-[#a84127] focus:outline-none focus:ring-2 focus:ring-[#a84127]/15"></textarea>
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
            <div class="flex items-center gap-4"><img src="/assets/logo/logo-square.png" alt="Logo Weigu Dimsum &amp; Gyoza" class="h-14 w-14 rounded-2xl object-cover shadow-lg shadow-black/20" /><div><p class="font-serif text-xl font-semibold">{{ ($business['name'] ?: "Weigu Dimsum & Gyoza") }}</p><p class="mt-1 text-sm text-white/60">Dimsum Â· lumpia Â· Chiquro</p></div></div>
            <nav aria-label="Navigasi footer" class="flex flex-wrap justify-center gap-x-5 gap-y-3 text-sm text-white/70">
                <a href="#menu" class="transition hover:text-white">Lihat menu</a>
                <a href="#outlet" class="transition hover:text-white">Lokasi cabang</a>
                <a href="{{ 'https://wa.me/' . ($whatsappNumber) }}" target="_blank" rel="noreferrer" class="transition hover:text-white">WhatsApp CS</a>
                <a href="{{ 'mailto:' . ($email) }}" class="transition hover:text-white">Email</a>
            </nav>
            <p class="text-xs text-white/50">Â© {{ now()->year }} {{ ($business['name'] ?: "Dimsum") }}</p>
        </div>
    </footer>
    <nav class="homepage-bottom-nav" aria-label="Navigasi homepage">
        <a href="#beranda" aria-current="location" data-homepage-nav-link>
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m3 10 9-7 9 7v10a1 1 0 0 1-1 1h-5v-7H9v7H4a1 1 0 0 1-1-1z" /></svg>
            <span>Beranda</span>
        </a>
        <a href="#menu" data-homepage-nav-link>
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h16" /></svg>
            <span>Menu</span>
        </a>
        <a href="#outlet" data-homepage-nav-link>
            <x-icon name="map-pin" size="{{ 21 }}" />
            <span>Outlet</span>
        </a>
        <a href="{{ 'https://wa.me/' . ($whatsappNumber) }}" target="_blank" rel="noreferrer">
            <x-icon name="phone" size="{{ 21 }}" />
            <span>WhatsApp</span>
        </a>
    </nav>
</div>
@assets
<script src="{{ \App\Support\StaticAssets::url('homepage.js') }}"></script>
@endassets
