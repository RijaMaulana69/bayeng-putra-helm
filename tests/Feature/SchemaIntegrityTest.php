<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\StockMovement;
use App\Models\Variant;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SchemaIntegrityTest extends TestCase
{
    use RefreshDatabase;

    protected Brand $brand;

    protected Category $category;

    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->brand = Brand::create([
            'nama' => 'KYT',
            'slug' => 'kyt',
        ]);

        $this->category = Category::create([
            'nama' => 'Full Face',
            'slug' => 'full-face',
        ]);

        $this->product = Product::create([
            'brand_id' => $this->brand->id,
            'category_id' => $this->category->id,
            'nama' => 'TT Course',
            'slug' => 'kyt-tt-course',
            'is_active' => true,
        ]);
    }

    /**
     * 1. Unique index kombinasi varian aktif (uk_variants_active_combination)
     * tetap bekerja dan mengizinkan kombinasi yang sama setelah varian lama di-soft delete.
     */
    public function test_active_variant_unique_index_allows_same_combination_after_soft_delete(): void
    {
        // 1. Buat varian aktif pertama
        $variant1 = Variant::create([
            'product_id' => $this->product->id,
            'sku' => 'KYT-TTC-MB-L',
            'warna' => 'Matt Black',
            'ukuran' => 'L',
            'harga' => 1200000,
            'stok_fisik' => 10,
            'stok_dipesan' => 0,
        ]);
        $this->assertDatabaseHas('variants', ['id' => $variant1->id, 'deleted_at' => null]);

        // 2. Pembuatan varian kedua dengan kombinasi aktif yang sama persis wajib ditolak oleh database index
        try {
            Variant::create([
                'product_id' => $this->product->id,
                'sku' => 'KYT-TTC-MB-L-DUP',
                'warna' => 'Matt Black',
                'ukuran' => 'L',
                'harga' => 1250000,
                'stok_fisik' => 5,
                'stok_dipesan' => 0,
            ]);
            $this->fail('Database harus menolak duplikasi kombinasi produk-warna-ukuran pada varian aktif.');
        } catch (QueryException $e) {
            $this->assertTrue(true, 'QueryException terlempar karena pelanggaran unique constraint.');
        }

        // 3. Lakukan soft delete pada varian pertama
        $variant1->delete();
        $this->assertSoftDeleted('variants', ['id' => $variant1->id]);

        // 4. Setelah varian pertama di-soft delete, pembuatan varian baru dengan kombinasi yang sama HARUS berhasil
        $variant2 = Variant::create([
            'product_id' => $this->product->id,
            'sku' => 'KYT-TTC-MB-L-NEW',
            'warna' => 'Matt Black',
            'ukuran' => 'L',
            'harga' => 1300000,
            'stok_fisik' => 8,
            'stok_dipesan' => 0,
        ]);
        $this->assertDatabaseHas('variants', ['id' => $variant2->id, 'deleted_at' => null]);

        // 5. Soft delete varian kedua
        $variant2->delete();
        $this->assertSoftDeleted('variants', ['id' => $variant2->id]);

        // 6. Buat varian ketiga dengan kombinasi yang sama -> harus tetap berhasil (beberapa soft-deleted diperbolehkan)
        $variant3 = Variant::create([
            'product_id' => $this->product->id,
            'sku' => 'KYT-TTC-MB-L-V3',
            'warna' => 'Matt Black',
            'ukuran' => 'L',
            'harga' => 1350000,
            'stok_fisik' => 12,
            'stok_dipesan' => 0,
        ]);
        $this->assertDatabaseHas('variants', ['id' => $variant3->id, 'deleted_at' => null]);
    }

    /**
     * 2. Composite unique key `variants(id, product_id)` (uk_variants_id_product) tersedia di database.
     */
    public function test_composite_unique_key_variants_id_product_exists(): void
    {
        // Pastikan tabel variants memiliki index komposit untuk [id, product_id]
        $indexes = Schema::getIndexes('variants');
        $found = false;

        foreach ($indexes as $index) {
            $columns = $index['columns'] ?? [];
            if (count($columns) === 2 && in_array('id', $columns) && in_array('product_id', $columns)) {
                $found = true;
                $this->assertTrue($index['unique'] ?? false, 'Index komposit (id, product_id) harus bersifat UNIQUE.');
                break;
            }
        }

        $this->assertTrue($found, 'Composite unique index uk_variants_id_product (id, product_id) wajib tersedia.');
    }

    /**
     * 3. Composite foreign key `product_images(variant_id, product_id)`
     * menjaga konsistensi relasi antara produk dan varian (mencegah mismatched variant).
     */
    public function test_composite_foreign_key_product_images_enforces_variant_product_consistency(): void
    {
        // Buat produk kedua
        $product2 = Product::create([
            'brand_id' => $this->brand->id,
            'category_id' => $this->category->id,
            'nama' => 'NFJ',
            'slug' => 'kyt-nfj',
            'is_active' => true,
        ]);

        // Buat varian milik Produk 1
        $variant1 = Variant::create([
            'product_id' => $this->product->id,
            'sku' => 'KYT-TTC-WH-M',
            'warna' => 'White',
            'ukuran' => 'M',
            'harga' => 1200000,
            'stok_fisik' => 5,
        ]);

        // Buat varian milik Produk 2
        $variant2 = Variant::create([
            'product_id' => $product2->id,
            'sku' => 'KYT-NFJ-BK-L',
            'warna' => 'Black',
            'ukuran' => 'L',
            'harga' => 950000,
            'stok_fisik' => 8,
        ]);

        // 1. Gambar untuk Produk 1 dengan varian milik Produk 1 -> BERHASIL
        $validImage = ProductImage::create([
            'product_id' => $this->product->id,
            'variant_id' => $variant1->id,
            'file_path' => 'products/kyt-ttc-white.jpg',
            'alt_text' => 'KYT TTC White',
            'is_primary' => false,
        ]);
        $this->assertDatabaseHas('product_images', ['id' => $validImage->id]);

        // 2. Gambar produk umum (variant_id = null) -> BERHASIL
        $generalImage = ProductImage::create([
            'product_id' => $this->product->id,
            'variant_id' => null,
            'file_path' => 'products/kyt-ttc-general.jpg',
            'alt_text' => 'KYT TTC General',
            'is_primary' => true,
        ]);
        $this->assertDatabaseHas('product_images', ['id' => $generalImage->id]);

        // 3. Gambar untuk Produk 1 DENGAN varian milik Produk 2 (MISMATCHED) -> WAJIB DITOLAK
        try {
            ProductImage::create([
                'product_id' => $this->product->id,
                'variant_id' => $variant2->id, // Varian milik product2!
                'file_path' => 'products/mismatched.jpg',
                'alt_text' => 'Mismatched Product Variant Image',
                'is_primary' => false,
            ]);
            $this->fail('Composite foreign key fk_product_images_variant_product harus menolak mismatched variant_id dan product_id.');
        } catch (QueryException $e) {
            $this->assertTrue(true, 'QueryException terlempar karena pelanggaran composite foreign key.');
        }
    }

    /**
     * 4. Produk tidak dapat memiliki lebih dari satu foto utama (uk_product_single_primary_image).
     */
    public function test_product_cannot_have_more_than_one_primary_image(): void
    {
        // 1. Buat foto utama pertama untuk Produk 1
        $primary1 = ProductImage::create([
            'product_id' => $this->product->id,
            'variant_id' => null,
            'file_path' => 'products/primary-1.jpg',
            'alt_text' => 'Primary 1',
            'is_primary' => true,
        ]);
        $this->assertDatabaseHas('product_images', ['id' => $primary1->id, 'is_primary' => 1]);

        // 2. Coba buat foto utama kedua untuk Produk 1 -> WAJIB DITOLAK oleh database index
        try {
            ProductImage::create([
                'product_id' => $this->product->id,
                'variant_id' => null,
                'file_path' => 'products/primary-2-duplicate.jpg',
                'alt_text' => 'Primary 2 Duplicate',
                'is_primary' => true,
            ]);
            $this->fail('Database harus menolak foto utama ganda pada satu produk yang sama.');
        } catch (QueryException $e) {
            $this->assertTrue(true, 'QueryException terlempar karena pelanggaran uk_product_single_primary_image.');
        }

        // 3. Foto non-utama (is_primary = false) pada Produk 1 boleh berjumlah banyak
        $nonPrimary1 = ProductImage::create([
            'product_id' => $this->product->id,
            'variant_id' => null,
            'file_path' => 'products/non-primary-1.jpg',
            'alt_text' => 'Non Primary 1',
            'is_primary' => false,
        ]);
        $nonPrimary2 = ProductImage::create([
            'product_id' => $this->product->id,
            'variant_id' => null,
            'file_path' => 'products/non-primary-2.jpg',
            'alt_text' => 'Non Primary 2',
            'is_primary' => false,
        ]);
        $this->assertDatabaseHas('product_images', ['id' => $nonPrimary1->id]);
        $this->assertDatabaseHas('product_images', ['id' => $nonPrimary2->id]);

        // 4. Produk lain boleh memiliki foto utama
        $product2 = Product::create([
            'brand_id' => $this->brand->id,
            'category_id' => $this->category->id,
            'nama' => 'Falcon',
            'slug' => 'kyt-falcon',
            'is_active' => true,
        ]);
        $primaryOtherProduct = ProductImage::create([
            'product_id' => $product2->id,
            'variant_id' => null,
            'file_path' => 'products/product2-primary.jpg',
            'alt_text' => 'Product 2 Primary',
            'is_primary' => true,
        ]);
        $this->assertDatabaseHas('product_images', ['id' => $primaryOtherProduct->id, 'is_primary' => 1]);
    }

    /**
     * 5. [MySQL-Specific] Foto utama tidak boleh menggunakan variant_id
     * (chk_primary_image_has_no_variant).
     */
    public function test_mysql_primary_image_cannot_have_variant_id(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            $this->markTestSkipped('Check constraint chk_primary_image_has_no_variant didefinisikan khusus pada driver MySQL.');
        }

        $variant = Variant::create([
            'product_id' => $this->product->id,
            'sku' => 'KYT-TTC-RD-XL',
            'warna' => 'Red',
            'ukuran' => 'XL',
            'harga' => 1200000,
            'stok_fisik' => 5,
        ]);

        $this->expectException(QueryException::class);
        $this->expectExceptionMessageMatches('/chk_primary_image_has_no_variant|Check constraint/i');

        ProductImage::create([
            'product_id' => $this->product->id,
            'variant_id' => $variant->id,
            'file_path' => 'products/invalid-primary.jpg',
            'alt_text' => 'Invalid Primary With Variant',
            'is_primary' => true, // Melanggar CHECK constraint MySQL!
        ]);
    }

    /**
     * 6. [MySQL-Specific] Trigger immutabilitas stock_movements menolak raw UPDATE dan DELETE.
     */
    public function test_mysql_stock_movements_immutability_triggers_prevent_raw_update_and_delete(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            $this->markTestSkipped('Trigger immutabilitas database stock_movements didefinisikan khusus pada driver MySQL.');
        }

        $variant = Variant::create([
            'product_id' => $this->product->id,
            'sku' => 'KYT-TTC-BL-M',
            'warna' => 'Blue',
            'ukuran' => 'M',
            'harga' => 1200000,
            'stok_fisik' => 10,
        ]);

        $movement = StockMovement::createLedgerEntry([
            'variant_id' => $variant->id,
            'tipe' => 'masuk',
            'perubahan_stok_fisik' => 10,
            'perubahan_stok_dipesan' => 0,
            'stok_fisik_sebelum' => 0,
            'stok_fisik_sesudah' => 10,
            'stok_dipesan_sebelum' => 0,
            'stok_dipesan_sesudah' => 0,
            'catatan' => 'Original Ledger Record',
        ]);

        // 1. Raw UPDATE via Query Builder wajib memicu trigger prevent_update
        try {
            DB::table('stock_movements')
                ->where('id', $movement->id)
                ->update(['catatan' => 'Attempted Raw SQL Update']);
            $this->fail('Trigger MySQL trg_stock_movements_prevent_update harus menolak raw UPDATE.');
        } catch (QueryException $e) {
            $this->assertStringContainsString('Tabel stock_movements bersifat immutable', $e->getMessage());
        }

        // 2. Raw DELETE via Query Builder wajib memicu trigger prevent_delete
        try {
            DB::table('stock_movements')
                ->where('id', $movement->id)
                ->delete();
            $this->fail('Trigger MySQL trg_stock_movements_prevent_delete harus menolak raw DELETE.');
        } catch (QueryException $e) {
            $this->assertStringContainsString('Tabel stock_movements bersifat immutable', $e->getMessage());
        }
    }

    /**
     * 7. [MySQL-Specific] Check constraint variants menolak harga tidak valid.
     */
    public function test_mysql_variants_reject_invalid_price(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            $this->markTestSkipped('Check constraint chk_variants_harga_safe didefinisikan khusus pada driver MySQL.');
        }

        $this->expectException(QueryException::class);
        $this->expectExceptionMessageMatches('/chk_variants_harga_safe|Check constraint/i');

        DB::table('variants')->insert([
            'product_id' => $this->product->id,
            'sku' => 'KYT-TTC-FREE',
            'warna' => 'Black',
            'ukuran' => 'M',
            'harga' => 0, // Melanggar harga > 0
            'stok_fisik' => 5,
            'stok_dipesan' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * 8. [MySQL-Specific] Check constraint stock_movements menolak inkonsistensi matematika dan referensi pesanan.
     */
    public function test_mysql_stock_movements_reject_inconsistent_math_and_order_reference(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            $this->markTestSkipped('Check constraint stock_movements math & order reference didefinisikan khusus pada driver MySQL.');
        }

        $variant = Variant::create([
            'product_id' => $this->product->id,
            'sku' => 'KYT-TTC-OR-L',
            'warna' => 'Orange',
            'ukuran' => 'L',
            'harga' => 1200000,
            'stok_fisik' => 10,
        ]);

        // 1. Math inconsistency: stok sebelum (10) + perubahan (5) != stok sesudah (20)
        try {
            DB::table('stock_movements')->insert([
                'variant_id' => $variant->id,
                'tipe' => 'penyesuaian',
                'perubahan_stok_fisik' => 5,
                'perubahan_stok_dipesan' => 0,
                'stok_fisik_sebelum' => 10,
                'stok_fisik_sesudah' => 20, // Salah! Seharusnya 15
                'stok_dipesan_sebelum' => 0,
                'stok_dipesan_sesudah' => 0,
                'created_at' => now(),
            ]);
            $this->fail('MySQL harus menolak math inconsistency pada stock_movements.');
        } catch (QueryException $e) {
            $this->assertStringContainsString('chk_stock_movements_math_consistency', $e->getMessage());
        }

        // 2. Order reference inconsistency: order_id terisi tapi order_item_id null
        try {
            DB::table('stock_movements')->insert([
                'variant_id' => $variant->id,
                'order_id' => 100,
                'order_item_id' => null, // Salah! Harus keduanya null atau keduanya tidak null
                'tipe' => 'keluar_pos',
                'perubahan_stok_fisik' => -1,
                'perubahan_stok_dipesan' => 0,
                'stok_fisik_sebelum' => 10,
                'stok_fisik_sesudah' => 9,
                'stok_dipesan_sebelum' => 0,
                'stok_dipesan_sesudah' => 0,
                'created_at' => now(),
            ]);
            $this->fail('MySQL harus menolak order reference inconsistency pada stock_movements.');
        } catch (QueryException $e) {
            $this->assertStringContainsString('chk_stock_movements_order_reference_consistency', $e->getMessage());
        }
    }

    /**
     * 9. [MySQL-Specific] Check constraint menjaga stok_fisik >= stok_dipesan.
     */
    public function test_mysql_variants_reject_stok_fisik_less_than_stok_dipesan(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            $this->markTestSkipped('Check constraint chk_variants_stok_valid didefinisikan khusus pada driver MySQL.');
        }

        $this->expectException(QueryException::class);
        $this->expectExceptionMessageMatches('/chk_variants_stok_valid|Check constraint/i');

        DB::table('variants')->insert([
            'product_id' => $this->product->id,
            'sku' => 'KYT-TTC-INV-STK',
            'warna' => 'Yellow',
            'ukuran' => 'M',
            'harga' => 1200000,
            'stok_fisik' => 3,
            'stok_dipesan' => 5, // Melanggar stok_fisik >= stok_dipesan
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
