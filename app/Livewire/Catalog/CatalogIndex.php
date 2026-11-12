<?php

declare(strict_types=1);

namespace App\Livewire\Catalog;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class CatalogIndex extends Component
{
    use WithPagination;

    #[Url(as: 'cari', history: true)]
    public string $search = '';

    #[Url(as: 'kategori', history: true)]
    public string $selectedCategory = '';

    #[Url(as: 'merek', history: true)]
    public string $selectedBrand = '';

    #[Url(as: 'urutkan', history: true)]
    public string $sortBy = 'terbaru';

    /**
     * Reset pagination when search query changes.
     */
    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    /**
     * Reset pagination when category filter changes.
     */
    public function updatingSelectedCategory(): void
    {
        $this->resetPage();
    }

    /**
     * Reset pagination when brand filter changes.
     */
    public function updatingSelectedBrand(): void
    {
        $this->resetPage();
    }

    /**
     * Reset pagination when sorting changes.
     */
    public function updatingSortBy(): void
    {
        $this->resetPage();
    }

    /**
     * Reset all active filters.
     */
    public function resetFilters(): void
    {
        $this->reset(['search', 'selectedCategory', 'selectedBrand', 'sortBy']);
        $this->resetPage();
    }

    /**
     * Render catalog index page.
     */
    public function render(): View
    {
        $query = Product::query()
            ->where('is_active', true)
            ->with([
                'brand:id,nama,slug',
                'category:id,nama,slug',
                'images' => function ($q) {
                    $q->orderByDesc('is_primary')->orderBy('urutan');
                },
                'variants' => function ($q) {
                    $q->where('is_active', true);
                },
            ]);

        // Filter: Pencarian nama produk atau merek
        if (trim($this->search) !== '') {
            $searchTerm = trim($this->search);
            $query->where(function ($q) use ($searchTerm) {
                $q->where('nama', 'like', "%{$searchTerm}%")
                    ->orWhere('deskripsi', 'like', "%{$searchTerm}%")
                    ->orWhereHas('brand', function ($bq) use ($searchTerm) {
                        $bq->where('nama', 'like', "%{$searchTerm}%");
                    });
            });
        }

        // Filter: Kategori
        if (! empty($this->selectedCategory)) {
            $query->whereHas('category', function ($q) {
                $q->where('slug', $this->selectedCategory)
                    ->where('is_active', true);
            });
        }

        // Filter: Merek
        if (! empty($this->selectedBrand)) {
            $query->whereHas('brand', function ($q) {
                $q->where('slug', $this->selectedBrand)
                    ->where('is_active', true);
            });
        }

        // Pengurutan
        match ($this->sortBy) {
            'nama_asc' => $query->orderBy('nama', 'asc'),
            'nama_desc' => $query->orderBy('nama', 'desc'),
            default => $query->latest('id'),
        };

        $products = $query->paginate(12);

        $categories = Category::query()
            ->where('is_active', true)
            ->orderBy('urutan')
            ->orderBy('nama')
            ->get(['id', 'nama', 'slug']);

        $brands = Brand::query()
            ->where('is_active', true)
            ->orderBy('nama')
            ->get(['id', 'nama', 'slug']);

        return view('livewire.catalog.catalog-index', [
            'products' => $products,
            'categories' => $categories,
            'brands' => $brands,
        ]);
    }
}
