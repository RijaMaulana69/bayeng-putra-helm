<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Variant;
use App\Services\Inventory\InventoryService;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

class InventoryServiceTest extends TestCase
{
    use RefreshDatabase;

    protected InventoryService $service;

    protected User $staff;

    protected Brand $brand;

    protected Category $category;

    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new InventoryService;

        $this->staff = User::factory()->create([
            'role' => Role::KARYAWAN,
        ]);

        $this->brand = Brand::create([
            'nama' => 'Cargloss',
            'slug' => 'cargloss',
        ]);

        $this->category = Category::create([
            'nama' => 'Retro',
            'slug' => 'retro',
        ]);

        $this->product = Product::create([
            'brand_id' => $this->brand->id,
            'category_id' => $this->category->id,
            'nama' => 'CF Retro',
            'slug' => 'cargloss-cf-retro',
            'is_active' => true,
        ]);
    }

    /**
     * Helper membuat varian helm untuk pengujian.
     */
    protected function createVariant(array $attributes = []): Variant
    {
        return Variant::create(array_merge([
            'product_id' => $this->product->id,
            'sku' => 'CGL-CFR-'.uniqid(),
            'warna' => 'Black',
            'ukuran' => 'L',
            'harga' => 300000,
            'stok_fisik' => 10,
            'stok_dipesan' => 0,
            'berat_gram' => 1300,
            'is_active' => true,
        ], $attributes));
    }

    /**
     * 1. Service menolak pemanggilan tanpa transaksi aktif.
     */
    public function test_rejects_call_without_active_transaction(): void
    {
        $variant = $this->createVariant(['stok_fisik' => 10]);

        // RefreshDatabase membungkus test di dalam transaksi. Rollback sementara untuk menguji level 0.
        $level = DB::transactionLevel();
        while (DB::transactionLevel() > 0) {
            DB::rollBack();
        }

        try {
            $this->expectException(\LogicException::class);
            $this->expectExceptionMessage('Pelanggaran Kontrak Arsitektur');

            $this->service->deductPosSale([$variant->id => 2], $this->staff->id, 'STRUK-001');
        } finally {
            // Pulihkan level transaksi untuk teardown RefreshDatabase
            while (DB::transactionLevel() < $level) {
                DB::beginTransaction();
            }
        }
    }

    /**
     * 2. Service menerima transaksi aktif pada koneksi yang benar.
     */
    public function test_accepts_call_with_active_transaction_on_correct_connection(): void
    {
        $this->createVariant(['stok_fisik' => 10]);

        $connection = DB::connection();
        $this->assertInstanceOf(ConnectionInterface::class, $connection);

        $result = $connection->transaction(function () {
            return $this->service->assertActiveTransaction();
        });

        $this->assertInstanceOf(ConnectionInterface::class, $result);
        $this->assertSame($connection->getName(), $result->getName());
    }

    /**
     * 3. Transaksi aktif pada koneksi default yang berbeda dari koneksi model Variant ditolak.
     */
    public function test_rejects_active_transaction_when_variant_connection_differs_or_has_no_transaction(): void
    {
        $mockVariantConnection = Mockery::mock(ConnectionInterface::class);
        $mockVariantConnection->shouldReceive('getName')->andReturn('sqlite_untransacted');
        $mockVariantConnection->shouldReceive('transactionLevel')->andReturn(0);

        $serviceWithDifferentVariantConnection = new class($mockVariantConnection) extends InventoryService
        {
            public function __construct(private ConnectionInterface $customConn) {}

            protected function getVariantConnection(): ConnectionInterface
            {
                return $this->customConn;
            }
        };

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Pelanggaran Kontrak Arsitektur');

        DB::transaction(function () use ($serviceWithDifferentVariantConnection) {
            $serviceWithDifferentVariantConnection->assertActiveTransaction();
        });
    }

    /**
     * 4. Koneksi Variant dan StockMovement yang berbeda ditolak.
     */
    public function test_rejects_differing_connections_between_variant_and_stock_movement(): void
    {
        $serviceWithDifferentConnection = new class extends InventoryService
        {
            protected function getStockMovementConnection(): ConnectionInterface
            {
                $mock = Mockery::mock(ConnectionInterface::class);
                $mock->shouldReceive('getName')->andReturn('mock_connection');

                return $mock;
            }
        };

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Inkonsistensi Konfigurasi');

        DB::transaction(function () use ($serviceWithDifferentConnection) {
            $serviceWithDifferentConnection->assertActiveTransaction();
        });
    }

    /**
     * 5. Koneksi Variant dan User yang berbeda ditolak.
     */
    public function test_rejects_differing_connections_between_variant_and_user(): void
    {
        $serviceWithDifferentUserConnection = new class extends InventoryService
        {
            protected function getUserConnection(): ConnectionInterface
            {
                $mock = Mockery::mock(ConnectionInterface::class);
                $mock->shouldReceive('getName')->andReturn('mock_user_connection');

                return $mock;
            }
        };

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Inkonsistensi Konfigurasi');

        DB::transaction(function () use ($serviceWithDifferentUserConnection) {
            $serviceWithDifferentUserConnection->assertActiveTransaction();
        });
    }

    /**
     * 6. Kegagalan validasi tidak menyebabkan perubahan stok atau ledger.
     */
    public function test_validation_failures_do_not_mutate_stock_or_create_ledger(): void
    {
        $variant = $this->createVariant([
            'stok_fisik' => 10,
            'stok_dipesan' => 2,
        ]);
        $initialMovementsCount = StockMovement::count();

        // A. Keranjang kosong
        try {
            DB::transaction(fn () => $this->service->deductPosSale([], $this->staff->id, 'STRUK-ERR-1'));
        } catch (\InvalidArgumentException $e) {
        }

        // B. Kuantitas invalid (-1, 0, 1000, 24.0, '024')
        $invalidQuantities = [-1, 0, 1000, 24.0, '024'];
        foreach ($invalidQuantities as $invQty) {
            try {
                DB::transaction(fn () => $this->service->deductPosSale([$variant->id => $invQty], $this->staff->id, 'STRUK-ERR-2'));
            } catch (\InvalidArgumentException $e) {
            }
        }

        // C. Staf tidak ditemukan
        try {
            DB::transaction(fn () => $this->service->deductPosSale([$variant->id => 1], 999999, 'STRUK-ERR-3'));
        } catch (\InvalidArgumentException $e) {
        }

        // D. Nomor struk kosong
        try {
            DB::transaction(fn () => $this->service->deductPosSale([$variant->id => 1], $this->staff->id, '   '));
        } catch (\InvalidArgumentException $e) {
        }

        // Verifikasi ketat: stok varian tetap utuh
        $variant->refresh();
        $this->assertSame(10, $variant->stok_fisik);
        $this->assertSame(2, $variant->stok_dipesan);
        $this->assertSame(8, $variant->stok_tersedia);

        // Verifikasi ketat: tidak ada buku besar (ledger) baru yang dibuat
        $this->assertSame($initialMovementsCount, StockMovement::count());
    }

    /**
     * 7. Penjualan POS berhasil ketika stok mencukupi (termasuk normalisasi string digit).
     */
    public function test_pos_sale_succeeds_when_stock_is_sufficient(): void
    {
        $variant = $this->createVariant([
            'stok_fisik' => 10,
            'stok_dipesan' => 2,
        ]);

        DB::transaction(function () use ($variant) {
            // Uji normalisasi string digit dengan spasi pada ID dan kuantitas
            $this->service->deductPosSale([" {$variant->id} " => ' 3 '], $this->staff->id, 'STRUK-POS-001');
        });

        $variant->refresh();
        $this->assertSame(7, $variant->stok_fisik);
        $this->assertSame(2, $variant->stok_dipesan);
        $this->assertSame(5, $variant->stok_tersedia);
    }

    /**
     * 8. stok_fisik berkurang sesuai kuantitas.
     */
    public function test_stok_fisik_decreases_by_exact_quantity(): void
    {
        $variant = $this->createVariant([
            'stok_fisik' => 15,
            'stok_dipesan' => 0,
        ]);

        DB::transaction(function () use ($variant) {
            $this->service->deductPosSale([$variant->id => 5], $this->staff->id, 'STRUK-POS-002');
        });

        $variant->refresh();
        $this->assertSame(10, $variant->stok_fisik);
    }

    /**
     * 9. stok_dipesan tetap dan tidak berubah oleh transaksi POS (Isolasi Reservasi).
     */
    public function test_stok_dipesan_remains_untouched_by_pos_sale(): void
    {
        $variant = $this->createVariant([
            'stok_fisik' => 10,
            'stok_dipesan' => 4,
        ]);

        DB::transaction(function () use ($variant) {
            $this->service->deductPosSale([$variant->id => 2], $this->staff->id, 'STRUK-POS-003');
        });

        $variant->refresh();
        $this->assertSame(8, $variant->stok_fisik);
        $this->assertSame(4, $variant->stok_dipesan);
    }

    /**
     * 10. Ledger POS berhasil dibuat.
     */
    public function test_pos_sale_creates_ledger_entry(): void
    {
        $variant = $this->createVariant([
            'stok_fisik' => 8,
            'stok_dipesan' => 1,
        ]);

        DB::transaction(function () use ($variant) {
            $this->service->deductPosSale([$variant->id => 3], $this->staff->id, 'STRUK-POS-004');
        });

        $this->assertDatabaseHas('stock_movements', [
            'variant_id' => $variant->id,
            'order_id' => null,
            'order_item_id' => null,
            'user_id' => $this->staff->id,
            'tipe' => 'keluar_pos',
            'perubahan_stok_fisik' => -3,
            'perubahan_stok_dipesan' => 0,
        ]);
    }

    /**
     * 11. Saldo sebelum dan sesudah pada ledger benar.
     */
    public function test_ledger_balances_before_and_after_are_accurate(): void
    {
        $variant = $this->createVariant([
            'stok_fisik' => 12,
            'stok_dipesan' => 3,
        ]);

        DB::transaction(function () use ($variant) {
            $this->service->deductPosSale([$variant->id => 4], $this->staff->id, 'STRUK-POS-005');
        });

        $movement = StockMovement::where('variant_id', $variant->id)->latest('id')->first();
        $this->assertNotNull($movement);
        $this->assertSame(12, $movement->stok_fisik_sebelum);
        $this->assertSame(8, $movement->stok_fisik_sesudah);
        $this->assertSame(3, $movement->stok_dipesan_sebelum);
        $this->assertSame(3, $movement->stok_dipesan_sesudah);
        $this->assertSame(-4, $movement->perubahan_stok_fisik);
        $this->assertSame(0, $movement->perubahan_stok_dipesan);
        $this->assertStringContainsString('STRUK-POS-005', $movement->catatan);
    }

    /**
     * 12. Penjualan ditolak jika stok tersedia tidak cukup (stok_fisik - stok_dipesan < diminta).
     */
    public function test_pos_sale_rejected_when_available_stock_insufficient(): void
    {
        $variant = $this->createVariant([
            'stok_fisik' => 5,
            'stok_dipesan' => 3, // stok_tersedia = 2
        ]);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('tidak mencukupi');

        DB::transaction(function () use ($variant) {
            // Minta 3 padahal tersedia hanya 2
            $this->service->deductPosSale([$variant->id => 3], $this->staff->id, 'STRUK-POS-006');
        });
    }

    /**
     * 13. Penjualan ditolak jika stok seluruhnya sudah direservasi online (stok_fisik === stok_dipesan).
     */
    public function test_pos_sale_rejected_when_all_physical_stock_is_reserved(): void
    {
        $variant = $this->createVariant([
            'stok_fisik' => 4,
            'stok_dipesan' => 4, // stok_tersedia = 0
        ]);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('tidak mencukupi');

        DB::transaction(function () use ($variant) {
            $this->service->deductPosSale([$variant->id => 1], $this->staff->id, 'STRUK-POS-007');
        });
    }

    /**
     * 14. Penjualan ditolak untuk varian tidak ditemukan.
     */
    public function test_pos_sale_rejected_for_non_existent_variant(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('tidak ditemukan');

        DB::transaction(function () {
            $this->service->deductPosSale([999999 => 1], $this->staff->id, 'STRUK-POS-008');
        });
    }

    /**
     * 15. Penjualan ditolak untuk varian tidak aktif atau produk tidak aktif.
     */
    public function test_pos_sale_rejected_for_inactive_variant_or_product(): void
    {
        $inactiveVariant = $this->createVariant([
            'stok_fisik' => 10,
            'is_active' => false,
        ]);

        try {
            DB::transaction(function () use ($inactiveVariant) {
                $this->service->deductPosSale([$inactiveVariant->id => 1], $this->staff->id, 'STRUK-POS-009A');
            });
            $this->fail('Harusnya melempar DomainException karena varian inaktif.');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('tidak aktif', $e->getMessage());
        }

        // Uji produk tidak aktif
        $inactiveProduct = Product::create([
            'brand_id' => $this->brand->id,
            'category_id' => $this->category->id,
            'nama' => 'Helm Mati',
            'slug' => 'helm-mati',
            'is_active' => false,
        ]);
        $variantUnderInactiveProduct = Variant::create([
            'product_id' => $inactiveProduct->id,
            'sku' => 'HLM-MT-01',
            'warna' => 'Black',
            'ukuran' => 'M',
            'harga' => 100000,
            'stok_fisik' => 10,
            'stok_dipesan' => 0,
            'is_active' => true,
        ]);

        try {
            DB::transaction(function () use ($variantUnderInactiveProduct) {
                $this->service->deductPosSale([$variantUnderInactiveProduct->id => 1], $this->staff->id, 'STRUK-POS-009B');
            });
            $this->fail('Harusnya melempar DomainException karena produk inaktif.');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('tidak aktif', $e->getMessage());
        }
    }

    /**
     * 16. Input kuantitas tidak valid ditolak (boolean, float, 0, negatif, >999).
     */
    public function test_pos_sale_rejected_for_invalid_quantity(): void
    {
        $variant = $this->createVariant(['stok_fisik' => 10]);

        // Kuantitas 0
        try {
            DB::transaction(function () use ($variant) {
                $this->service->deductPosSale([$variant->id => 0], $this->staff->id, 'STRUK-POS-010');
            });
            $this->fail('Kuantitas 0 harus ditolak.');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('berada di luar batas', $e->getMessage());
        }

        // Kuantitas negatif
        try {
            DB::transaction(function () use ($variant) {
                $this->service->deductPosSale([$variant->id => -5], $this->staff->id, 'STRUK-POS-010');
            });
            $this->fail('Kuantitas negatif harus ditolak.');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('berada di luar batas', $e->getMessage());
        }

        // Kuantitas melebihi batas 999
        try {
            DB::transaction(function () use ($variant) {
                $this->service->deductPosSale([$variant->id => 1000], $this->staff->id, 'STRUK-POS-010');
            });
            $this->fail('Kuantitas > 999 harus ditolak.');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('berada di luar batas', $e->getMessage());
        }

        // Float 24.0
        try {
            DB::transaction(function () use ($variant) {
                $this->service->deductPosSale([$variant->id => 24.0], $this->staff->id, 'STRUK-POS-010');
            });
            $this->fail('Float 24.0 harus ditolak.');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('tidak boleh berupa boolean, float, atau null', $e->getMessage());
        }

        // Boolean true
        try {
            DB::transaction(function () use ($variant) {
                $this->service->deductPosSale([$variant->id => true], $this->staff->id, 'STRUK-POS-010');
            });
            $this->fail('Boolean true harus ditolak.');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('tidak boleh berupa boolean, float, atau null', $e->getMessage());
        }
    }

    /**
     * 17. Atomic Multi-Variant POS Rollback Test (3 Varian: A, B, dan C).
     *
     * Varian A dan B berhasil dimutasi stok dan ledgernya dalam transaksi,
     * lalu kegagalan database dipaksa saat ledger varian C ditulis.
     * Seluruh perubahan wajib di-rollback ke kondisi awal persis.
     */
    public function test_atomic_multi_variant_pos_rollback_when_variant_c_ledger_fails(): void
    {
        $variantA = $this->createVariant(['sku' => 'SKU-ROLL-A', 'warna' => 'Red', 'ukuran' => 'M', 'stok_fisik' => 10, 'stok_dipesan' => 1]);
        $variantB = $this->createVariant(['sku' => 'SKU-ROLL-B', 'warna' => 'Blue', 'ukuran' => 'L', 'stok_fisik' => 15, 'stok_dipesan' => 2]);
        $variantC = $this->createVariant(['sku' => 'SKU-ROLL-C', 'warna' => 'Black', 'ukuran' => 'XL', 'stok_fisik' => 20, 'stok_dipesan' => 3]);

        // Pastikan urutan id: A < B < C untuk membuktikan pengurutan deterministik
        $this->assertTrue($variantA->id < $variantB->id && $variantB->id < $variantC->id);

        $initialMovementsCount = StockMovement::count();

        // Buat trigger SQLite sementara yang HANYA menolak insert ledger untuk varian C
        DB::unprepared("
            CREATE TRIGGER trg_test_fail_variant_c_ledger
            BEFORE INSERT ON stock_movements
            WHEN NEW.variant_id = {$variantC->id}
            BEGIN
                SELECT RAISE(ABORT, 'Simulasi kegagalan database saat insert ledger varian C');
            END;
        ");

        try {
            DB::transaction(function () use ($variantA, $variantB, $variantC) {
                // Urutan input sengaja diacak: C, A, B
                // Service wajib mengurutkan secara deterministik A -> B -> C (id ASC)
                // Mutasi stok fisik A & B terjadi dan ledger A & B berhasil di-insert ke DB transaksi,
                // lalu saat ledger C di-insert, trigger SQLite memicu QueryException ABORT
                $this->service->deductPosSale([
                    $variantC->id => 2,
                    $variantA->id => 3,
                    $variantB->id => 4,
                ], $this->staff->id, 'STRUK-FAIL-C');
            });
            $this->fail('Harusnya melempar QueryException karena trigger SQLite menolak insert ledger varian C.');
        } catch (QueryException $e) {
            $this->assertStringContainsString('Simulasi kegagalan database saat insert ledger varian C', $e->getMessage());
        } finally {
            // Bersihkan trigger sementara secara aman di blok finally
            DB::unprepared('DROP TRIGGER IF EXISTS trg_test_fail_variant_c_ledger;');
        }

        // Refresh seluruh model varian dari database pasca-rollback
        $variantA->refresh();
        $variantB->refresh();
        $variantC->refresh();

        // Assertion wajib:
        // 1. stok_fisik A kembali persis ke nilai awal (10)
        $this->assertSame(10, $variantA->stok_fisik);
        // 2. stok_dipesan A kembali persis ke nilai awal (1)
        $this->assertSame(1, $variantA->stok_dipesan);

        // 3. stok_fisik B kembali persis ke nilai awal (15)
        $this->assertSame(15, $variantB->stok_fisik);
        // 4. stok_dipesan B kembali persis ke nilai awal (2)
        $this->assertSame(2, $variantB->stok_dipesan);

        // 5. stok_fisik C kembali persis ke nilai awal (20)
        $this->assertSame(20, $variantC->stok_fisik);
        // 6. stok_dipesan C kembali persis ke nilai awal (3)
        $this->assertSame(3, $variantC->stok_dipesan);

        // 7. Tidak ada ledger dari transaksi yang gagal untuk A, B, maupun C
        $this->assertDatabaseMissing('stock_movements', [
            'catatan' => 'Penjualan langsung toko / PoS Struk #STRUK-FAIL-C',
        ]);
        $this->assertSame($initialMovementsCount, StockMovement::count());
    }

    /**
     * 18. Data varian dengan beberapa ID diproses dalam urutan lock deterministik (id ASC).
     */
    public function test_multiple_variants_are_processed_deterministically(): void
    {
        $variant1 = $this->createVariant(['sku' => 'SKU-001', 'warna' => 'Red', 'ukuran' => 'M', 'stok_fisik' => 10]);
        $variant2 = $this->createVariant(['sku' => 'SKU-002', 'warna' => 'Blue', 'ukuran' => 'L', 'stok_fisik' => 15]);
        $variant3 = $this->createVariant(['sku' => 'SKU-003', 'warna' => 'Black', 'ukuran' => 'XL', 'stok_fisik' => 20]);

        // Pastikan urutan ID: $variant1->id < $variant2->id < $variant3->id
        $this->assertTrue($variant1->id < $variant2->id && $variant2->id < $variant3->id);

        // Berikan urutan acak / terbalik: variant3, variant1, variant2
        $items = [
            $variant3->id => 2,
            $variant1->id => 3,
            $variant2->id => 4,
        ];

        $executedQueries = [];
        DB::listen(function ($query) use (&$executedQueries) {
            $executedQueries[] = [
                'sql' => $query->sql,
                'bindings' => $query->bindings,
            ];
        });

        DB::transaction(function () use ($items) {
            $this->service->deductPosSale($items, $this->staff->id, 'STRUK-MULTI');
        });

        // 1. Verifikasi query pemilihan varian memuat ORDER BY id ASC
        // Catatan: Pada SQLiteGrammar, lockForUpdate() dikompilasi tanpa klausul 'FOR UPDATE'
        // karena SQLite menerapkan database-level locking. Pada MySQL, klausul 'FOR UPDATE' dihasilkan otomatis.
        $selectQueries = array_filter($executedQueries, function ($q) {
            return str_contains(strtolower($q['sql']), 'variants') &&
                   str_contains(strtolower($q['sql']), 'order by');
        });
        $this->assertNotEmpty($selectQueries, 'Query select varian dengan ORDER BY harus dijalankan.');
        $selectSql = strtolower(reset($selectQueries)['sql']);
        $this->assertMatchesRegularExpression('/order by\s+[`"]?id[`"]?\s+asc/i', $selectSql);

        // 2. Verifikasi mutasi fisik dan ledger terjadi secara berurutan sesuai id ASC ($variant1 -> $variant2 -> $variant3)
        $variant1->refresh();
        $variant2->refresh();
        $variant3->refresh();

        $this->assertSame(7, $variant1->stok_fisik);
        $this->assertSame(11, $variant2->stok_fisik);
        $this->assertSame(18, $variant3->stok_fisik);

        // Verifikasi urutan pembuatan stock_movements sesuai urutan ID menaik
        $movements = StockMovement::where('catatan', 'like', '%STRUK-MULTI%')->orderBy('id', 'asc')->get();
        $this->assertCount(3, $movements);
        $this->assertSame($variant1->id, $movements[0]->variant_id);
        $this->assertSame($variant2->id, $movements[1]->variant_id);
        $this->assertSame($variant3->id, $movements[2]->variant_id);
    }

    /**
     * 19. Validasi keranjang kosong, staf tidak ada, dan nomor struk tidak valid.
     */
    public function test_validates_cart_staff_and_receipt_parameters(): void
    {
        $variant = $this->createVariant(['stok_fisik' => 10]);

        // Keranjang kosong
        try {
            DB::transaction(function () {
                $this->service->deductPosSale([], $this->staff->id, 'STRUK-VALID');
            });
            $this->fail('Keranjang kosong harus ditolak.');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('tidak boleh kosong', $e->getMessage());
        }

        // Staf ID tidak ditemukan
        try {
            DB::transaction(function () use ($variant) {
                $this->service->deductPosSale([$variant->id => 1], 999999, 'STRUK-VALID');
            });
            $this->fail('Staf tidak ada harus ditolak.');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('tidak ditemukan', $e->getMessage());
        }

        // Nomor struk kosong / spasi
        try {
            DB::transaction(function () use ($variant) {
                $this->service->deductPosSale([$variant->id => 1], $this->staff->id, '   ');
            });
            $this->fail('Struk spasi harus ditolak.');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('Nomor struk', $e->getMessage());
        }

        // Nomor struk > 50 karakter
        try {
            DB::transaction(function () use ($variant) {
                $this->service->deductPosSale([$variant->id => 1], $this->staff->id, str_repeat('A', 51));
            });
            $this->fail('Struk > 50 char harus ditolak.');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('maksimal 50 karakter', $e->getMessage());
        }
    }

    /**
     * 20. Validasi integer ketat sesuai Proposal v2.9 §5.3 (regex /^[1-9]\d*$/).
     *
     * Menguji seluruh variasi input valid dan tidak valid yang disyaratkan secara spesifik.
     */
    public function test_validates_strict_positive_int_policy(): void
    {
        // 1. Input VALID
        // - 24
        $this->assertSame(24, $this->service->validateStrictPositiveInt(24));
        // - "24"
        $this->assertSame(24, $this->service->validateStrictPositiveInt('24'));
        // - " 24 "
        $this->assertSame(24, $this->service->validateStrictPositiveInt('  24  '));
        // - 1
        $this->assertSame(1, $this->service->validateStrictPositiveInt(1));
        // - PHP_INT_MAX
        $this->assertSame(PHP_INT_MAX, $this->service->validateStrictPositiveInt(PHP_INT_MAX));
        // - PHP_INT_MAX - 1
        $this->assertSame(PHP_INT_MAX - 1, $this->service->validateStrictPositiveInt(PHP_INT_MAX - 1));
        // - Batas bisnis $max = 999
        $this->assertSame(999, $this->service->validateStrictPositiveInt(999, max: 999));
        $this->assertSame(999, $this->service->validateStrictPositiveInt('999', max: 999));

        // 2. Batas bisnis $max = 999 terlampaui melempar InvalidArgumentException
        try {
            $this->service->validateStrictPositiveInt(1000, max: 999);
            $this->fail('Nilai 1000 dengan $max=999 harusnya ditolak.');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('berada di luar batas', $e->getMessage());
        }

        // 3. String dengan angka nol di depan (leading zeros) DITOLAK: "024", "00024"
        $leadingZeroStrings = ['024', '00024', ' 024 ', ' 00024 '];
        foreach ($leadingZeroStrings as $lz) {
            try {
                $this->service->validateStrictPositiveInt($lz);
                $this->fail("String berawalan nol '{$lz}' harusnya ditolak.");
            } catch (\InvalidArgumentException $e) {
                $this->assertStringContainsString('bukan bilangan bulat positif', $e->getMessage());
            }
        }

        // 4. String hanya berisi angka nol ditolak: "0", "00"
        $zeroStrings = ['0', '00', '000', ' 0 ', ' 00 '];
        foreach ($zeroStrings as $zeroStr) {
            try {
                $this->service->validateStrictPositiveInt($zeroStr);
                $this->fail("String nol '{$zeroStr}' harusnya ditolak.");
            } catch (\InvalidArgumentException $e) {
                $this->assertStringContainsString('bukan bilangan bulat positif', $e->getMessage());
            }
        }

        // 5. Input TIDAK VALID spesifik yang wajib melempar InvalidArgumentException
        $invalidInputs = [
            true, false, // boolean
            24.0, 1.0, // float
            '24.0', '24.5', // string float/desimal
            2.4e1, '2.4e1', // notasi ilmiah float & string
            0, -1, // nol dan negatif integer
            '2 4', // spasi di tengah
            '', '   ', null, // string kosong, spasi murni, null
            'abc', // huruf alfabet
        ];

        foreach ($invalidInputs as $invalid) {
            try {
                $this->service->validateStrictPositiveInt($invalid);
                $this->fail('Gagal: Nilai '.var_export($invalid, true).' harusnya ditolak oleh validateStrictPositiveInt.');
            } catch (\InvalidArgumentException $e) {
                $this->assertTrue(true);
            }
        }

        // 6. Overflow terhadap PHP_INT_MAX melempar OverflowException
        $overflowInputs = [
            '9223372036854775808', // PHP_INT_MAX + 1
            '99999999999999999999',
        ];

        foreach ($overflowInputs as $overflowStr) {
            try {
                $this->service->validateStrictPositiveInt($overflowStr);
                $this->fail("Gagal: Overflow '{$overflowStr}' harus melempar OverflowException.");
            } catch (\OverflowException $e) {
                $this->assertStringContainsString('melampaui kapasitas', $e->getMessage());
            }
        }
    }

    /**
     * 21. Validasi ketat terhadap parameter $staffUserId pada deductPosSale.
     */
    public function test_pos_sale_staff_user_id_strict_validation(): void
    {
        $variant = $this->createVariant(['stok_fisik' => 10]);

        // Integer valid diterima
        DB::transaction(function () use ($variant) {
            $this->service->deductPosSale([$variant->id => 1], $this->staff->id, 'STRUK-STAFF-1');
        });

        // String digit valid diterima
        DB::transaction(function () use ($variant) {
            $this->service->deductPosSale([$variant->id => 1], (string) $this->staff->id, 'STRUK-STAFF-2');
        });

        // String digit dengan leading zero DITOLAK
        try {
            DB::transaction(function () use ($variant) {
                $this->service->deductPosSale([$variant->id => 1], '0'.$this->staff->id, 'STRUK-STAFF-LZ');
            });
            $this->fail('Leading zero pada staffUserId harus ditolak.');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('bukan bilangan bulat positif', $e->getMessage());
        }

        // Float bulat ditolak (misal 1.0)
        try {
            DB::transaction(function () use ($variant) {
                $this->service->deductPosSale([$variant->id => 1], (float) $this->staff->id, 'STRUK-STAFF-FAIL');
            });
            $this->fail('Float staffUserId harus ditolak.');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('tidak boleh berupa boolean, float, atau null', $e->getMessage());
        }

        // Boolean true ditolak
        try {
            DB::transaction(function () use ($variant) {
                $this->service->deductPosSale([$variant->id => 1], true, 'STRUK-STAFF-FAIL');
            });
            $this->fail('Boolean staffUserId harus ditolak.');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('tidak boleh berupa boolean, float, atau null', $e->getMessage());
        }

        // Boolean false ditolak
        try {
            DB::transaction(function () use ($variant) {
                $this->service->deductPosSale([$variant->id => 1], false, 'STRUK-STAFF-FAIL');
            });
            $this->fail('Boolean false staffUserId harus ditolak.');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('tidak boleh berupa boolean, float, atau null', $e->getMessage());
        }
    }
}
