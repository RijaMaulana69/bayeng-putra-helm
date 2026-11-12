<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Variant;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\MasterCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MasterCategorySeederTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 1. Seeder membuat kategori Retro dan Half Face dengan atribut yang sesuai.
     */
    public function test_seeder_creates_retro_and_half_face_categories(): void
    {
        $this->seed(MasterCategorySeeder::class);

        $this->assertDatabaseHas('categories', [
            'nama' => 'Retro',
            'slug' => 'retro',
            'is_active' => true,
            'deleted_at' => null,
        ]);

        $this->assertDatabaseHas('categories', [
            'nama' => 'Half Face',
            'slug' => 'half-face',
            'is_active' => true,
            'deleted_at' => null,
        ]);
    }

    /**
     * 2. Jumlah kategori yang dibuat tepat dua pada database kosong.
     */
    public function test_exact_category_count_is_two_on_empty_database(): void
    {
        $this->assertEquals(0, Category::withTrashed()->count());

        $this->seed(MasterCategorySeeder::class);

        $this->assertEquals(2, Category::count());
        $this->assertEquals(2, Category::withTrashed()->count());
    }

    /**
     * 3. Menjalankan seeder dua kali tidak membuat duplikasi (idempoten).
     */
    public function test_running_seeder_twice_is_idempotent_and_does_not_duplicate(): void
    {
        $this->seed(MasterCategorySeeder::class);
        $this->assertEquals(2, Category::count());

        // Jalankan kedua kali
        $this->seed(MasterCategorySeeder::class);

        $this->assertEquals(2, Category::count());
        $this->assertEquals(2, Category::withTrashed()->count());
        $this->assertEquals(1, Category::where('slug', 'retro')->count());
        $this->assertEquals(1, Category::where('slug', 'half-face')->count());
    }

    /**
     * 4. Data kategori yang sudah ada tidak ditimpa (nama, deskripsi, urutan, status).
     */
    public function test_existing_category_data_is_not_overwritten(): void
    {
        // Buat kategori Retro dengan nilai kustom terlebih dahulu
        Category::create([
            'nama' => 'Custom Retro Name',
            'slug' => 'retro',
            'deskripsi' => 'Deskripsi kustom yang sudah ada sebelumnya',
            'urutan' => 99,
            'is_active' => true,
        ]);

        $this->seed(MasterCategorySeeder::class);

        $retro = Category::where('slug', 'retro')->firstOrFail();
        $this->assertEquals('Custom Retro Name', $retro->nama);
        $this->assertEquals('Deskripsi kustom yang sudah ada sebelumnya', $retro->deskripsi);
        $this->assertEquals(99, $retro->urutan);
        $this->assertTrue($retro->is_active);

        // Half face tetap dibuat secara normal
        $halfFace = Category::where('slug', 'half-face')->firstOrFail();
        $this->assertEquals('Half Face', $halfFace->nama);
        $this->assertTrue($halfFace->is_active);

        $this->assertEquals(2, Category::count());
    }

    /**
     * 5. Kategori soft-deleted tidak di-restore otomatis dan tidak menyebabkan duplikasi.
     */
    public function test_soft_deleted_category_is_not_restored_automatically(): void
    {
        $retro = Category::create([
            'nama' => 'Retro Terhapus',
            'slug' => 'retro',
            'deskripsi' => 'Kategori yang sebelumnya di-soft delete',
            'urutan' => 1,
            'is_active' => true,
        ]);
        $retro->delete();

        $this->assertTrue($retro->trashed());
        $this->assertNotNull($retro->deleted_at);

        $this->seed(MasterCategorySeeder::class);

        // Kategori retro harus tetap soft-deleted
        $retroFresh = Category::withTrashed()->where('slug', 'retro')->firstOrFail();
        $this->assertTrue($retroFresh->trashed());
        $this->assertNotNull($retroFresh->deleted_at);
        $this->assertEquals('Retro Terhapus', $retroFresh->nama);

        // Kategori aktif hanya ada 1 (Half Face)
        $this->assertEquals(1, Category::count());
        $this->assertEquals(2, Category::withTrashed()->count());
    }

    /**
     * 6. Kategori yang tidak aktif tidak otomatis diaktifkan.
     */
    public function test_inactive_category_is_not_activated_automatically(): void
    {
        $halfFace = Category::create([
            'nama' => 'Half Face Nonaktif',
            'slug' => 'half-face',
            'deskripsi' => 'Kategori dinonaktifkan oleh admin',
            'urutan' => 2,
            'is_active' => false,
        ]);

        $this->seed(MasterCategorySeeder::class);

        $halfFaceFresh = Category::where('slug', 'half-face')->firstOrFail();
        $this->assertFalse($halfFaceFresh->is_active);
        $this->assertEquals('Half Face Nonaktif', $halfFaceFresh->nama);

        // Retro dibuat normal dan aktif
        $retro = Category::where('slug', 'retro')->firstOrFail();
        $this->assertTrue($retro->is_active);
    }

    /**
     * 7. Seeder tidak membuat kategori yang belum disetujui (Full Face, Cross, Modular).
     */
    public function test_seeder_does_not_create_unapproved_categories(): void
    {
        $this->seed(MasterCategorySeeder::class);

        $this->assertDatabaseMissing('categories', ['slug' => 'full-face']);
        $this->assertDatabaseMissing('categories', ['slug' => 'cross']);
        $this->assertDatabaseMissing('categories', ['slug' => 'modular']);
        $this->assertDatabaseMissing('categories', ['slug' => 'bogo']);
        $this->assertDatabaseMissing('categories', ['slug' => 'trail']);

        $unapprovedCount = Category::withTrashed()->whereIn('slug', [
            'full-face', 'cross', 'modular', 'bogo', 'trail',
        ])->count();

        $this->assertEquals(0, $unapprovedCount);
    }

    /**
     * 8. Tidak ada produk, varian, gambar produk, stock movement, atau brand yang dibuat oleh seeder ini.
     */
    public function test_seeder_does_not_create_products_variants_images_movements_or_brands(): void
    {
        $this->seed(MasterCategorySeeder::class);

        $this->assertEquals(0, Product::count());
        $this->assertEquals(0, Variant::count());
        $this->assertEquals(0, ProductImage::count());
        $this->assertEquals(0, StockMovement::count());
        $this->assertEquals(0, Brand::count());
    }

    /**
     * 9. DatabaseSeeder berhasil memanggil MasterCategorySeeder secara terintegrasi.
     */
    public function test_database_seeder_calls_master_category_seeder_correctly(): void
    {
        $this->seed(DatabaseSeeder::class);

        // User dari DatabaseSeeder tetap dibuat
        $this->assertEquals(1, User::where('email', 'test@example.com')->count());

        // Kategori terbuat melalui pemanggilan seeder terintegrasi
        $this->assertEquals(2, Category::count());
        $this->assertDatabaseHas('categories', ['slug' => 'retro', 'is_active' => true]);
        $this->assertDatabaseHas('categories', ['slug' => 'half-face', 'is_active' => true]);
    }
}
