<div x-data="{ activeCategory: 'dimsum-kukus' }">
    <div role="tablist" aria-label="Kategori menu" class="scroll-reveal -mx-5 mb-6 flex gap-2 overflow-x-auto px-5 pb-2 sm:mx-0 sm:px-0">
        @foreach ($menuCategories as $category)
            <button
                id="tab-{{ $category['id'] }}"
                type="button"
                role="tab"
                aria-selected="{{ $loop->first ? 'true' : 'false' }}"
                :aria-selected="activeCategory === '{{ $category['id'] }}'"
                aria-controls="panel-{{ $category['id'] }}"
                @click="activeCategory = '{{ $category['id'] }}'"
                class="shrink-0 rounded-full border px-4 py-2.5 text-sm font-semibold transition"
                :class="activeCategory === '{{ $category['id'] }}' ? 'border-[#a84127] bg-[#a84127] text-white' : 'border-[#e7d8ca] bg-white text-[#674d41] hover:border-[#bd795d]'"
            >
                {{ $category['title'] }}
            </button>
        @endforeach
    </div>
    @foreach ($menuCategories as $category)
        <div id="panel-{{ $category['id'] }}" role="tabpanel" aria-labelledby="tab-{{ $category['id'] }}" x-show="activeCategory === '{{ $category['id'] }}'" @if (! $loop->first) x-cloak @endif class="grid gap-4 sm:grid-cols-2">
            @foreach ($category['items'] as $item)
                <article class="group rounded-2xl border border-[#ead9c8] bg-white p-5 shadow-[0_8px_24px_rgba(80,47,31,0.04)] transition duration-300 hover:-translate-y-1 hover:border-[#d7b8a2] hover:shadow-[0_16px_32px_rgba(80,47,31,0.09)]">
                    <div class="mb-4 flex items-center justify-between gap-3 border-b border-[#f1e7df] pb-3">
                        <h3 class="font-serif text-xl font-semibold text-[#3d2c25]">{{ $item['name'] }}</h3>
                        <span class="text-xs font-medium text-[#9a8275]">{{ count($item['variants']) > 1 ? 'Pilih porsi' : 'Harga' }}</span>
                    </div>
                    <div class="grid grid-cols-2 gap-2 sm:grid-cols-3">
                        @foreach ($item['variants'] as $variant)
                            <div class="min-h-[72px] rounded-xl border px-3 py-2.5 {{ ($variant['recommended'] ?? false) ? 'border-[#d9a58d] bg-[#fff2e9]' : 'border-transparent bg-[#faf6f1]' }}">
                                <div class="flex min-h-4 items-center justify-between gap-1">
                                    <span class="text-xs font-medium text-[#806a5f]">{{ $variant['label'] }}</span>
                                    @if ($variant['recommended'] ?? false)
                                        <span class="whitespace-nowrap rounded-full bg-[#a84127] px-1.5 py-0.5 text-[9px] font-bold uppercase tracking-wide text-white">Rekomendasi</span>
                                    @endif
                                </div>
                                <span class="mt-2 block whitespace-nowrap text-sm font-bold tabular-nums text-[#34251f]">Rp{{ "\u{00a0}" }}{{ number_format($variant['price'] ?? 0, 0, ',', '.') }}</span>
                            </div>
                        @endforeach
                    </div>
                </article>
            @endforeach
        </div>
    @endforeach
</div>
