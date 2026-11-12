<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    {{-- Breadcrumb Navigation --}}
    <nav class="flex items-center text-xs text-gray-500 mb-6 font-medium space-x-2">
        <a href="{{ route('catalog.index') }}" wire:navigate class="hover:text-indigo-600 transition">Katalog Helm</a>
        <span>/</span>
        @if ($product->category)
            <a href="{{ route('catalog.index', ['kategori' => $product->category->slug]) }}" wire:navigate class="hover:text-indigo-600 transition">
                {{ $product->category->nama }}
            </a>
            <span>/</span>
        @endif
        <span class="text-gray-900 font-semibold truncate max-w-md">{{ $product->nama }}</span>
    </nav>

    {{-- Main Product Card --}}
    <div class="bg-white rounded-3xl border border-gray-100 shadow-sm p-6 lg:p-8">
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 lg:gap-12">
            {{-- Kolom Kiri: Galeri Foto --}}
            <div class="lg:col-span-6 flex flex-col gap-4">
                {{-- Foto Utama --}}
                <div class="relative aspect-square bg-gray-50 rounded-2xl overflow-hidden border border-gray-100">
                    @if ($activePhotoUrl)
                        <img
                            src="{{ asset('storage/' . $activePhotoUrl) }}"
                            alt="{{ $product->nama }}"
                            class="w-full h-full object-cover object-center transition duration-300"
                            onerror="this.onerror=null; this.src='https://placehold.co/800x800/e2e8f0/475569?text=Bayeng+Helm';"
                        />
                    @else
                        <div class="w-full h-full flex flex-col items-center justify-center text-gray-300 p-8">
                            <svg class="w-24 h-24 stroke-current" fill="none" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                            <span class="text-sm text-gray-400 mt-2 font-medium">Foto Produk Belum Tersedia</span>
                        </div>
                    @endif

                    @if ($this->selectedVariant)
                        <div class="absolute top-4 right-4">
                            @if ($this->selectedVariant->stok_tersedia > 0)
                                <span class="px-3 py-1.5 rounded-full text-xs font-bold bg-emerald-50 text-emerald-700 border border-emerald-200 shadow-sm">
                                    Stok Tersedia: {{ $this->selectedVariant->stok_tersedia }} unit
                                </span>
                            @else
                                <span class="px-3 py-1.5 rounded-full text-xs font-bold bg-rose-50 text-rose-700 border border-rose-200 shadow-sm">
                                    Stok Varian Habis
                                </span>
                            @endif
                        </div>
                    @endif
                </div>

                {{-- Thumbnail Gallery --}}
                @if ($product->images->count() > 1)
                    <div class="grid grid-cols-5 gap-3">
                        @foreach ($product->images as $img)
                            <button
                                type="button"
                                wire:click="setActivePhoto('{{ $img->file_path }}')"
                                class="relative aspect-square rounded-xl overflow-hidden border-2 transition {{ $activePhotoUrl === $img->file_path ? 'border-indigo-600 ring-2 ring-indigo-200' : 'border-gray-200 hover:border-gray-300' }}"
                            >
                                <img
                                    src="{{ asset('storage/' . $img->file_path) }}"
                                    alt="{{ $img->alt_text ?? 'Foto Helm' }}"
                                    class="w-full h-full object-cover object-center"
                                    onerror="this.onerror=null; this.src='https://placehold.co/200x200/e2e8f0/475569?text=Thumb';"
                                />
                            </button>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- Kolom Kanan: Rincian & Pilihan Varian --}}
            <div class="lg:col-span-6 flex flex-col">
                {{-- Kategori & Merek --}}
                <div class="flex items-center gap-2 mb-2">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-md text-xs font-semibold bg-indigo-50 text-indigo-700 border border-indigo-100">
                        {{ $product->brand?->nama ?? 'Umum' }}
                    </span>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-md text-xs font-semibold bg-slate-100 text-slate-700">
                        {{ $product->category?->nama ?? 'Helm' }}
                    </span>
                </div>

                {{-- Nama Produk --}}
                <h1 class="text-2xl sm:text-3xl font-extrabold text-gray-900 tracking-tight mb-4">
                    {{ $product->nama }}
                </h1>

                {{-- Harga --}}
                <div class="p-4 bg-slate-50 rounded-2xl border border-slate-100 mb-6">
                    <span class="text-xs text-gray-500 font-medium block mb-1">Harga Resmi Produk</span>
                    <div class="text-3xl font-black text-indigo-700">
                        @if ($this->selectedVariant)
                            Rp {{ number_format((int) $this->selectedVariant->harga, 0, ',', '.') }}
                        @else
                            @php
                                $minPrice = $product->variants->min('harga');
                                $maxPrice = $product->variants->max('harga');
                            @endphp
                            @if ($minPrice !== null && $maxPrice !== null && $minPrice !== $maxPrice)
                                Rp {{ number_format((int) $minPrice, 0, ',', '.') }} - Rp {{ number_format((int) $maxPrice, 0, ',', '.') }}
                            @elseif ($minPrice !== null)
                                Rp {{ number_format((int) $minPrice, 0, ',', '.') }}
                            @else
                                <span class="text-base font-normal text-gray-400">Harga belum diatur</span>
                            @endif
                        @endif
                    </div>
                </div>

                {{-- Pilihan Varian Warna --}}
                @if (!empty($this->availableColors))
                    <div class="mb-5">
                        <label class="block text-xs font-bold uppercase tracking-wider text-gray-700 mb-2">
                            Pilihan Warna: <span class="text-indigo-600 font-semibold">{{ $selectedColor ?: 'Pilih warna' }}</span>
                        </label>
                        <div class="flex flex-wrap gap-2.5">
                            @foreach ($this->availableColors as $color)
                                <button
                                    type="button"
                                    wire:click="selectColor('{{ $color }}')"
                                    class="px-4 py-2 text-xs font-semibold rounded-xl border transition {{ $selectedColor === $color ? 'bg-indigo-600 text-white border-indigo-600 shadow-sm ring-2 ring-indigo-200' : 'bg-white text-gray-800 border-gray-200 hover:border-gray-400' }}"
                                >
                                    {{ $color }}
                                </button>
                            @endforeach
                        </div>
                    </div>
                @endif

                {{-- Pilihan Varian Ukuran --}}
                @if (!empty($this->availableSizes))
                    <div class="mb-6">
                        <label class="block text-xs font-bold uppercase tracking-wider text-gray-700 mb-2">
                            Pilihan Ukuran: <span class="text-indigo-600 font-semibold">{{ $selectedSize ?: 'Pilih ukuran' }}</span>
                        </label>
                        <div class="flex flex-wrap gap-2.5">
                            @foreach ($this->availableSizes as $size)
                                <button
                                    type="button"
                                    wire:click="selectSize('{{ $size }}')"
                                    class="px-4 py-2 text-xs font-semibold rounded-xl border transition {{ $selectedSize === $size ? 'bg-indigo-600 text-white border-indigo-600 shadow-sm ring-2 ring-indigo-200' : 'bg-white text-gray-800 border-gray-200 hover:border-gray-400' }}"
                                >
                                    {{ $size }}
                                </button>
                            @endforeach
                        </div>
                    </div>
                @endif

                {{-- Info Varian Terpilih & Metadata --}}
                @if ($this->selectedVariant)
                    <div class="bg-gray-50 rounded-xl p-3.5 mb-6 text-xs text-gray-600 border border-gray-200/60 grid grid-cols-2 sm:grid-cols-3 gap-2">
                        <div>
                            <span class="text-gray-400 block font-medium">SKU Varian</span>
                            <span class="font-mono font-semibold text-gray-900">{{ $this->selectedVariant->sku }}</span>
                        </div>
                        <div>
                            <span class="text-gray-400 block font-medium">Berat Pengiriman</span>
                            <span class="font-semibold text-gray-900">{{ $this->selectedVariant->berat_gram }} gram</span>
                        </div>
                        <div>
                            <span class="text-gray-400 block font-medium">Status Inventaris</span>
                            <span class="font-semibold {{ $this->selectedVariant->stok_tersedia > 0 ? 'text-emerald-700' : 'text-rose-600' }}">
                                {{ $this->selectedVariant->stok_tersedia > 0 ? 'Tersedia ' . $this->selectedVariant->stok_tersedia . ' unit' : 'Stok Habis' }}
                            </span>
                        </div>
                    </div>
                @else
                    <div class="bg-amber-50 rounded-xl p-3.5 mb-6 text-xs text-amber-800 border border-amber-200">
                        Kombinasi warna dan ukuran ini tidak tersedia. Silakan pilih kombinasi warna atau ukuran lain.
                    </div>
                @endif

                {{-- Tombol Tambah ke Keranjang (Visual Preparation) --}}
                <div class="space-y-3 mb-6">
                    <button
                        type="button"
                        disabled
                        class="w-full flex items-center justify-center gap-2 px-6 py-4 bg-gray-200 text-gray-500 font-bold rounded-2xl cursor-not-allowed transition"
                        title="Fitur Keranjang Belanja Sedang Disiapkan untuk Submilestone 3.1 Langkah 3"
                    >
                        <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
                        <span>Tambah ke Keranjang</span>
                    </button>
                    <p class="text-xs text-center text-gray-400 font-medium">
                        * Fitur keranjang belanja interaktif akan dihubungkan pada Langkah 3.
                    </p>
                </div>

                {{-- Banner Lokasi Toko Resmi --}}
                <div class="mt-auto pt-4 border-t border-gray-100 flex items-start gap-3 text-xs text-gray-500">
                    <svg class="w-5 h-5 text-indigo-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                    <div>
                        <span class="font-bold text-gray-800 block">Jaminan Produk Asli Bayeng Putra Helm</span>
                        <span>Dapat dibeli online atau diambil langsung di toko fisik kami: <strong>Jl. RE Martadinata No. 49, Cijoho – Kuningan</strong>.</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Deskripsi & Spesifikasi Produk --}}
        <div class="mt-12 pt-8 border-t border-gray-100">
            <h2 class="text-xl font-bold text-gray-900 mb-4">Deskripsi Produk</h2>
            <div class="prose prose-sm max-w-none text-gray-600 leading-relaxed mb-8">
                @if (!empty($product->deskripsi))
                    {!! nl2br(e($product->deskripsi)) !!}
                @else
                    <p class="text-gray-400 italic">Deskripsi belum ditambahkan untuk produk ini.</p>
                @endif
            </div>

            @if (!empty($product->spesifikasi) && is_array($product->spesifikasi))
                <h3 class="text-base font-bold text-gray-900 mb-3">Spesifikasi Teknis</h3>
                <div class="bg-gray-50 rounded-2xl p-5 border border-gray-100 max-w-2xl">
                    <dl class="divide-y divide-gray-200/60 text-xs">
                        @foreach ($product->spesifikasi as $key => $val)
                            <div class="py-2.5 grid grid-cols-3 gap-4">
                                <dt class="font-semibold text-gray-700">{{ is_string($key) ? ucfirst($key) : 'Spesifikasi' }}</dt>
                                <dd class="col-span-2 text-gray-600">{{ is_array($val) ? implode(', ', $val) : $val }}</dd>
                            </div>
                        @endforeach
                    </dl>
                </div>
            @endif
        </div>
    </div>
</div>