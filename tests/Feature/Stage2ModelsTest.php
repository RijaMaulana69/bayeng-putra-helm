<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Variant;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Stage2ModelsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 1. Seluruh model dapat diinisialisasi dan disimpan ke database.
     */
    public function test_all_stage_2_models_can_be_instantiated(): void
    {
        $brand = Brand::create(['nama' => 'KYT', 'slug' => 'kyt']);
        $category = Category::create(['nama' => 'Full Face', 'slug' => 'full-face']);
        $product = Product::create([
            'brand_id' => $brand->id,
            'category_id' => $category->id,
            'nama' => 'TT Course',
            'slug' => 'kyt-tt-course',
        ]);
        $variant = Variant::create([
            'product_id' => $product->id,
            'sku' => 'KYT-TTC-MB-L',
            'warna' => 'Matt Black',
            'ukuran' => 'L',
            'harga' => 1250000,
            'stok_fisik' => 10,
            'stok_dipesan' => 2,
        ]);
        $image = ProductImage::create([
            'product_id' => $product->id,
            'variant_id' => $variant->id,
            'file_path' => 'products/kyt-ttc-black.jpg',
            'alt_text' => 'KYT TT Course Matt Black Tampak Depan',
            'is_primary' => false,
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
            'catatan' => 'Penerimaan stok awal dari distributor',
        ]);

        $this->assertInstanceOf(Brand::class, $brand);
        $this->assertInstanceOf(Category::class, $category);
        $this->assertInstanceOf(Product::class, $product);
        $this->assertInstanceOf(Variant::class, $variant);
        $this->assertInstanceOf(ProductImage::class, $image);
        $this->assertInstanceOf(StockMovement::class, $movement);
    }

    /**
     * 2. Relasi antar-model berjalan sesuai struktur tabel.
     */
    public function test_relationships_between_models_work_correctly(): void
    {
        $user = User::factory()->create();
        $brand = Brand::create(['nama' => 'NHK', 'slug' => 'nhk']);
        $category = Category::create(['nama' => 'Half Face', 'slug' => 'half-face']);
        $product = Product::create([
            'brand_id' => $brand->id,
            'category_id' => $category->id,
            'nama' => 'R1',
            'slug' => 'nhk-r1',
        ]);
        $variant = Variant::create([
            'product_id' => $product->id,
            'sku' => 'NHK-R1-WH-M',
            'warna' => 'White',
            'ukuran' => 'M',
            'harga' => 450000,
            'stok_fisik' => 5,
            'stok_dipesan' => 1,
        ]);
        $image = ProductImage::create([
            'product_id' => $product->id,
            'variant_id' => $variant->id,
            'file_path' => 'products/nhk-r1.jpg',
            'alt_text' => 'NHK R1 White',
            'is_primary' => true,
        ]);
        $movement = StockMovement::createLedgerEntry([
            'variant_id' => $variant->id,
            'user_id' => $user->id,
            'tipe' => 'masuk',
            'perubahan_stok_fisik' => 5,
            'perubahan_stok_dipesan' => 0,
            'stok_fisik_sebelum' => 0,
            'stok_fisik_sesudah' => 5,
            'stok_dipesan_sebelum' => 0,
            'stok_dipesan_sesudah' => 0,
        ]);

        // Brand -> Products
        $this->assertTrue($brand->products->contains($product));

        // Category -> Products
        $this->assertTrue($category->products->contains($product));

        // Product -> Brand & Category
        $this->assertEquals($brand->id, $product->brand->id);
        $this->assertEquals($category->id, $product->category->id);

        // Product -> Variants & Images
        $this->assertTrue($product->variants->contains($variant));
        $this->assertTrue($product->images->contains($image));
        $this->assertTrue($product->productImages->contains($image));

        // Variant -> Product, Images, StockMovements
        $this->assertEquals($product->id, $variant->product->id);
        $this->assertTrue($variant->images->contains($image));
        $this->assertTrue($variant->stockMovements->contains($movement));

        // ProductImage -> Product & Variant
        $this->assertEquals($product->id, $image->product->id);
        $this->assertEquals($variant->id, $image->variant->id);

        // StockMovement -> Variant & User
        $this->assertEquals($variant->id, $movement->variant->id);
        $this->assertEquals($user->id, $movement->user->id);
    }

    /**
     * 3. Soft delete bekerja hanya pada model yang memiliki deleted_at.
     */
    public function test_soft_deletes_behavior(): void
    {
        $brand = Brand::create(['nama' => 'INK', 'slug' => 'ink']);
        $category = Category::create(['nama' => 'Retro', 'slug' => 'retro']);
        $product = Product::create([
            'brand_id' => $brand->id,
            'category_id' => $category->id,
            'nama' => 'Centro',
            'slug' => 'ink-centro',
        ]);
        $variant = Variant::create([
            'product_id' => $product->id,
            'sku' => 'INK-CTR-BK-L',
            'warna' => 'Black',
            'ukuran' => 'L',
            'harga' => 380000,
        ]);
        $image = ProductImage::create([
            'product_id' => $product->id,
            'file_path' => 'products/ink-centro.jpg',
            'alt_text' => 'INK Centro',
        ]);

        // Soft delete Brand, Category, Product, Variant
        $brand->delete();
        $category->delete();
        $product->delete();
        $variant->delete();

        $this->assertSoftDeleted('brands', ['id' => $brand->id]);
        $this->assertSoftDeleted('categories', ['id' => $category->id]);
        $this->assertSoftDeleted('products', ['id' => $product->id]);
        $this->assertSoftDeleted('variants', ['id' => $variant->id]);

        // ProductImage does NOT use SoftDeletes (Hard delete)
        $this->assertFalse(in_array('Illuminate\Database\Eloquent\SoftDeletes', class_uses_recursive(ProductImage::class)));
        $image->delete();
        $this->assertDatabaseMissing('product_images', ['id' => $image->id]);

        // StockMovement does NOT use SoftDeletes
        $this->assertFalse(in_array('Illuminate\Database\Eloquent\SoftDeletes', class_uses_recursive(StockMovement::class)));
    }

    /**
     * 4. Casting atribut berjalan sesuai tipe data.
     */
    public function test_attribute_casting_and_accessors(): void
    {
        $brand = Brand::create(['nama' => 'Cargloss', 'slug' => 'cargloss', 'is_active' => 1]);
        $category = Category::create(['nama' => 'Classic', 'slug' => 'classic', 'urutan' => '10', 'is_active' => 0]);
        $product = Product::create([
            'brand_id' => $brand->id,
            'category_id' => $category->id,
            'nama' => 'CF Retro',
            'slug' => 'cargloss-cf-retro',
            'spesifikasi' => ['material' => 'ABS High Impact', 'tali' => 'Microlock'],
            'is_active' => '1',
        ]);
        $variant = Variant::create([
            'product_id' => $product->id,
            'sku' => 'CGL-CFR-BK-XL',
            'warna' => 'Deep Black',
            'ukuran' => 'XL',
            'harga' => '280000',
            'stok_fisik' => '25',
            'stok_dipesan' => '5',
            'berat_gram' => '1350',
            'is_active' => 1,
        ]);

        // Brand casts
        $this->assertIsBool($brand->is_active);
        $this->assertTrue($brand->is_active);

        // Category casts
        $this->assertIsInt($category->urutan);
        $this->assertSame(10, $category->urutan);
        $this->assertIsBool($category->is_active);
        $this->assertFalse($category->is_active);

        // Product casts
        $this->assertIsArray($product->spesifikasi);
        $this->assertEquals('ABS High Impact', $product->spesifikasi['material']);
        $this->assertIsBool($product->is_active);

        // Variant casts & accessor stok_tersedia
        $this->assertIsInt($variant->harga);
        $this->assertSame(280000, $variant->harga);
        $this->assertIsInt($variant->stok_fisik);
        $this->assertSame(25, $variant->stok_fisik);
        $this->assertIsInt($variant->stok_dipesan);
        $this->assertSame(5, $variant->stok_dipesan);
        $this->assertIsInt($variant->berat_gram);
        $this->assertSame(1350, $variant->berat_gram);

        // Accessor stok_tersedia
        $this->assertSame(20, $variant->stok_tersedia);
    }

    /**
     * 5. Mass assignment tidak dapat memodifikasi generated column & StockMovement tertutup total.
     */
    public function test_mass_assignment_protection(): void
    {
        $brand = Brand::create(['nama' => 'JPX', 'slug' => 'jpx']);
        $category = Category::create(['nama' => 'Cross', 'slug' => 'cross']);
        $product = Product::create([
            'brand_id' => $brand->id,
            'category_id' => $category->id,
            'nama' => 'Fox1',
            'slug' => 'jpx-fox1',
        ]);

        // Attempt mass-assignment on generated column 'deleted_unique' on Variant
        $variant = Variant::create([
            'product_id' => $product->id,
            'sku' => 'JPX-FOX1-GR-L',
            'warna' => 'Flourescent Green',
            'ukuran' => 'L',
            'harga' => 650000,
            'deleted_unique' => 99999, // Harus diabaikan oleh $fillable
        ]);
        $this->assertNotEquals(99999, $variant->getAttributes()['deleted_unique'] ?? null);

        // Attempt mass-assignment on generated column 'primary_flag' on ProductImage
        $image = ProductImage::create([
            'product_id' => $product->id,
            'file_path' => 'products/jpx.jpg',
            'alt_text' => 'JPX Fox1',
            'primary_flag' => 88888, // Harus diabaikan oleh $fillable
        ]);
        $this->assertNotEquals(88888, $image->getAttributes()['primary_flag'] ?? null);

        // StockMovement mass assignment protection: $guarded = ['*']
        $this->expectException(MassAssignmentException::class);
        StockMovement::create([
            'variant_id' => $variant->id,
            'tipe' => 'masuk',
            'stok_fisik_sebelum' => 0,
            'stok_fisik_sesudah' => 10,
            'stok_dipesan_sebelum' => 0,
            'stok_dipesan_sesudah' => 0,
        ]);
    }

    /**
     * 6. StockMovement menolak operasi UPDATE dan DELETE melalui Eloquent.
     */
    public function test_stock_movement_rejects_update_and_delete_via_eloquent(): void
    {
        $brand = Brand::create(['nama' => 'RSV', 'slug' => 'rsv']);
        $category = Category::create(['nama' => 'Full Face', 'slug' => 'full-face-rsv']);
        $product = Product::create([
            'brand_id' => $brand->id,
            'category_id' => $category->id,
            'nama' => 'FF500',
            'slug' => 'rsv-ff500',
        ]);
        $variant = Variant::create([
            'product_id' => $product->id,
            'sku' => 'RSV-FF500-BK-L',
            'warna' => 'Black',
            'ukuran' => 'L',
            'harga' => 800000,
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
            'catatan' => 'Original Note',
        ]);

        // Test UPDATE rejection via update()
        try {
            $movement->update(['catatan' => 'Hacked Note']);
            $this->fail('StockMovement::update() should have thrown a LogicException');
        } catch (\LogicException $e) {
            $this->assertStringContainsString('immutable', $e->getMessage());
        }

        // Test UPDATE rejection via save()
        try {
            $movement->catatan = 'Hacked Note via save';
            $movement->save();
            $this->fail('StockMovement::save() on dirty model should have thrown a LogicException');
        } catch (\LogicException $e) {
            $this->assertStringContainsString('immutable', $e->getMessage());
        }

        // Test DELETE rejection via delete()
        try {
            $movement->delete();
            $this->fail('StockMovement::delete() should have thrown a LogicException');
        } catch (\LogicException $e) {
            $this->assertStringContainsString('immutable', $e->getMessage());
        }
    }

    /**
     * 7. StockMovement tidak mencoba mengisi updated_at.
     */
    public function test_stock_movement_does_not_manage_updated_at(): void
    {
        $this->assertNull(StockMovement::UPDATED_AT);

        $brand = Brand::create(['nama' => 'GM', 'slug' => 'gm']);
        $category = Category::create(['nama' => 'Half Face', 'slug' => 'half-face-gm']);
        $product = Product::create([
            'brand_id' => $brand->id,
            'category_id' => $category->id,
            'nama' => 'Evolution',
            'slug' => 'gm-evolution',
        ]);
        $variant = Variant::create([
            'product_id' => $product->id,
            'sku' => 'GM-EVO-RD-M',
            'warna' => 'Red',
            'ukuran' => 'M',
            'harga' => 320000,
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
        ]);

        $this->assertNotNull($movement->created_at);
        $this->assertArrayNotHasKey('updated_at', $movement->getAttributes());
    }

    /**
     * 8. Restore Variant menangani konflik kombinasi aktif dengan benar.
     */
    public function test_variant_restore_conflict_handling(): void
    {
        $brand = Brand::create(['nama' => 'MDS', 'slug' => 'mds']);
        $category = Category::create(['nama' => 'Superpro', 'slug' => 'superpro']);
        $product = Product::create([
            'brand_id' => $brand->id,
            'category_id' => $category->id,
            'nama' => 'Superpro Helmet',
            'slug' => 'mds-superpro',
        ]);

        // Buat varian 1
        $variant1 = Variant::create([
            'product_id' => $product->id,
            'sku' => 'MDS-SP-BK-L',
            'warna' => 'Solid Black',
            'ukuran' => 'L',
            'harga' => 500000,
        ]);

        // Soft delete varian 1
        $variant1->delete();
        $this->assertSoftDeleted('variants', ['id' => $variant1->id]);

        // Buat varian 2 dengan kombinasi sama persis (product_id, warna, ukuran) yang sekarang aktif
        $variant2 = Variant::create([
            'product_id' => $product->id,
            'sku' => 'MDS-SP-BK-L-NEW',
            'warna' => 'Solid Black',
            'ukuran' => 'L',
            'harga' => 520000,
        ]);
        $this->assertDatabaseHas('variants', ['id' => $variant2->id, 'deleted_at' => null]);

        // Coba pulihkan (restore) varian 1 sementara varian 2 masih aktif
        // Harus ditolak dengan DomainException
        try {
            $variant1->restore();
            $this->fail('Variant::restore() should have failed due to active combination conflict');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('kombinasi produk, warna', $e->getMessage());
            $this->assertStringContainsString('sudah digunakan oleh varian aktif lain', $e->getMessage());
        }

        // Varian 1 harus tetap dalam status soft-deleted
        $this->assertSoftDeleted('variants', ['id' => $variant1->id]);

        // Sekarang soft-delete varian 2 sehingga tidak ada lagi varian aktif dengan kombinasi tersebut
        $variant2->delete();

        // Pemulihan varian 1 sekarang harus berhasil
        $variant1->restore();
        $this->assertDatabaseHas('variants', ['id' => $variant1->id, 'deleted_at' => null]);
    }
}
