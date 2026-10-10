<div>
    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-4">
        <div><h2 class="h4 mb-1">Konten homepage</h2><p class="text-muted mb-0">Ubah tulisan yang tampil di halaman depan dan tambahkan foto menu saat siap.</p></div>
        <a href="{{ route('home') }}" target="_blank" rel="noreferrer" class="btn btn-outline-secondary">Lihat homepage <i class="bi bi-arrow-up-right ms-1" aria-hidden="true"></i></a>
    </div>

    @unless ($canEdit)<div class="alert alert-light border">Anda dapat melihat konten homepage, tetapi tidak memiliki izin untuk menyimpan perubahan.</div>@endunless

    <nav class="homepage-editor-navigation" aria-label="Bagian pengaturan homepage">
        <a href="#editor-hero">Bagian utama</a><a href="#editor-story">Cerita</a><a href="#editor-headings">Judul bagian</a><a href="#editor-images">Foto menu</a>
    </nav>
    <form wire:submit="save" class="homepage-editor">
        <section id="editor-hero" class="card mb-4">
            <div class="card-header"><h3 class="h6 mb-0">Bagian utama</h3><small class="text-muted">Teks pembuka yang terlihat pertama kali.</small></div>
            <div class="card-body row g-3">
                <div class="col-12 col-lg-6"><label class="form-label" for="hero-eyebrow">Label kecil</label><input id="hero-eyebrow" wire:model="content.hero_eyebrow" class="form-control" maxlength="100">@error('content.hero_eyebrow')<small class="text-danger">{{ $message }}</small>@enderror</div>
                <div class="col-12 col-lg-6"><label class="form-label" for="hero-title">Judul utama</label><input id="hero-title" wire:model="content.hero_title" class="form-control" maxlength="160">@error('content.hero_title')<small class="text-danger">{{ $message }}</small>@enderror</div>
                <div class="col-12"><label class="form-label" for="hero-caption">Caption</label><textarea id="hero-caption" wire:model="content.hero_caption" rows="3" class="form-control" maxlength="500"></textarea>@error('content.hero_caption')<small class="text-danger">{{ $message }}</small>@enderror</div>
                <div class="col-12 col-lg-8"><label class="form-label" for="order-url">Tautan pemesanan</label><input id="order-url" type="url" wire:model="content.order_url" class="form-control" maxlength="2048" placeholder="https://"><small class="text-muted">Masukkan tautan langsung toko Anda. Kosongkan untuk menggunakan WhatsApp toko.</small>@error('content.order_url')<small class="text-danger d-block">{{ $message }}</small>@enderror</div>
                <div class="col-12 col-lg-4"><label class="form-label" for="order-label">Teks tombol pemesanan</label><input id="order-label" wire:model="content.order_label" class="form-control" maxlength="60">@error('content.order_label')<small class="text-danger d-block">{{ $message }}</small>@enderror</div>
                <div class="col-12 col-lg-6"><label class="form-label" for="hero-image">Foto latar utama <span class="text-muted fw-normal">(opsional)</span></label><input id="hero-image" type="file" wire:model="heroImage" accept="image/jpeg,image/png,image/webp" class="form-control"><small class="text-muted">JPG, PNG, atau WebP, maksimal 4 MB.</small>@error('heroImage')<small class="text-danger d-block">{{ $message }}</small>@enderror</div>
                <div class="col-12 col-lg-6">@if ($heroImage && $heroImage->isPreviewable())<img src="{{ $heroImage->temporaryUrl() }}" alt="Pratinjau foto utama" class="homepage-editor-preview" loading="lazy" decoding="async">@elseif (!empty($content['hero_image']))<img src="{{ asset('storage/'.$content['hero_image']) }}" alt="Foto utama saat ini" class="homepage-editor-preview" loading="lazy" decoding="async">@else<div class="homepage-editor-empty">Foto utama bawaan masih digunakan.</div>@endif</div>
            </div>
        </section>

        <section id="editor-story" class="card mb-4">
            <div class="card-header"><h3 class="h6 mb-0">Cerita Weigu</h3><small class="text-muted">Bagian pengantar di bawah hero.</small></div>
            <div class="card-body row g-3">
                <div class="col-12 col-lg-6"><label class="form-label" for="editor-story_eyebrow">Label kecil</label><input id="editor-story_eyebrow" wire:model="content.story_eyebrow" class="form-control" maxlength="100">@error('content.story_eyebrow')<small class="text-danger">{{ $message }}</small>@enderror</div>
                <div class="col-12 col-lg-6"><label class="form-label" for="editor-story_title">Judul</label><input id="editor-story_title" wire:model="content.story_title" class="form-control" maxlength="160">@error('content.story_title')<small class="text-danger">{{ $message }}</small>@enderror</div>
                <div class="col-12"><label class="form-label" for="editor-story_body">Paragraf pertama</label><textarea id="editor-story_body" wire:model="content.story_body" rows="3" class="form-control" maxlength="1000"></textarea>@error('content.story_body')<small class="text-danger">{{ $message }}</small>@enderror</div>
                <div class="col-12"><label class="form-label" for="editor-story_second">Paragraf kedua</label><textarea id="editor-story_second" wire:model="content.story_second" rows="2" class="form-control" maxlength="500"></textarea>@error('content.story_second')<small class="text-danger">{{ $message }}</small>@enderror</div>
            </div>
        </section>

        <section id="editor-headings" class="card mb-4">
            <div class="card-header"><h3 class="h6 mb-0">Judul bagian menu dan outlet</h3></div>
            <div class="card-body row g-3">
                <div class="col-12 col-lg-6"><label class="form-label" for="editor-menu_title">Judul menu</label><input id="editor-menu_title" wire:model="content.menu_title" class="form-control" maxlength="120">@error('content.menu_title')<small class="text-danger">{{ $message }}</small>@enderror</div>
                <div class="col-12 col-lg-6"><label class="form-label" for="editor-menu_caption">Caption menu</label><input id="editor-menu_caption" wire:model="content.menu_caption" class="form-control" maxlength="300">@error('content.menu_caption')<small class="text-danger">{{ $message }}</small>@enderror</div>
                <div class="col-12 col-lg-6"><label class="form-label" for="editor-outlet_title">Judul outlet</label><input id="editor-outlet_title" wire:model="content.outlet_title" class="form-control" maxlength="120">@error('content.outlet_title')<small class="text-danger">{{ $message }}</small>@enderror</div>
                <div class="col-12 col-lg-6"><label class="form-label" for="editor-outlet_caption">Caption outlet</label><input id="editor-outlet_caption" wire:model="content.outlet_caption" class="form-control" maxlength="300">@error('content.outlet_caption')<small class="text-danger">{{ $message }}</small>@enderror</div>
            </div>
        </section>

        <section id="editor-images" class="card mb-4">
            <div class="card-header"><h3 class="h6 mb-0">Foto menu</h3><small class="text-muted">Harga dan nama produk mengikuti data menu saat ini. Foto bersifat opsional.</small></div>
            <div class="card-body"><div class="row g-3">
                @foreach ($menus as $menu)
                    <div class="col-12 col-md-6 col-xl-4"><div class="homepage-menu-upload">
                        @if (!empty($menuImages[$menu['slug']]) && $menuImages[$menu['slug']]->isPreviewable())<img src="{{ $menuImages[$menu['slug']]->temporaryUrl() }}" alt="Pratinjau {{ $menu['name'] }}" class="homepage-editor-preview" loading="lazy" decoding="async">
                        @elseif ($menu['image'])<img src="{{ asset('storage/'.$menu['image']) }}" alt="Foto {{ $menu['name'] }}" class="homepage-editor-preview" loading="lazy" decoding="async">
                        @else<div class="homepage-editor-empty"><i class="bi bi-image" aria-hidden="true"></i><span>Belum ada foto</span></div>@endif
                        <label class="form-label mt-3 mb-1" for="menu-image-{{ $menu['slug'] }}">{{ $menu['name'] }}</label>
                        <input id="menu-image-{{ $menu['slug'] }}" type="file" wire:model="menuImages.{{ $menu['slug'] }}" accept="image/jpeg,image/png,image/webp" class="form-control form-control-sm">
                        @error('menuImages.'.$menu['slug'])<small class="text-danger d-block">{{ $message }}</small>@enderror
                        @if ($menu['image'])<label class="form-check mt-2"><input type="checkbox" class="form-check-input" wire:model="removeMenuImages.{{ $menu['slug'] }}"><span class="form-check-label">Hapus foto saat ini</span></label>@endif
                    </div></div>
                @endforeach
            </div></div>
        </section>

        @if ($canEdit)<div class="homepage-editor-save"><span class="text-muted small">Perubahan langsung tampil setelah disimpan.</span><button type="submit" class="btn btn-dark px-4" wire:loading.attr="disabled"><span wire:loading.remove>Simpan perubahan</span><span wire:loading>Menyimpan…</span></button></div>@endif
    </form>
</div>
