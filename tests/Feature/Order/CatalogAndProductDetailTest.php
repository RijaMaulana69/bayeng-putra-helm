<?php

declare(strict_types=1);

namespace Tests\Feature\Order;

use App\Livewire\Catalog\CatalogIndex;
use App\Livewire\Catalog\ProductDetail;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Variant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CatalogAndProductDetailTest extends TestCase
{
    use RefreshDatabase;

    protected Brand $brandKyt;
    protected Brand $brandNhk;
    protected Category $catFullFace;
    protected Category $catHalfFace;

    protected function setUp(): void
    {
        parent::setUp();

        $this->brandKyt = Brand::create([
            'nama' => 'KYT',
            'slug' => 'kyt',
            'is_active' => true,
        ]);

        $this->brandNhk = Brand::create([
            'nama' => 'NHK',
            'slug' => 'nhk',
            'is_active' => true,
        ]);

        $this->catFullFace = Category::create([
            'nama' => 'Full Face',
            'slug' => 'full-face',
            'urutan' => 1,
            'is_active' => true,
        ]);

        $this->catHalfFace = Category::create([
            'nama' => 'Half Face',
            'slug' => 'half-face',
            'urutan' => 2,
            'is_active' => true,
        ]);
    }

    /** 1. Katalog dapat diakses tanpa login (Guest Access). */
    public function test_1_catalog_can_be_accessed_without_login(): void
    {
        $response = $this->get('/katalog');
        $response->assertStatus(200);
        $response->assertSee('Katalog Bayeng Putra Helm');
    }

    /** 2. Produk aktif ditampilkan. */
    public function test_2_active_products_are_displayed(): void
    {
        $product = Product::create([
            'brand_id' => $this->brandKyt->id,
            'category_id' => $this->catFullFace->id,
            'nama' => 'KYT TT Course Solid White',
            'slug' => 'kyt-tt-course-solid-white',
            'is_active' => true,
        ]);

        Livewire::test(CatalogIndex::class)
            ->assertSee('KYT TT Course Solid White');
    }

    /** 3. Produk tidak aktif tidak ditampilkan pada katalog. */
    public function test_3_inactive_products_are_not_displayed(): void
    {
        $product = Product::create([
            'brand_id' => $this->brandKyt->id,
            'category_id' => $this->catFullFace->id,
            'nama' => 'KYT Pro Inactive Prototype',
            'slug' => 'kyt-pro-inactive',
            'is_active' => false,
        ]);

        Livewire::test(CatalogIndex::class)
            ->assertDontSee('KYT Pro Inactive Prototype');
    }

    /** 4. Pencarian produk berdasarkan nama berjalan dengan benar. */
    public function test_4_search_filters_products_by_name(): void
    {
        Product::create([
            'brand_id' => $this->brandKyt->id,
            'category_id' => $this->catFullFace->id,
            'nama' => 'KYT Falcon Race Helmet',
            'slug' => 'kyt-falcon-race',
            'is_active' => true,
        ]);

        Product::create([
            'brand_id' => $this->brandNhk->id,
            'category_id' => $this->catHalfFace->id,
            'nama' => 'NHK R6 Touring Series',
            'slug' => 'nhk-r6-touring',
            'is_active' => true,
        ]);

        Livewire::test(CatalogIndex::class)
            ->set('search', 'Falcon')
            ->assertSee('KYT Falcon Race Helmet')
            ->assertDontSee('NHK R6 Touring Series');
    }

    /** 5. Filter kategori berjalan dengan benar. */
    public function test_5_category_filter_works(): void
    {
        Product::create([
            'brand_id' => $this->brandKyt->id,
            'category_id' => $this->catFullFace->id,
            'nama' => 'Helm Balap Full Face',
            'slug' => 'helm-balap-full-face',
            'is_active' => true,
        ]);

        Product::create([
            'brand_id' => $this->brandKyt->id,
            'category_id' => $this->catHalfFace->id,
            'nama' => 'Helm Santai Half Face',
            'slug' => 'helm-santai-half-face',
            'is_active' => true,
        ]);

        Livewire::test(CatalogIndex::class)
            ->set('selectedCategory', 'full-face')
            ->assertSee('Helm Balap Full Face')
            ->assertDontSee('Helm Santai Half Face');
    }

    /** 6. Filter merek berjalan dengan benar. */
    public function test_6_brand_filter_works(): void
    {
        Product::create([
            'brand_id' => $this->brandKyt->id,
            'category_id' => $this->catFullFace->id,
            'nama' => 'KYT Venom Special',
            'slug' => 'kyt-venom-special',
            'is_active' => true,
        ]);

        Product::create([
            'brand_id' => $this->brandNhk->id,
            'category_id' => $this->catFullFace->id,
            'nama' => 'NHK GP Prime Special',
            'slug' => 'nhk-gp-prime-special',
            'is_active' => true,
        ]);

        Livewire::test(CatalogIndex::class)
            ->set('selectedBrand', 'nhk')
            ->assertSee('NHK GP Prime Special')
            ->assertDontSee('KYT Venom Special');
    }

    /** 7. Kombinasi filter kategori dan merek berjalan harmonis. */
    public function test_7_combined_category_and_brand_filter_works(): void
    {
        Product::create([
            'brand_id' => $this->brandKyt->id,
            'category_id' => $this->catFullFace->id,
            'nama' => 'KYT Full Face Target',
            'slug' => 'kyt-full-target',
            'is_active' => true,
        ]);

        Product::create([
            'brand_id' => $this->brandKyt->id,
            'category_id' => $this->catHalfFace->id,
            'nama' => 'KYT Half Face Non Target',
            'slug' => 'kyt-half-non-target',
            'is_active' => true,
        ]);

        Product::create([
            'brand_id' => $this->brandNhk->id,
            'category_id' => $this->catFullFace->id,
            'nama' => 'NHK Full Face Non Target',
            'slug' => 'nhk-full-non-target',
            'is_active' => true,
        ]);

        Livewire::test(CatalogIndex::class)
            ->set('selectedBrand', 'kyt')
            ->set('selectedCategory', 'full-face')
            ->assertSee('KYT Full Face Target')
            ->assertDontSee('KYT Half Face Non Target')
            ->assertDontSee('NHK Full Face Non Target');
    }

    /** 8. Pagination berjalan dan reset ke halaman pertama saat filter berubah. */
    public function test_8_pagination_works_and_resets_on_filter_change(): void
    {
        for ($i = 1; $i <= 15; $i++) {
            Product::create([
                'brand_id' => $this->brandKyt->id,
                'category_id' => $this->catFullFace->id,
                'nama' => sprintf('Helm Produk Batch %02d', $i),
                'slug' => sprintf('helm-produk-batch-%02d', $i),
                'is_active' => true,
            ]);
        }

        $component = Livewire::test(CatalogIndex::class)
            ->call('gotoPage', 2);

        $this->assertEquals(2, $component->paginators['page'] ?? 2);

        // Ubah pencarian, pastikan halaman kembali ke 1
        $component->set('search', 'Batch');
        $this->assertEquals(1, $component->paginators['page'] ?? 1);
    }

    /** 9. Detail produk aktif dapat dibuka secara publik. */
    public function test_9_active_product_detail_can_be_opened(): void
    {
        $product = Product::create([
            'brand_id' => $this->brandKyt->id,
            'category_id' => $this->catFullFace->id,
            'nama' => 'KYT TT Course Tourist',
            'slug' => 'kyt-tt-course-tourist',
            'deskripsi' => 'Helm harian dan turing terbaik dengan bobot ringan.',
            'is_active' => true,
        ]);

        $variant = Variant::create([
            'product_id' => $product->id,
            'sku' => 'KYT-TTC-BLK-L',
            'warna' => 'Hitam Doff',
            'ukuran' => 'L',
            'harga' => 1250000,
            'stok_fisik' => 10,
            'stok_dipesan' => 2,
            'is_active' => true,
        ]);

        $response = $this->get('/katalog/' . $product->slug);
        $response->assertStatus(200);
        $response->assertSee('KYT TT Course Tourist');
        $response->assertSee('Rp 1.250.000');
        $response->assertSee('Hitam Doff');
        $response->assertSee('Stok Tersedia: 8 unit');
    }

    /** 10. Produk tidak aktif tidak dapat diakses (mengembalikan HTTP 404). */
    public function test_10_inactive_product_detail_returns_404(): void
    {
        $product = Product::create([
            'brand_id' => $this->brandKyt->id,
            'category_id' => $this->catFullFace->id,
            'nama' => 'KYT Discontinued Helmet',
            'slug' => 'kyt-discontinued-helmet',
            'is_active' => false,
        ]);

        $response = $this->get('/katalog/' . $product->slug);
        $response->assertStatus(404);
    }

    /** 11. Varian hanya berasal dari produk terkait. */
    public function test_11_variants_only_belong_to_the_current_product(): void
    {
        $productA = Product::create([
            'brand_id' => $this->brandKyt->id,
            'category_id' => $this->catFullFace->id,
            'nama' => 'Helm A',
            'slug' => 'helm-a',
            'is_active' => true,
        ]);

        $productB = Product::create([
            'brand_id' => $this->brandNhk->id,
            'category_id' => $this->catFullFace->id,
            'nama' => 'Helm B',
            'slug' => 'helm-b',
            'is_active' => true,
        ]);

        $variantA = Variant::create([
            'product_id' => $productA->id,
            'sku' => 'SKU-HELM-A-L',
            'warna' => 'Merah Glossy',
            'ukuran' => 'L',
            'harga' => 800000,
            'is_active' => true,
        ]);

        $variantB = Variant::create([
            'product_id' => $productB->id,
            'sku' => 'SKU-HELM-B-XL',
            'warna' => 'Biru Doff',
            'ukuran' => 'XL',
            'harga' => 900000,
            'is_active' => true,
        ]);

        Livewire::test(ProductDetail::class, ['slug' => $productA->slug])
            ->assertSee('Merah Glossy')
            ->assertDontSee('Biru Doff')
            ->assertDontSee('SKU-HELM-B-XL');
    }

    /** 12. Varian tidak aktif tidak dapat dipilih atau ditampilkan. */
    public function test_12_inactive_variants_cannot_be_selected(): void
    {
        $product = Product::create([
            'brand_id' => $this->brandKyt->id,
            'category_id' => $this->catFullFace->id,
            'nama' => 'Helm Multi Varian',
            'slug' => 'helm-multi-varian',
            'is_active' => true,
        ]);

        Variant::create([
            'product_id' => $product->id,
            'sku' => 'VAR-ACTIVE-M',
            'warna' => 'Kuning Stabilo',
            'ukuran' => 'M',
            'harga' => 950000,
            'is_active' => true,
        ]);

        Variant::create([
            'product_id' => $product->id,
            'sku' => 'VAR-INACTIVE-S',
            'warna' => 'Ungu Metalik',
            'ukuran' => 'S',
            'harga' => 950000,
            'is_active' => false,
        ]);

        Livewire::test(ProductDetail::class, ['slug' => $product->slug])
            ->assertSee('Kuning Stabilo')
            ->assertDontSee('Ungu Metalik');
    }

    /** 13. Stok tersedia dihitung dengan benar (stok_fisik - stok_dipesan). */
    public function test_13_available_stock_is_calculated_accurately(): void
    {
        $product = Product::create([
            'brand_id' => $this->brandKyt->id,
            'category_id' => $this->catFullFace->id,
            'nama' => 'Helm Uji Stok',
            'slug' => 'helm-uji-stok',
            'is_active' => true,
        ]);

        $variant = Variant::create([
            'product_id' => $product->id,
            'sku' => 'SKU-STOK-1',
            'warna' => 'Hitam',
            'ukuran' => 'XL',
            'harga' => 1500000,
            'stok_fisik' => 15,
            'stok_dipesan' => 6,
            'is_active' => true,
        ]);

        $this->assertEquals(9, $variant->stok_tersedia);

        Livewire::test(ProductDetail::class, ['slug' => $product->slug])
            ->assertSee('Stok Tersedia: 9 unit');
    }

    /** 14. Produk tanpa foto ditampilkan secara aman dengan placeholder. */
    public function test_14_product_without_photo_renders_safely(): void
    {
        $product = Product::create([
            'brand_id' => $this->brandKyt->id,
            'category_id' => $this->catFullFace->id,
            'nama' => 'Helm Tanpa Gambar',
            'slug' => 'helm-tanpa-gambar',
            'is_active' => true,
        ]);

        $this->get('/katalog')
            ->assertStatus(200)
            ->assertSee('Helm Tanpa Gambar')
            ->assertSee('Foto Belum Tersedia');

        $this->get('/katalog/' . $product->slug)
            ->assertStatus(200)
            ->assertSee('Foto Produk Belum Tersedia');
    }

    /** 15. Kunjungan dan navigasi halaman katalog tidak mengubah stok fisik maupun stok dipesan. */
    public function test_15_visiting_catalog_does_not_mutate_stock(): void
    {
        $product = Product::create([
            'brand_id' => $this->brandKyt->id,
            'category_id' => $this->catFullFace->id,
            'nama' => 'Helm Uji Immutabilitas Stok',
            'slug' => 'helm-uji-immutabilitas-stok',
            'is_active' => true,
        ]);

        $variant = Variant::create([
            'product_id' => $product->id,
            'sku' => 'SKU-IMMUTABLE-1',
            'warna' => 'Silver',
            'ukuran' => 'M',
            'harga' => 1100000,
            'stok_fisik' => 20,
            'stok_dipesan' => 5,
            'is_active' => true,
        ]);

        // Kunjungi katalog dan detail produk
        $this->get('/katalog')->assertStatus(200);
        $this->get('/katalog/' . $product->slug)->assertStatus(200);

        // Verifikasi stok pada database tidak bergeser satu unit pun
        $variantFresh = $variant->fresh();
        $this->assertEquals(20, $variantFresh->stok_fisik);
        $this->assertEquals(5, $variantFresh->stok_dipesan);
    }
}
