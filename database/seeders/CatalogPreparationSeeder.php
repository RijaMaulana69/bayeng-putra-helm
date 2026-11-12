<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class CatalogPreparationSeeder extends Seeder
{
    /**
     * Lima kandidat produk yang resmi disetujui pemilik proyek untuk Stage 2.3.
     * Persetujuan ini bersifat eksklusif dan tidak mencakup 45 foto lainnya.
     *
     * @var array<int, array{product_slug: string, nama: string, category_slug: string, brand_slug: string, brand_nama: string, file_path: string}>
     */
    protected array $approvedCandidates = [
        [
            'product_slug' => 'cargloss-cf-retro',
            'nama' => 'Cargloss CF Retro',
            'category_slug' => 'retro',
            'brand_slug' => 'cargloss',
            'brand_nama' => 'Cargloss',
            'file_path' => 'products/cargloss_cf_retro.jpg',
        ],
        [
            'product_slug' => 'cargloss-devil',
            'nama' => 'Cargloss Devil',
            'category_slug' => 'half-face',
            'brand_slug' => 'cargloss',
            'brand_nama' => 'Cargloss',
            'file_path' => 'products/cargloss_devil.jpg',
        ],
        [
            'product_slug' => 'cargloss-ghotic',
            'nama' => 'Cargloss Ghotic',
            'category_slug' => 'half-face',
            'brand_slug' => 'cargloss',
            'brand_nama' => 'Cargloss',
            'file_path' => 'products/cargloss_ghotic.jpg',
        ],
        [
            'product_slug' => 'gm-g1',
            'nama' => 'GM G1',
            'category_slug' => 'half-face',
            'brand_slug' => 'gm',
            'brand_nama' => 'GM',
            'file_path' => 'products/gm_g1.jpg',
        ],
        [
            'product_slug' => 'gm-g2',
            'nama' => 'GM G2',
            'category_slug' => 'half-face',
            'brand_slug' => 'gm',
            'brand_nama' => 'GM',
            'file_path' => 'products/gm_g2.jpg',
        ],
    ];

    /**
     * Jalankan seeder persiapan katalog.
     */
    public function run(): void
    {
        foreach ($this->approvedCandidates as $candidate) {
            $this->processCandidate($candidate);
        }
    }

    /**
     * Memproses satu entri kandidat produk dengan validasi preflight ketat dan transaksi atomik.
     *
     * @param  array{product_slug: string, nama: string, category_slug: string, brand_slug: string, brand_nama: string, file_path: string}  $candidate
     */
    public function processCandidate(array $candidate): bool
    {
        $productSlug = $candidate['product_slug'];
        $nama = $candidate['nama'];
        $categorySlug = $candidate['category_slug'];
        $brandSlug = $candidate['brand_slug'];
        $brandNama = $candidate['brand_nama'];
        $filePath = $candidate['file_path'];

        // 1. Preflight Check: Validasi keberadaan file fisik gambar di disk publik
        if (! Storage::disk('public')->exists($filePath)) {
            $msg = "SKIP [{$nama}]: File gambar fisik '{$filePath}' tidak ditemukan di disk publik.";
            $this->command?->warn($msg);
            Log::warning($msg);

            return false;
        }

        // 2. Preflight Check: Validasi master kategori resmi (harus tersedia, aktif, dan tidak soft-deleted)
        $categoryWithTrashed = Category::withTrashed()->where('slug', $categorySlug)->first();
        if (! $categoryWithTrashed) {
            $msg = "SKIP [{$nama}]: Kategori '{$categorySlug}' belum tersedia di database.";
            $this->command?->warn($msg);
            Log::warning($msg);

            return false;
        }

        if ($categoryWithTrashed->trashed()) {
            $msg = "SKIP [{$nama}]: Kategori '{$categorySlug}' ditemukan dalam kondisi soft-deleted. Tidak di-restore otomatis.";
            $this->command?->warn($msg);
            Log::warning($msg);

            return false;
        }

        if (! $categoryWithTrashed->is_active) {
            $msg = "SKIP [{$nama}]: Kategori '{$categorySlug}' berstatus TIDAK AKTIF.";
            $this->command?->warn($msg);
            Log::warning($msg);

            return false;
        }

        $category = $categoryWithTrashed;

        // 3. Preflight Check: Periksa apakah brand target dalam kondisi soft-deleted
        $brandWithTrashed = Brand::withTrashed()->where('slug', $brandSlug)->first();
        if ($brandWithTrashed && $brandWithTrashed->trashed()) {
            $msg = "SKIP [{$nama}]: Brand '{$brandSlug}' ditemukan dalam kondisi soft-deleted. Tidak di-restore otomatis.";
            $this->command?->warn($msg);
            Log::warning($msg);

            return false;
        }

        // 4. Preflight Check: Periksa apakah produk target dalam kondisi soft-deleted
        $existingProductWithTrashed = Product::withTrashed()->where('slug', $productSlug)->first();
        if ($existingProductWithTrashed && $existingProductWithTrashed->trashed()) {
            $msg = "SKIP [{$nama}]: Produk '{$productSlug}' ditemukan dalam kondisi soft-deleted. Tidak di-restore otomatis.";
            $this->command?->warn($msg);
            Log::warning($msg);

            return false;
        }

        $existingProduct = $existingProductWithTrashed;

        // 5. Preflight Check: Validasi konflik jika produk sudah ada di database
        if ($existingProduct) {
            $expectedBrand = Brand::where('slug', $brandSlug)->first();
            if (! $expectedBrand) {
                $msg = "SKIP [{$nama}]: Produk '{$productSlug}' sudah ada namun brand yang diharapkan '{$brandSlug}' belum tersedia di database. Brand baru tidak dibuat otomatis.";
                $this->command?->warn($msg);
                Log::warning($msg);

                return false;
            }

            if ($existingProduct->brand_id !== $expectedBrand->id) {
                $msg = "SKIP [{$nama}]: Konflik relasi brand pada produk '{$productSlug}' (brand_id eksisting: {$existingProduct->brand_id}, diharapkan: {$expectedBrand->id}).";
                $this->command?->warn($msg);
                Log::warning($msg);

                return false;
            }

            if ($existingProduct->category_id !== $category->id) {
                $msg = "SKIP [{$nama}]: Konflik relasi kategori pada produk '{$productSlug}' (category_id eksisting: {$existingProduct->category_id}, diharapkan: {$category->id}).";
                $this->command?->warn($msg);
                Log::warning($msg);

                return false;
            }
        }

        // 6. Eksekusi Penulisan dalam Transaksi Database Atomik
        DB::beginTransaction();
        try {
            // A. Ambil atau buat Brand baru jika produk baru
            $brand = Brand::where('slug', $brandSlug)->first();
            if (! $brand) {
                $brand = Brand::create([
                    'nama' => $brandNama,
                    'slug' => $brandSlug,
                    'is_active' => true,
                ]);
            }

            // B. Ambil produk eksisting atau buat produk baru
            if ($existingProduct) {
                $product = $existingProduct;
            } else {
                $product = Product::create([
                    'brand_id' => $brand->id,
                    'category_id' => $category->id,
                    'nama' => $nama,
                    'slug' => $productSlug,
                    'is_active' => false, // Produk baru wajib inactive
                ]);
            }

            // C. Kelola Gambar Produk (ProductImage)
            $existingImage = ProductImage::where('product_id', $product->id)
                ->where('file_path', $filePath)
                ->first();

            if (! $existingImage) {
                $hasPrimary = ProductImage::where('product_id', $product->id)
                    ->where('is_primary', true)
                    ->exists();

                $this->insertProductImage($product, $filePath, $hasPrimary);
            }

            DB::commit();

            $successMsg = "BERHASIL: Kandidat produk '{$nama}' (slug: '{$productSlug}') berhasil diproses.";
            $this->command?->info($successMsg);
            Log::info($successMsg);

            return true;
        } catch (Throwable $e) {
            DB::rollBack();
            $errorMsg = "ERROR: Gagal memproses kandidat produk '{$nama}': ".$e->getMessage();
            $this->command?->error($errorMsg);
            Log::error($errorMsg, ['exception' => $e]);

            return false;
        }
    }

    /**
     * Menyimpan record ProductImage ke database.
     */
    protected function insertProductImage(Product $product, string $filePath, bool $hasPrimary): ProductImage
    {
        $maxUrutan = ProductImage::where('product_id', $product->id)->max('urutan');
        $nextOrder = $maxUrutan !== null ? ((int) $maxUrutan + 1) : 0;

        return ProductImage::create([
            'product_id' => $product->id,
            'variant_id' => null, // Wajib null untuk gambar utama produk tanpa varian
            'file_path' => $filePath,
            'alt_text' => $product->nama,
            'urutan' => $nextOrder,
            'is_primary' => ! $hasPrimary,
        ]);
    }
}
