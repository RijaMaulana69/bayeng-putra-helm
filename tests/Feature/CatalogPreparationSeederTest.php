<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\StockMovement;
use App\Models\Variant;
use Database\Seeders\CatalogPreparationSeeder;
use Database\Seeders\MasterCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CatalogPreparationSeederTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Helper untuk menyiapkan file gambar palsu di disk publik.
     *
     * @param  array<int, string>|null  $customFiles
     */
    protected function setupFakeStorage(?array $customFiles = null): void
    {
        Storage::fake('public');

        $files = $customFiles ?? [
            'products/cargloss_cf_retro.jpg',
            'products/cargloss_devil.jpg',
            'products/cargloss_ghotic.jpg',
            'products/gm_g1.jpg',
            'products/gm_g2.jpg',
        ];

        foreach ($files as $file) {
            Storage::disk('public')->put($file, 'dummy content image');
        }
    }

    /**
     * 1. Seluruh 5 kandidat produk yang disetujui diproses dengan benar.
     */
    public function test_all_five_approved_candidates_are_processed_successfully(): void
    {
        $this->setupFakeStorage();
        $this->seed(MasterCategorySeeder::class);
        $this->seed(CatalogPreparationSeeder::class);

        // Verifikasi 5 produk dibuat
        $this->assertEquals(5, Product::count());

        $expectedProducts = [
            'cargloss-cf-retro' => ['nama' => 'Cargloss CF Retro', 'category' => 'retro', 'brand' => 'cargloss', 'file_path' => 'products/cargloss_cf_retro.jpg'],
            'cargloss-devil' => ['nama' => 'Cargloss Devil', 'category' => 'half-face', 'brand' => 'cargloss', 'file_path' => 'products/cargloss_devil.jpg'],
            'cargloss-ghotic' => ['nama' => 'Cargloss Ghotic', 'category' => 'half-face', 'brand' => 'cargloss', 'file_path' => 'products/cargloss_ghotic.jpg'],
            'gm-g1' => ['nama' => 'GM G1', 'category' => 'half-face', 'brand' => 'gm', 'file_path' => 'products/gm_g1.jpg'],
            'gm-g2' => ['nama' => 'GM G2', 'category' => 'half-face', 'brand' => 'gm', 'file_path' => 'products/gm_g2.jpg'],
        ];

        foreach ($expectedProducts as $slug => $data) {
            $product = Product::where('slug', $slug)->first();
            $this->assertNotNull($product, "Produk {$slug} harus ditemukan.");
            $this->assertEquals($data['nama'], $product->nama);
            $this->assertFalse($product->is_active, "Produk baru {$slug} wajib is_active = false.");
            $this->assertEquals($data['category'], $product->category->slug);
            $this->assertEquals($data['brand'], $product->brand->slug);

            // Verifikasi gambar utama
            $primaryImage = ProductImage::where('product_id', $product->id)->first();
            $this->assertNotNull($primaryImage, "Produk {$slug} harus memiliki gambar.");
            $this->assertTrue($primaryImage->is_primary);
            $this->assertNull($primaryImage->variant_id);
            $this->assertEquals($data['file_path'], $primaryImage->file_path, "File path gambar {$slug} harus tepat sesuai manifest.");
            $this->assertEquals(0, $primaryImage->urutan, "Gambar pertama pada produk baru {$slug} harus memiliki urutan 0.");
        }

        // Verifikasi 2 brand dibuat (Cargloss dan GM)
        $this->assertEquals(2, Brand::count());
        $this->assertDatabaseHas('brands', ['slug' => 'cargloss', 'is_active' => true]);
        $this->assertDatabaseHas('brands', ['slug' => 'gm', 'is_active' => true]);

        // Verifikasi 5 gambar dibuat
        $this->assertEquals(5, ProductImage::count());
    }

    /**
     * 2. Kandidat yang belum disetujui tidak diproses.
     */
    public function test_unapproved_candidates_are_not_processed(): void
    {
        $this->setupFakeStorage();
        $this->seed(MasterCategorySeeder::class);
        $this->seed(CatalogPreparationSeeder::class);

        // Hanya 5 kandidat resmi yang dibuat
        $this->assertEquals(5, Product::count());

        $this->assertDatabaseMissing('products', ['slug' => 'bogo-classic-black']);
        $this->assertDatabaseMissing('products', ['slug' => 'kyt-tt-corse']);
        $this->assertDatabaseMissing('products', ['slug' => 'ink-centro']);
        $this->assertDatabaseMissing('products', ['slug' => 'nhk-r1']);
    }

    /**
     * 3. Kategori yang belum tersedia atau tidak aktif menyebabkan kandidat dilewati.
     */
    public function test_candidate_skipped_if_category_missing_or_inactive(): void
    {
        $this->setupFakeStorage();

        // Kasus A: Kategori belum tersedia di database
        $this->seed(CatalogPreparationSeeder::class);
        $this->assertEquals(0, Product::count());
        $this->assertEquals(0, Brand::count());
        $this->assertEquals(0, ProductImage::count());

        // Kasus B: Kategori retro tidak aktif, half-face aktif
        Category::create(['nama' => 'Retro', 'slug' => 'retro', 'is_active' => false]);
        Category::create(['nama' => 'Half Face', 'slug' => 'half-face', 'is_active' => true]);

        $this->seed(CatalogPreparationSeeder::class);

        // Produk retro dilewati
        $this->assertEquals(0, Product::where('slug', 'cargloss-cf-retro')->count());
        // Hanya 4 produk half-face yang diproses
        $this->assertEquals(4, Product::count());
    }

    /**
     * 4. Kategori yang soft-deleted menyebabkan kandidat dilewati dan tidak di-restore.
     */
    public function test_candidate_skipped_if_category_is_soft_deleted(): void
    {
        $this->setupFakeStorage();

        $retro = Category::create(['nama' => 'Retro', 'slug' => 'retro', 'is_active' => true]);
        $retro->delete();

        Category::create(['nama' => 'Half Face', 'slug' => 'half-face', 'is_active' => true]);

        $this->seed(CatalogPreparationSeeder::class);

        $this->assertEquals(0, Product::where('slug', 'cargloss-cf-retro')->count());
        $this->assertTrue($retro->fresh()->trashed());
        $this->assertEquals(4, Product::count());
    }

    /**
     * 5. Brand yang belum tersedia di DB tidak dibuat secara otomatis jika produk terkait sudah ada.
     */
    public function test_brand_not_created_if_existing_product_missing_brand(): void
    {
        $this->setupFakeStorage();
        Category::create(['nama' => 'Retro', 'slug' => 'retro', 'is_active' => true]);

        // Buat brand dummy lain
        $otherBrand = Brand::create(['nama' => 'Other Brand', 'slug' => 'other-brand', 'is_active' => true]);

        // Buat produk dengan slug cargloss-cf-retro yang menunjuk ke other-brand
        Product::create([
            'brand_id' => $otherBrand->id,
            'category_id' => Category::where('slug', 'retro')->first()->id,
            'nama' => 'Cargloss CF Retro Existing',
            'slug' => 'cargloss-cf-retro',
            'is_active' => true,
        ]);

        // Jalankan seeder (brand 'cargloss' belum ada di DB)
        $this->seed(CatalogPreparationSeeder::class);

        // Brand cargloss tidak boleh dibuat secara otomatis
        $this->assertDatabaseMissing('brands', ['slug' => 'cargloss']);

        // Produk tidak ditimpa
        $product = Product::where('slug', 'cargloss-cf-retro')->first();
        $this->assertEquals($otherBrand->id, $product->brand_id);
    }

    /**
     * 6. Konflik brand atau kategori pada produk yang sudah ada tidak menimpa data.
     */
    public function test_conflict_brand_or_category_does_not_overwrite_data(): void
    {
        $this->setupFakeStorage();
        $category = Category::create(['nama' => 'Half Face', 'slug' => 'half-face', 'is_active' => true]);
        $wrongBrand = Brand::create(['nama' => 'Wrong Brand', 'slug' => 'wrong-brand', 'is_active' => true]);
        Brand::create(['nama' => 'GM', 'slug' => 'gm', 'is_active' => true]);

        // Produk gm-g1 terhubung ke wrong-brand
        $product = Product::create([
            'brand_id' => $wrongBrand->id,
            'category_id' => $category->id,
            'nama' => 'GM G1 Custom Name',
            'slug' => 'gm-g1',
            'is_active' => true,
        ]);

        $this->seed(CatalogPreparationSeeder::class);

        $productFresh = $product->fresh();
        $this->assertEquals($wrongBrand->id, $productFresh->brand_id, 'Brand ID tidak boleh ditimpa saat konflik.');
        $this->assertEquals('GM G1 Custom Name', $productFresh->nama, 'Nama tidak boleh ditimpa.');
    }

    /**
     * 7. Produk eksisting dengan category_id berbeda dari kategori yang diharapkan dilewati.
     */
    public function test_existing_product_with_category_conflict_is_skipped(): void
    {
        $this->setupFakeStorage();
        $retroCategory = Category::create(['nama' => 'Retro', 'slug' => 'retro', 'is_active' => true]);
        $halfFaceCategory = Category::create(['nama' => 'Half Face', 'slug' => 'half-face', 'is_active' => true]);
        $carglossBrand = Brand::create(['nama' => 'Cargloss', 'slug' => 'cargloss', 'is_active' => true]);

        // cargloss-cf-retro seharusnya berkategori 'retro', tetapi di database eksisting dihubungkan ke 'half-face'
        $product = Product::create([
            'brand_id' => $carglossBrand->id,
            'category_id' => $halfFaceCategory->id,
            'nama' => 'Cargloss CF Retro Custom Name',
            'slug' => 'cargloss-cf-retro',
            'is_active' => true,
        ]);

        $this->seed(CatalogPreparationSeeder::class);

        $productFresh = $product->fresh();
        // 1. category_id lama tidak berubah
        $this->assertEquals($halfFaceCategory->id, $productFresh->category_id, 'category_id tidak boleh diubah secara paksa.');
        // 2. Nama produk tidak berubah
        $this->assertEquals('Cargloss CF Retro Custom Name', $productFresh->nama, 'Nama produk tidak boleh ditimpa.');
        // 3. Tidak ada ProductImage baru yang dibuat untuk produk ini
        $this->assertEquals(0, ProductImage::where('product_id', $product->id)->count(), 'Tidak boleh membuat gambar untuk produk yang konflik.');
        // 4. Relasi brand tidak berubah
        $this->assertEquals($carglossBrand->id, $productFresh->brand_id);
    }

    /**
     * 7. File gambar tidak ditemukan menyebabkan kandidat dilewati tanpa data parsial.
     */
    public function test_missing_image_file_causes_candidate_to_be_skipped(): void
    {
        // Hanya sediakan 4 gambar (tanpa gm_g2.jpg)
        $this->setupFakeStorage([
            'products/cargloss_cf_retro.jpg',
            'products/cargloss_devil.jpg',
            'products/cargloss_ghotic.jpg',
            'products/gm_g1.jpg',
        ]);
        $this->seed(MasterCategorySeeder::class);
        $this->seed(CatalogPreparationSeeder::class);

        // 4 produk berhasil dibuat
        $this->assertEquals(4, Product::count());
        $this->assertDatabaseMissing('products', ['slug' => 'gm-g2']);
        $this->assertDatabaseMissing('product_images', ['file_path' => 'products/gm_g2.jpg']);
    }

    /**
     * 8. Produk baru harus berstatus is_active = false.
     */
    public function test_newly_created_products_are_inactive(): void
    {
        $this->setupFakeStorage();
        $this->seed(MasterCategorySeeder::class);
        $this->seed(CatalogPreparationSeeder::class);

        foreach (Product::all() as $product) {
            $this->assertFalse($product->is_active, "Produk {$product->slug} harus inactive.");
        }
    }

    /**
     * 9. Metadata gambar yang sudah ada dipertahankan (tidak ditimpa).
     */
    public function test_existing_image_metadata_is_preserved(): void
    {
        $this->setupFakeStorage();
        $this->seed(MasterCategorySeeder::class);

        $brand = Brand::create(['nama' => 'Cargloss', 'slug' => 'cargloss', 'is_active' => true]);
        $retro = Category::where('slug', 'retro')->first();

        $product = Product::create([
            'brand_id' => $brand->id,
            'category_id' => $retro->id,
            'nama' => 'Cargloss CF Retro',
            'slug' => 'cargloss-cf-retro',
            'is_active' => false,
        ]);

        ProductImage::create([
            'product_id' => $product->id,
            'variant_id' => null,
            'file_path' => 'products/cargloss_cf_retro.jpg',
            'alt_text' => 'Custom Alt Text Khusus',
            'urutan' => 99,
            'is_primary' => true,
        ]);

        $this->seed(CatalogPreparationSeeder::class);

        $image = ProductImage::where('product_id', $product->id)->first();
        $this->assertEquals('Custom Alt Text Khusus', $image->alt_text);
        $this->assertEquals(99, $image->urutan);
        $this->assertTrue($image->is_primary);
    }

    /**
     * 10. Gambar utama tidak dibuat ganda ketika seeder dijalankan berulang.
     */
    public function test_primary_image_is_not_duplicated(): void
    {
        $this->setupFakeStorage();
        $this->seed(MasterCategorySeeder::class);

        $this->seed(CatalogPreparationSeeder::class);
        $this->seed(CatalogPreparationSeeder::class);

        foreach (Product::all() as $product) {
            $primaryImagesCount = ProductImage::where('product_id', $product->id)
                ->where('is_primary', true)
                ->count();
            $this->assertEquals(1, $primaryImagesCount, "Produk {$product->slug} hanya boleh memiliki 1 gambar utama.");
        }
    }

    /**
     * 11. Urutan gambar baru adalah max(urutan) + 1 dan gambar utama lama dipertahankan.
     */
    public function test_image_order_increments_and_preserves_existing_primary_image(): void
    {
        $this->setupFakeStorage();
        $this->seed(MasterCategorySeeder::class);

        $brand = Brand::create(['nama' => 'Cargloss', 'slug' => 'cargloss', 'is_active' => true]);
        $retro = Category::where('slug', 'retro')->first();

        // 1. Buat produk eksisting
        $product = Product::create([
            'brand_id' => $brand->id,
            'category_id' => $retro->id,
            'nama' => 'Cargloss CF Retro',
            'slug' => 'cargloss-cf-retro',
            'is_active' => false,
        ]);

        // 2. Tambahkan gambar 1 (gambar utama lama, urutan 0)
        $existingPrimary = ProductImage::create([
            'product_id' => $product->id,
            'variant_id' => null,
            'file_path' => 'products/old_primary.jpg',
            'alt_text' => 'Old Primary Image',
            'urutan' => 0,
            'is_primary' => true,
        ]);

        // 3. Tambahkan gambar 2 (gambar sekunder lama, urutan 2, sehingga max urutan = 2)
        ProductImage::create([
            'product_id' => $product->id,
            'variant_id' => null,
            'file_path' => 'products/old_secondary.jpg',
            'alt_text' => 'Old Secondary Image',
            'urutan' => 2,
            'is_primary' => false,
        ]);

        $this->assertEquals(2, ProductImage::where('product_id', $product->id)->count());

        // 4. Jalankan seeder
        $this->seed(CatalogPreparationSeeder::class);

        // 5. Verifikasi bahwa gambar kandidat (cargloss_cf_retro.jpg) ditambahkan
        $candidateImage = ProductImage::where('product_id', $product->id)
            ->where('file_path', 'products/cargloss_cf_retro.jpg')
            ->first();

        $this->assertNotNull($candidateImage, 'Gambar kandidat harus berhasil ditambahkan.');

        // 6. Gambar utama lama tetap is_primary = true
        $this->assertTrue($existingPrimary->fresh()->is_primary, 'Gambar utama lama harus tetap dipertahankan.');
        $this->assertEquals(1, ProductImage::where('product_id', $product->id)->where('is_primary', true)->count());

        // 7. Gambar baru menjadi sekunder (is_primary = false) dan variant_id = null
        $this->assertFalse($candidateImage->is_primary, 'Gambar baru harus berstatus sekunder.');
        $this->assertNull($candidateImage->variant_id);

        // 8. Urutan gambar baru harus max(urutan) + 1 yaitu 2 + 1 = 3
        $this->assertEquals(3, $candidateImage->urutan, 'Urutan gambar baru harus bernilai max(urutan) + 1.');
    }

    /**
     * 11. Kegagalan pembuatan gambar menyebabkan transaksi database di-rollback secara atomik.
     */
    public function test_failure_during_image_creation_causes_transaction_rollback(): void
    {
        $this->setupFakeStorage();
        $this->seed(MasterCategorySeeder::class);

        // Mock seeder dengan simulasi kegagalan penyimpanan gambar
        $seeder = new class extends CatalogPreparationSeeder
        {
            protected function insertProductImage(Product $product, string $filePath, bool $hasPrimary): ProductImage
            {
                throw new \RuntimeException('Simulasi error saat menyimpan gambar produk');
            }
        };

        Storage::disk('public')->put('products/test_fail.jpg', 'fake content');

        $result = $seeder->processCandidate([
            'product_slug' => 'test-fail-product',
            'nama' => 'Test Fail Product',
            'category_slug' => 'retro',
            'brand_slug' => 'new-test-brand',
            'brand_nama' => 'New Test Brand',
            'file_path' => 'products/test_fail.jpg',
        ]);

        $this->assertFalse($result, 'Pemrosesan harus mengembalikan false saat terjadi exception.');

        // Transaksi harus di-rollback: Brand dan Product tidak boleh tersimpan parsial
        $this->assertDatabaseMissing('brands', ['slug' => 'new-test-brand']);
        $this->assertDatabaseMissing('products', ['slug' => 'test-fail-product']);
        $this->assertDatabaseMissing('product_images', ['file_path' => 'products/test_fail.jpg']);
    }

    /**
     * 12. Kegagalan satu kandidat tidak menghentikan pemrosesan kandidat berikutnya.
     */
    public function test_failure_of_one_candidate_does_not_stop_subsequent_candidates(): void
    {
        // Hilangkan file pertama cargloss_cf_retro.jpg
        $this->setupFakeStorage([
            'products/cargloss_devil.jpg',
            'products/cargloss_ghotic.jpg',
            'products/gm_g1.jpg',
            'products/gm_g2.jpg',
        ]);
        $this->seed(MasterCategorySeeder::class);
        $this->seed(CatalogPreparationSeeder::class);

        // Kandidat 1 dilewati, namun 4 kandidat lainnya berhasil diproses
        $this->assertDatabaseMissing('products', ['slug' => 'cargloss-cf-retro']);
        $this->assertEquals(4, Product::count());
    }

    /**
     * 13. Seeder bersifat idempoten (dijalankan berulang kali menghasilkan status yang sama).
     */
    public function test_seeder_is_idempotent(): void
    {
        $this->setupFakeStorage();
        $this->seed(MasterCategorySeeder::class);

        $this->seed(CatalogPreparationSeeder::class);
        $this->assertEquals(5, Product::count());
        $this->assertEquals(2, Brand::count());
        $this->assertEquals(5, ProductImage::count());

        // Jalankan kedua kali
        $this->seed(CatalogPreparationSeeder::class);
        $this->assertEquals(5, Product::count());
        $this->assertEquals(2, Brand::count());
        $this->assertEquals(5, ProductImage::count());
    }

    /**
     * 14. Seeder sama sekali tidak membuat Variant maupun StockMovement.
     */
    public function test_no_variants_or_stock_movements_are_created(): void
    {
        $this->setupFakeStorage();
        $this->seed(MasterCategorySeeder::class);
        $this->seed(CatalogPreparationSeeder::class);

        $this->assertEquals(0, Variant::count(), 'Variant harus tetap 0.');
        $this->assertEquals(0, StockMovement::count(), 'StockMovement harus tetap 0.');
    }
}
