<?php

declare(strict_types=1);

namespace App\Livewire\Catalog;

use App\Models\Product;
use App\Models\Variant;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class ProductDetail extends Component
{
    public Product $product;

    public ?int $selectedVariantId = null;

    public string $selectedColor = '';

    public string $selectedSize = '';

    public ?string $activePhotoUrl = null;

    /**
     * Inisialisasi komponen detail produk berdasarkan slug.
     */
    public function mount(string $slug): void
    {
        $this->product = Product::query()
            ->where('slug', $slug)
            ->where('is_active', true)
            ->with([
                'brand:id,nama,slug,logo_path',
                'category:id,nama,slug',
                'images' => function ($q) {
                    $q->orderByDesc('is_primary')->orderBy('urutan');
                },
                'variants' => function ($q) {
                    $q->where('is_active', true);
                },
            ])
            ->firstOrFail();

        // Tetapkan foto aktif awal dari primary image atau foto pertama
        $primaryImg = $this->product->images->firstWhere('is_primary', true) ?? $this->product->images->first();
        $this->activePhotoUrl = $primaryImg ? $primaryImg->file_path : null;

        // Inisialisasi varian default
        $defaultVariant = $this->product->variants->firstWhere('stok_tersedia', '>', 0)
            ?? $this->product->variants->first();

        if ($defaultVariant) {
            $this->selectedVariantId = $defaultVariant->id;
            $this->selectedColor = $defaultVariant->warna;
            $this->selectedSize = $defaultVariant->ukuran;

            // Jika varian memiliki foto khusus, prioritaskan
            $variantImg = $this->product->images->firstWhere('variant_id', $defaultVariant->id);
            if ($variantImg) {
                $this->activePhotoUrl = $variantImg->file_path;
            }
        }
    }

    /**
     * Memilih warna varian helm.
     */
    public function selectColor(string $color): void
    {
        $this->selectedColor = $color;
        $this->resolveMatchingVariant();
    }

    /**
     * Memilih ukuran varian helm.
     */
    public function selectSize(string $size): void
    {
        $this->selectedSize = $size;
        $this->resolveMatchingVariant();
    }

    /**
     * Mengganti foto aktif galeri.
     */
    public function setActivePhoto(string $filePath): void
    {
        $this->activePhotoUrl = $filePath;
    }

    /**
     * Menemukan varian aktif yang cocok dengan kombinasi warna dan ukuran terpilih.
     */
    protected function resolveMatchingVariant(): void
    {
        // Cari varian yang cocok dengan warna dan ukuran terpilih
        $matching = $this->product->variants
            ->where('is_active', true)
            ->where('warna', $this->selectedColor)
            ->where('ukuran', $this->selectedSize)
            ->first();

        // Jika tidak ada ukuran yang cocok dengan warna tersebut, pilih ukuran pertama yang tersedia untuk warna itu
        if (! $matching) {
            $matching = $this->product->variants
                ->where('is_active', true)
                ->where('warna', $this->selectedColor)
                ->first();

            if ($matching) {
                $this->selectedSize = $matching->ukuran;
            }
        }

        if ($matching) {
            $this->selectedVariantId = $matching->id;

            // Perbarui foto aktif jika varian ini memiliki foto khusus
            $variantImg = $this->product->images->firstWhere('variant_id', $matching->id);
            if ($variantImg) {
                $this->activePhotoUrl = $variantImg->file_path;
            }
        } else {
            $this->selectedVariantId = null;
        }
    }

    /**
     * Mendapatkan objek varian yang terpilih secara valid dan aman.
     * Mencegah manipulasi ID dari browser.
     */
    #[Computed]
    public function selectedVariant(): ?Variant
    {
        if ($this->selectedVariantId === null) {
            return null;
        }

        return $this->product->variants
            ->where('id', $this->selectedVariantId)
            ->where('is_active', true)
            ->first();
    }

    /**
     * Daftar seluruh pilihan warna unik dari varian aktif.
     *
     * @return array<int, string>
     */
    #[Computed]
    public function availableColors(): array
    {
        return $this->product->variants
            ->where('is_active', true)
            ->pluck('warna')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Daftar seluruh pilihan ukuran unik dari varian aktif.
     *
     * @return array<int, string>
     */
    #[Computed]
    public function availableSizes(): array
    {
        return $this->product->variants
            ->where('is_active', true)
            ->pluck('ukuran')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Render view detail produk.
     */
    public function render(): View
    {
        return view('livewire.catalog.product-detail');
    }
}
