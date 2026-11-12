<div>
    {{-- Header Banner Toko --}}
    <div class="bg-gradient-to-r from-slate-900 via-indigo-950 to-slate-900 text-white py-10 px-4 sm:px-6 lg:px-8 border-b border-indigo-900/50 shadow-sm">
        <div class="max-w-7xl mx-auto flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-indigo-500/20 text-indigo-300 border border-indigo-500/30 mb-2">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                    Toko Resmi Helm & Aksesori
                </span>
                <h1 class="text-3xl font-extrabold tracking-tight text-white sm:text-4xl">
                    Katalog Bayeng Putra Helm
                </h1>
                <p class="mt-2 text-sm text-slate-300 max-w-2xl">
                    Temukan helm berstandar SNI dan DOT dengan berbagai pilihan merek, tipe, warna, dan ukuran terbaik untuk keselamatan berkendara Anda.
                </p>
            </div>
            <div class="text-xs text-slate-400 bg-slate-800/60 p-3 rounded-xl border border-slate-700/60 flex items-start gap-2.5 max-w-sm">
                <svg class="w-4 h-4 text-indigo-400 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                <div>
                    <span class="font-semibold text-slate-200 block">Lokasi Toko Fisik:</span>
                    <span>Jl. RE Martadinata No. 49, Cijoho – Kuningan</span>
                </div>
            </div>
        </div>
    </div>

    {{-- Main Container --}}
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        {{-- Filter & Search Bar --}}
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 mb-8">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                {{-- Pencarian --}}
                <div class="relative">
                    <label for="search" class="block text-xs font-semibold text-gray-600 mb-1">Cari Produk</label>
                    <div class="relative">
                        <input
                            type="text"
                            id="search"
                            wire:model.live.debounce.350ms="search"
                            placeholder="Ketik nama helm atau merek..."
                            class="w-full pl-9 pr-4 py-2 text-sm border-gray-300 rounded-xl focus:ring-indigo-500 focus:border-indigo-500 placeholder-gray-400"
                        />
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-gray-400">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        </div>
                    </div>
                </div>

                {{-- Filter Kategori --}}
                <div>
                    <label for="category" class="block text-xs font-semibold text-gray-600 mb-1">Kategori Tipe</label>
                    <select
                        id="category"
                        wire:model.live="selectedCategory"
                        class="w-full py-2 px-3 text-sm border-gray-300 rounded-xl focus:ring-indigo-500 focus:border-indigo-500"
                    >
                        <option value="">Semua Kategori</option>
                        @foreach ($categories as $cat)
                            <option value="{{ $cat->slug }}">{{ $cat->nama }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Filter Merek --}}
                <div>
                    <label for="brand" class="block text-xs font-semibold text-gray-600 mb-1">Merek</label>
                    <select
                        id="brand"
                        wire:model.live="selectedBrand"
                        class="w-full py-2 px-3 text-sm border-gray-300 rounded-xl focus:ring-indigo-500 focus:border-indigo-500"
                    >
                        <option value="">Semua Merek</option>
                        @foreach ($brands as $br)
                            <option value="{{ $br->slug }}">{{ $br->nama }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Urutkan --}}
                <div>
                    <label for="sort" class="block text-xs font-semibold text-gray-600 mb-1">Urutan</label>
                    <select
                        id="sort"
                        wire:model.live="sortBy"
                        class="w-full py-2 px-3 text-sm border-gray-300 rounded-xl focus:ring-indigo-500 focus:border-indigo-500"
                    >
                        <option value="terbaru">Terbaru Ditambahkan</option>
                        <option value="nama_asc">Nama (A - Z)</option>
                        <option value="nama_desc">Nama (Z - A)</option>
                    </select>
                </div>
            </div>

            {{-- Filter Chips & Reset --}}
            @if (!empty($search) || !empty($selectedCategory) || !empty($selectedBrand) || $sortBy !== 'terbaru')
                <div class="mt-4 pt-3 border-t border-gray-100 flex flex-wrap items-center gap-2 text-xs">
                    <span class="text-gray-500 font-medium">Filter Aktif:</span>
                    @if (!empty($search))
                        <span class="inline-flex items-center gap-1 bg-indigo-50 text-indigo-700 px-2.5 py-1 rounded-full font-medium">
                            Cari: "{{ $search }}"
                            <button type="button" wire:click="$set('search', '')" class="hover:text-indigo-900">&times;</button>
                        </span>
                    @endif
                    @if (!empty($selectedCategory))
                        <span class="inline-flex items-center gap-1 bg-indigo-50 text-indigo-700 px-2.5 py-1 rounded-full font-medium">
                            Kategori: {{ $categories->firstWhere('slug', $selectedCategory)?->nama ?? $selectedCategory }}
                            <button type="button" wire:click="$set('selectedCategory', '')" class="hover:text-indigo-900">&times;</button>
                        </span>
                    @endif
                    @if (!empty($selectedBrand))
                        <span class="inline-flex items-center gap-1 bg-indigo-50 text-indigo-700 px-2.5 py-1 rounded-full font-medium">
                            Merek: {{ $brands->firstWhere('slug', $selectedBrand)?->nama ?? $selectedBrand }}
                            <button type="button" wire:click="$set('selectedBrand', '')" class="hover:text-indigo-900">&times;</button>
                        </span>
                    @endif

                    <button
                        type="button"
                        wire:click="resetFilters"
                        class="ml-auto text-indigo-600 hover:text-indigo-800 font-medium hover:underline text-xs"
                    >
                        Reset Semua Filter
                    </button>
                </div>
            @endif
        </div>

        {{-- Loading Indicator --}}
        <div wire:loading.delay class="w-full text-center py-6">
            <span class="inline-flex items-center gap-2 text-sm text-indigo-600 font-medium bg-indigo-50 px-4 py-2 rounded-full">
                <svg class="animate-spin h-4 w-4 text-indigo-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
                Memuat data katalog...
            </span>
        </div>

        {{-- Product Grid --}}
        @if ($products->isNotEmpty())
            <div wire:loading.class="opacity-50" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
                @foreach ($products as $product)
                    @php
                        $primaryImage = $product->images->firstWhere('is_primary', true) ?? $product->images->first();
                        $minPrice = $product->variants->min('harga');
                        $maxPrice = $product->variants->max('harga');
                        $totalStock = $product->variants->sum(fn ($v) => $v->stok_tersedia);
                        $hasActiveVariants = $product->variants->isNotEmpty();
                    @endphp

                    <div class="bg-white rounded-2xl overflow-hidden border border-gray-100 shadow-sm hover:shadow-md transition duration-200 flex flex-col group">
                        {{-- Foto Produk --}}
                        <a href="{{ route('catalog.show', $product->slug) }}" wire:navigate class="relative aspect-square bg-gray-50 overflow-hidden block">
                            @if ($primaryImage && $primaryImage->file_path)
                                <img
                                    src="{{ asset('storage/' . $primaryImage->file_path) }}"
                                    alt="{{ $primaryImage->alt_text ?? $product->nama }}"
                                    class="w-full h-full object-cover object-center group-hover:scale-105 transition duration-300"
                                    onerror="this.onerror=null; this.src='https://placehold.co/600x600/e2e8f0/475569?text=Bayeng+Helm';"
                                />
                            @else
                                <div class="w-full h-full flex flex-col items-center justify-center text-gray-300 p-4">
                                    <svg class="w-16 h-16 stroke-current" fill="none" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                    <span class="text-xs text-gray-400 mt-2 font-medium">Foto Belum Tersedia</span>
                                </div>
                            @endif

                            {{-- Badge Stok Tersedia --}}
                            <div class="absolute top-3 right-3">
                                @if ($totalStock > 0)
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200 shadow-sm">
                                        Stok {{ $totalStock }}
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-rose-50 text-rose-700 border border-rose-200 shadow-sm">
                                        Habis
                                    </span>
                                @endif
                            </div>
                        </a>

                        {{-- Info Produk --}}
                        <div class="p-4 flex flex-col flex-1">
                            <div class="flex items-center gap-1.5 text-xs text-gray-500 mb-1.5 font-medium">
                                <span class="text-indigo-600 font-semibold">{{ $product->brand?->nama ?? 'Umum' }}</span>
                                <span>&bull;</span>
                                <span>{{ $product->category?->nama ?? 'Helm' }}</span>
                            </div>

                            <h3 class="text-sm font-bold text-gray-900 group-hover:text-indigo-600 transition line-clamp-2 mb-2">
                                <a href="{{ route('catalog.show', $product->slug) }}" wire:navigate>
                                    {{ $product->nama }}
                                </a>
                            </h3>

                            <div class="mt-auto pt-3 border-t border-gray-50 flex items-baseline justify-between">
                                <div>
                                    <span class="text-xs text-gray-400 block font-medium">Mulai dari</span>
                                    <span class="text-base font-extrabold text-indigo-700">
                                        @if ($minPrice !== null)
                                            Rp {{ number_format((int) $minPrice, 0, ',', '.') }}
                                        @else
                                            <span class="text-xs text-gray-400 font-normal">Belum ada harga</span>
                                        @endif
                                    </span>
                                </div>
                                <a
                                    href="{{ route('catalog.show', $product->slug) }}"
                                    wire:navigate
                                    class="inline-flex items-center justify-center p-2 rounded-xl text-indigo-600 bg-indigo-50 hover:bg-indigo-600 hover:text-white transition"
                                    title="Lihat Detail Produk"
                                >
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                                </a>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- Pagination Links --}}
            <div class="mt-8">
                {{ $products->links() }}
            </div>
        @else
            {{-- Empty State --}}
            <div class="text-center py-16 px-4 bg-white rounded-2xl border border-gray-100 shadow-sm">
                <div class="mx-auto w-16 h-16 rounded-full bg-indigo-50 text-indigo-600 flex items-center justify-center mb-4">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                </div>
                <h3 class="text-lg font-bold text-gray-900 mb-1">Helm Tidak Ditemukan</h3>
                <p class="text-sm text-gray-500 max-w-md mx-auto mb-6">
                    Maaf, tidak ada produk helm yang cocok dengan kriteria filter atau kata kunci pencarian Anda.
                </p>
                <button
                    type="button"
                    wire:click="resetFilters"
                    class="inline-flex items-center px-4 py-2 border border-transparent rounded-xl text-sm font-semibold text-white bg-indigo-600 hover:bg-indigo-700 transition"
                >
                    Tampilkan Semua Produk
                </button>
            </div>
        @endif
    </div>
</div>