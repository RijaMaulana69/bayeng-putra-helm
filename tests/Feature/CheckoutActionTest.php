<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Order\CheckoutAction;
use App\Enums\AuditActorType;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Variant;
use App\Services\Cart\CartService;
use App\Services\Inventory\InventoryService;
use Carbon\Carbon;
use DomainException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Tests\TestCase;

class CheckoutActionTest extends TestCase
{
    use RefreshDatabase;

    protected User $buyer;

    protected Category $category;

    protected Brand $brand;

    protected Product $product;

    protected Variant $variantA;

    protected Variant $variantB;

    protected CheckoutAction $checkoutAction;

    protected function setUp(): void
    {
        parent::setUp();

        $this->buyer = User::factory()->create();

        $this->category = Category::create([
            'nama' => 'Helm Full Face',
            'slug' => 'helm-full-face',
            'is_active' => true,
        ]);

        $this->brand = Brand::create([
            'nama' => 'KYT',
            'slug' => 'kyt',
            'is_active' => true,
        ]);

        $this->product = Product::create([
            'category_id' => $this->category->id,
            'brand_id' => $this->brand->id,
            'nama' => 'KYT TT Course',
            'slug' => 'kyt-tt-course',
            'deskripsi' => 'Helm balap harian berbobot ringan',
            'harga_dasar' => 1200000,
            'is_active' => true,
        ]);

        $this->variantA = Variant::create([
            'product_id' => $this->product->id,
            'sku' => 'KYT-TTC-BLK-L',
            'warna' => 'Black Doff',
            'ukuran' => 'L',
            'harga' => 1250000,
            'stok_fisik' => 10,
            'stok_dipesan' => 0,
            'is_active' => true,
        ]);

        $this->variantB = Variant::create([
            'product_id' => $this->product->id,
            'sku' => 'KYT-TTC-RED-M',
            'warna' => 'Red Glossy',
            'ukuran' => 'M',
            'harga' => 1300000,
            'stok_fisik' => 5,
            'stok_dipesan' => 1,
            'is_active' => true,
        ]);

        $this->checkoutAction = app(CheckoutAction::class);
    }

    /**
     * Test A: Checkout pertama berhasil dengan token UUID valid.
     */
    public function test_a_checkout_pertama_berhasil_dengan_idempotency_token(): void
    {
        $token = (string) Str::uuid();
        $items = [
            ['variant_id' => $this->variantA->id, 'kuantitas' => 2],
        ];

        $order = $this->checkoutAction->execute(
            $this->buyer,
            PaymentMethod::TRANSFER_BANK,
            $items,
            $token,
            'Tolong dipacking kayu'
        );

        $this->assertInstanceOf(Order::class, $order);
        $this->assertSame($token, $order->idempotency_key);
        $this->assertStringStartsWith('ORD-', $order->nomor_pesanan);
        $this->assertSame(OrderStatus::MENUNGGU_PEMBAYARAN, $order->status);
        $this->assertSame(PaymentStatus::BELUM_DIBAYAR, $order->status_pembayaran);
        $this->assertSame(PaymentMethod::TRANSFER_BANK, $order->metode_pembayaran);
        $this->assertSame(2, $order->total_produk);
        $this->assertSame(2500000, (int) $order->total_harga);
        $this->assertSame(2500000, (int) $order->total_akhir);
        $this->assertSame('Tolong dipacking kayu', $order->catatan);

        // Validasi mutasi reservasi stok pada varian A
        $this->variantA->refresh();
        $this->assertSame(10, $this->variantA->stok_fisik); // Fisik tetap
        $this->assertSame(2, $this->variantA->stok_dipesan); // Dipesan bertambah

        // Validasi buku besar (Ledger)
        $this->assertDatabaseHas('stock_movements', [
            'order_id' => $order->id,
            'variant_id' => $this->variantA->id,
            'tipe' => 'reservasi_masuk',
            'perubahan_stok_fisik' => 0,
            'perubahan_stok_dipesan' => 2,
            'stok_fisik_sebelum' => 10,
            'stok_fisik_sesudah' => 10,
            'stok_dipesan_sebelum' => 0,
            'stok_dipesan_sesudah' => 2,
        ]);

        // Validasi audit log
        $this->assertDatabaseHas('order_audit_logs', [
            'order_id' => $order->id,
            'tipe_aktor' => AuditActorType::PEMBELI->value,
            'buyer_user_id' => $this->buyer->id,
            'status_sesudah' => OrderStatus::MENUNGGU_PEMBAYARAN->value,
        ]);
    }

    /**
     * Test B: Retry request dengan token sama dan payload sama (Idempotent Replay).
     */
    public function test_b_retry_token_sama_dan_payload_sama_mengembalikan_order_identik_tanpa_efek_samping(): void
    {
        $token = (string) Str::uuid();
        $items = [
            ['variant_id' => $this->variantA->id, 'kuantitas' => 2],
        ];

        // Eksekusi pertama
        $orderFirst = $this->checkoutAction->execute(
            $this->buyer,
            PaymentMethod::TRANSFER_BANK,
            $items,
            $token,
            'Catatan pertama'
        );

        $initialOrderCount = Order::count();
        $initialItemCount = OrderItem::count();
        $initialMovementCount = StockMovement::count();
        $this->variantA->refresh();
        $initialReservedStock = $this->variantA->stok_dipesan;

        // Eksekusi kedua (simulasi network retry atau double click)
        $orderRetry = $this->checkoutAction->execute(
            $this->buyer,
            PaymentMethod::TRANSFER_BANK,
            $items,
            $token,
            'Catatan pertama'
        );

        // Verifikasi hasil: harus mengembalikan instance pesanan yang sama
        $this->assertSame($orderFirst->id, $orderRetry->id);
        $this->assertSame($orderFirst->nomor_pesanan, $orderRetry->nomor_pesanan);

        // Verifikasi zero side-effect:
        $this->assertSame($initialOrderCount, Order::count());
        $this->assertSame($initialItemCount, OrderItem::count());
        $this->assertSame($initialMovementCount, StockMovement::count());

        $this->variantA->refresh();
        $this->assertSame($initialReservedStock, $this->variantA->stok_dipesan);
    }

    /**
     * Test C: Token sama digunakan untuk payload berbeda (Wajib Ditolak).
     */
    public function test_c_token_sama_dengan_payload_berbeda_ditolak_keras(): void
    {
        $token = (string) Str::uuid();
        $itemsA = [
            ['variant_id' => $this->variantA->id, 'kuantitas' => 1],
        ];

        // Checkout awal
        $this->checkoutAction->execute($this->buyer, PaymentMethod::TRANSFER_BANK, $itemsA, $token);

        // Percobaan checkout ulang dengan token sama tapi kuantitas beda
        $itemsB = [
            ['variant_id' => $this->variantA->id, 'kuantitas' => 2],
        ];

        $initialOrderCount = Order::count();
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Payload Tidak Cocok/i');

        try {
            $this->checkoutAction->execute($this->buyer, PaymentMethod::TRANSFER_BANK, $itemsB, $token);
        } finally {
            $this->assertSame($initialOrderCount, Order::count());
        }
    }

    /**
     * Test D: Token sama dicoba digunakan oleh pengguna berbeda (Wajib Ditolak & Tidak Bocor).
     */
    public function test_d_token_sama_dari_pengguna_berbeda_ditolak_tanpa_bocor(): void
    {
        $token = (string) Str::uuid();
        $items = [
            ['variant_id' => $this->variantA->id, 'kuantitas' => 1],
        ];

        // Pengguna A checkout
        $this->checkoutAction->execute($this->buyer, PaymentMethod::TRANSFER_BANK, $items, $token);

        // Pengguna B mencoba memakai token pengguna A
        $otherUser = User::factory()->create();

        $this->expectException(DomainException::class);
        $this->expectExceptionMessageMatches('/Akses Ditolak.*tidak valid untuk pengguna ini/i');

        $this->checkoutAction->execute($otherUser, PaymentMethod::TRANSFER_BANK, $items, $token);
    }

    /**
     * Test E: Token baru diperlakukan sebagai proses checkout terpisah.
     */
    public function test_e_token_baru_menghasilkan_pesanan_baru(): void
    {
        $token1 = (string) Str::uuid();
        $token2 = (string) Str::uuid();

        $order1 = $this->checkoutAction->execute($this->buyer, PaymentMethod::TRANSFER_BANK, [
            ['variant_id' => $this->variantA->id, 'kuantitas' => 1],
        ], $token1);

        $order2 = $this->checkoutAction->execute($this->buyer, PaymentMethod::TRANSFER_BANK, [
            ['variant_id' => $this->variantA->id, 'kuantitas' => 1],
        ], $token2);

        $this->assertNotSame($order1->id, $order2->id);
        $this->assertNotSame($order1->nomor_pesanan, $order2->nomor_pesanan);

        $this->variantA->refresh();
        $this->assertSame(2, $this->variantA->stok_dipesan); // 1 + 1 = 2
    }

    /**
     * Test F: Rollback transaksi kegagalan tidak meninggalkan jejak token terpakai.
     */
    public function test_f_kegagalan_transaksi_rollback_penuh_dan_token_tidak_tersimpan(): void
    {
        $token = (string) Str::uuid();
        // Varian B tersedia 4, minta 10 -> pasti gagal
        $items = [
            ['variant_id' => $this->variantB->id, 'kuantitas' => 10],
        ];

        try {
            $this->checkoutAction->execute($this->buyer, PaymentMethod::TRANSFER_BANK, $items, $token);
            $this->fail('Seharusnya gagal karena stok tidak mencukupi');
        } catch (DomainException $e) {
            $this->assertStringContainsString('tidak mencukupi', $e->getMessage());
        }

        // Token tidak boleh tersimpan di database
        $this->assertDatabaseMissing('orders', [
            'idempotency_key' => $token,
        ]);

        // Token yang sama dapat dicoba lagi setelah stok disesuaikan
        $orderValid = $this->checkoutAction->execute($this->buyer, PaymentMethod::TRANSFER_BANK, [
            ['variant_id' => $this->variantB->id, 'kuantitas' => 1],
        ], $token);

        $this->assertSame($token, $orderValid->idempotency_key);
    }

    /**
     * Test G: Token invalid (kosong, bukan UUID, melebihi batas panjang).
     */
    public function test_g_token_invalid_ditolak_sebelum_transaksi(): void
    {
        $items = [
            ['variant_id' => $this->variantA->id, 'kuantitas' => 1],
        ];

        // 1. Token kosong
        try {
            $this->checkoutAction->execute($this->buyer, PaymentMethod::TRANSFER_BANK, $items, '   ');
            $this->fail('Token kosong harus ditolak');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('wajib berupa string', $e->getMessage());
        }

        // 2. Token bukan UUID
        try {
            $this->checkoutAction->execute($this->buyer, PaymentMethod::TRANSFER_BANK, $items, 'random-token-123');
            $this->fail('Token bukan UUID harus ditolak');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('bukan format UUID yang valid', $e->getMessage());
        }

        // 3. Token melebihi 100 karakter
        $longToken = str_repeat('a', 101);
        try {
            $this->checkoutAction->execute($this->buyer, PaymentMethod::TRANSFER_BANK, $items, $longToken);
            $this->fail('Token lebih dari 100 karakter harus ditolak');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('tidak boleh melebihi 100 karakter', $e->getMessage());
        }
    }

    /**
     * Test H: Database UNIQUE constraint menolak duplikasi token secara fisik.
     */
    public function test_h_database_unique_constraint_enforces_idempotency_key(): void
    {
        $token = (string) Str::uuid();

        // Buat order pertama langsung di level DB
        Order::create([
            'nomor_pesanan' => 'ORD-TEST-IDEM-001',
            'idempotency_key' => $token,
            'user_id' => $this->buyer->id,
            'metode_pembayaran' => PaymentMethod::TRANSFER_BANK,
            'total_produk' => 1,
            'total_harga' => 100000,
            'total_akhir' => 100000,
            'nominal_kewajiban' => 100000,
            'expires_at' => now()->addHours(2),
        ]);

        // Coba insert order kedua dengan idempotency_key yang sama secara langsung via DB
        $this->expectException(QueryException::class);

        Order::create([
            'nomor_pesanan' => 'ORD-TEST-IDEM-002',
            'idempotency_key' => $token,
            'user_id' => $this->buyer->id,
            'metode_pembayaran' => PaymentMethod::TRANSFER_BANK,
            'total_produk' => 1,
            'total_harga' => 100000,
            'total_akhir' => 100000,
            'nominal_kewajiban' => 100000,
            'expires_at' => now()->addHours(2),
        ]);
    }

    /**
     * Test I: Konsistensi UI dan CartService token lifecycle.
     */
    public function test_i_cart_service_token_lifecycle(): void
    {
        $cartService = app(CartService::class);

        // Token stabil saat dipanggil berulang
        $token1 = $cartService->getCheckoutToken();
        $token2 = $cartService->getCheckoutToken();
        $this->assertSame($token1, $token2);
        $this->assertTrue(Str::isUuid($token1));

        // Setelah checkout dan cart di-clear, token baru digenerate untuk transaksi berikutnya
        $cartService->clear();
        $token3 = $cartService->getCheckoutToken();
        $this->assertNotSame($token1, $token3);
    }

    /**
     * Test J: Ketentuan metode pembayaran dan TTL tetap terjaga.
     */
    public function test_j_payment_methods_and_ttl_rules(): void
    {
        $tokenBank = (string) Str::uuid();
        $tokenEwallet = (string) Str::uuid();
        $tokenStore = (string) Str::uuid();

        // 1. Transfer Bank: TTL 2 jam, bayar 100%
        $orderBank = $this->checkoutAction->execute($this->buyer, PaymentMethod::TRANSFER_BANK, [
            ['variant_id' => $this->variantA->id, 'kuantitas' => 1],
        ], $tokenBank);
        $diffHoursBank = (int) round(Carbon::now('UTC')->diffInSeconds($orderBank->expires_at) / 3600);
        $this->assertSame(2, $diffHoursBank);
        $this->assertSame((int) $orderBank->total_akhir, (int) $orderBank->nominal_kewajiban);

        // 2. E-Wallet: TTL 2 jam, bayar 100%
        $orderEwallet = $this->checkoutAction->execute($this->buyer, PaymentMethod::EWALLET, [
            ['variant_id' => $this->variantA->id, 'kuantitas' => 1],
        ], $tokenEwallet);
        $diffHoursEwallet = (int) round(Carbon::now('UTC')->diffInSeconds($orderEwallet->expires_at) / 3600);
        $this->assertSame(2, $diffHoursEwallet);

        // 3. Bayar di Toko: TTL 24 jam, tanpa payment record saat checkout
        $orderStore = $this->checkoutAction->execute($this->buyer, PaymentMethod::BAYAR_DI_TOKO, [
            ['variant_id' => $this->variantA->id, 'kuantitas' => 1],
        ], $tokenStore);
        $diffHoursStore = (int) round(Carbon::now('UTC')->diffInSeconds($orderStore->expires_at) / 3600);
        $this->assertSame(24, $diffHoursStore);
        $this->assertDatabaseMissing('payments', ['order_id' => $orderStore->id]);
    }

    /**
     * Test Tambahan: Harga dari input browser tidak dipercaya.
     */
    public function test_price_from_browser_is_ignored_and_fetched_from_database(): void
    {
        $token = (string) Str::uuid();
        $items = [
            [
                'variant_id' => $this->variantA->id,
                'kuantitas' => 1,
                'harga' => 1000, // Disusupi
            ],
        ];

        $order = $this->checkoutAction->execute($this->buyer, PaymentMethod::TRANSFER_BANK, $items, $token);

        $this->assertSame(1250000, (int) $order->total_harga);
        $this->assertSame(1250000, (int) $order->total_akhir);
    }

    /**
     * Test Tambahan: Dua item dengan varian yang sama dalam satu payload checkout diagregasikan dengan benar.
     */
    public function test_duplicate_variants_in_payload_are_aggregated_properly(): void
    {
        $token = (string) Str::uuid();
        $items = [
            ['variant_id' => $this->variantA->id, 'kuantitas' => 1],
            ['variant_id' => $this->variantA->id, 'kuantitas' => 2],
        ];

        $order = $this->checkoutAction->execute($this->buyer, PaymentMethod::TRANSFER_BANK, $items, $token);

        $this->assertCount(1, $order->items);
        $this->assertSame(3, $order->items->first()->kuantitas);
        $this->assertSame(3, $order->total_produk);
        $this->assertSame(3750000, (int) $order->total_akhir);
    }

    /**
     * Test Tambahan: Koneksi database konsisten dengan kontrak transaksi InventoryService.
     */
    public function test_database_connection_consistency_contract(): void
    {
        $inventoryService = app(InventoryService::class);

        // RefreshDatabase membungkus test di dalam transaksi. Rollback sementara untuk menguji level 0.
        $level = DB::transactionLevel();
        while (DB::transactionLevel() > 0) {
            DB::rollBack();
        }

        try {
            $this->expectException(\LogicException::class);
            $this->expectExceptionMessageMatches('/wajib dieksekusi di dalam transaksi database aktif/i');

            $order = new Order;
            $inventoryService->reserveStockForOrder($order, [
                ['variant_id' => $this->variantA->id, 'qty' => 1, 'order_item_id' => 1],
            ], $this->buyer->id);
        } finally {
            for ($i = 0; $i < $level; $i++) {
                DB::beginTransaction();
            }
        }
    }
}
