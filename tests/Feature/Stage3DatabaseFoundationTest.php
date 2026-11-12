<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\AuditActorType;
use App\Enums\EwalletProvider;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentRecordStatus;
use App\Enums\PaymentRecordType;
use App\Enums\PaymentStatus;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderAuditLog;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use App\Models\Variant;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class Stage3DatabaseFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_stage_3_tables_are_created_successfully(): void
    {
        $this->assertTrue(Schema::hasTable('orders'));
        $this->assertTrue(Schema::hasTable('order_items'));
        $this->assertTrue(Schema::hasTable('payments'));
        $this->assertTrue(Schema::hasTable('order_audit_logs'));

        // Cek kolom penting pada tabel orders
        $this->assertTrue(Schema::hasColumns('orders', [
            'id', 'nomor_pesanan', 'idempotency_key', 'user_id', 'status', 'status_pembayaran',
            'metode_pembayaran', 'total_produk', 'total_harga', 'diskon',
            'total_akhir', 'nominal_kewajiban', 'catatan', 'grace_period_used',
            'expires_at', 'confirmed_at', 'completed_at', 'cancelled_at',
            'expired_at', 'created_at', 'updated_at',
        ]));

        // Cek kolom penting pada tabel order_items
        $this->assertTrue(Schema::hasColumns('order_items', [
            'id', 'order_id', 'variant_id', 'nama_produk', 'sku', 'warna',
            'ukuran', 'harga_satuan', 'kuantitas', 'diskon_dialokasikan',
            'subtotal', 'subtotal_bersih', 'kuantitas_diserahkan',
            'kuantitas_dibatalkan', 'kuantitas_kedaluwarsa',
            'created_at', 'updated_at',
        ]));

        // Cek kolom penting pada tabel payments
        $this->assertTrue(Schema::hasColumns('payments', [
            'id', 'order_id', 'nomor_transaksi', 'tipe', 'metode',
            'provider_ewallet', 'nominal', 'uang_diterima', 'kembalian',
            'status', 'bukti_transfer_path', 'alasan', 'idempotency_key',
            'request_hash', 'staff_user_id', 'verified_at',
            'created_at', 'updated_at',
        ]));

        // Cek kolom penting pada tabel order_audit_logs
        $this->assertTrue(Schema::hasColumns('order_audit_logs', [
            'id', 'order_id', 'tipe_aktor', 'staff_user_id', 'buyer_user_id',
            'status_sebelum', 'status_sesudah', 'catatan', 'created_at',
        ]));
    }

    public function test_order_creation_and_backed_enums(): void
    {
        $user = User::factory()->create();

        $order = Order::create([
            'nomor_pesanan' => 'ORD-20261112-TEST01',
            'user_id' => $user->id,
            'metode_pembayaran' => PaymentMethod::TRANSFER_BANK,
            'total_produk' => 1,
            'total_harga' => 350000,
            'diskon' => 0,
            'total_akhir' => 350000,
            'nominal_kewajiban' => 350000,
            'catatan' => 'Pesanan uji coba milestone 1',
            'expires_at' => Carbon::now('UTC')->addHours(2),
        ]);

        // Verifikasi default state dari model
        $this->assertSame(OrderStatus::DIBUAT, $order->status);
        $this->assertSame(PaymentStatus::BELUM_DIBAYAR, $order->status_pembayaran);

        // Status dan status_pembayaran dilindungi dari mass assignment publik demi keamanan transaksi.
        // Transisi dilakukan secara eksplisit pada instance model.
        $order->status = OrderStatus::MENUNGGU_PEMBAYARAN;
        $order->save();

        $this->assertDatabaseHas('orders', [
            'nomor_pesanan' => 'ORD-20261112-TEST01',
            'status' => 'menunggu_pembayaran',
            'status_pembayaran' => 'belum_dibayar',
            'metode_pembayaran' => 'transfer_bank',
        ]);

        // Cek casting enums
        $this->assertInstanceOf(OrderStatus::class, $order->status);
        $this->assertSame(OrderStatus::MENUNGGU_PEMBAYARAN, $order->status);
        $this->assertInstanceOf(PaymentStatus::class, $order->status_pembayaran);
        $this->assertSame(PaymentStatus::BELUM_DIBAYAR, $order->status_pembayaran);
        $this->assertInstanceOf(PaymentMethod::class, $order->metode_pembayaran);
        $this->assertSame(PaymentMethod::TRANSFER_BANK, $order->metode_pembayaran);

        // Helper methods
        $this->assertFalse($order->isExpired());
        $this->assertTrue($order->canBePaidOnline());
        $this->assertFalse($order->canBePaidAtCashier());
    }

    public function test_order_item_creation_and_remaining_reserved_quantity(): void
    {
        $user = User::factory()->create();
        $category = Category::create(['nama' => 'Helm Full Face', 'slug' => 'helm-full-face']);
        $brand = Brand::create(['nama' => 'KYT', 'slug' => 'kyt']);
        $product = Product::create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'nama' => 'KYT TT Course',
            'slug' => 'kyt-tt-course',
            'harga_dasar' => 1200000,
            'status' => 'aktif',
        ]);
        $variant = Variant::create([
            'product_id' => $product->id,
            'sku' => 'KYT-TTC-BLK-L',
            'warna' => 'Black Doff',
            'ukuran' => 'L',
            'harga' => 1250000,
            'stok_fisik' => 10,
            'stok_dipesan' => 2,
        ]);

        $order = Order::create([
            'nomor_pesanan' => 'ORD-20261112-TEST02',
            'user_id' => $user->id,
            'metode_pembayaran' => PaymentMethod::BAYAR_DI_TOKO,
            'total_produk' => 2,
            'total_harga' => 2500000,
            'diskon' => 0,
            'total_akhir' => 2500000,
            'nominal_kewajiban' => 2500000,
            'expires_at' => Carbon::now('UTC')->addHours(24),
        ]);
        $order->status = OrderStatus::SIAP_DIAMBIL;
        $order->save();

        $item = OrderItem::create([
            'order_id' => $order->id,
            'variant_id' => $variant->id,
            'nama_produk' => 'KYT TT Course',
            'sku' => 'KYT-TTC-BLK-L',
            'warna' => 'Black Doff',
            'ukuran' => 'L',
            'harga_satuan' => 1250000,
            'kuantitas' => 2,
            'subtotal' => 2500000,
            'diskon_dialokasikan' => 0,
            'subtotal_bersih' => 2500000,
            'kuantitas_diserahkan' => 0,
            'kuantitas_dibatalkan' => 0,
            'kuantitas_kedaluwarsa' => 0,
        ]);

        $this->assertSame(2, $item->remainingReservedQuantity());

        // Simulasi diserahkan 1
        $item->kuantitas_diserahkan = 1;
        $this->assertSame(1, $item->remainingReservedQuantity());

        // Relasi
        $this->assertInstanceOf(Order::class, $item->order);
        $this->assertSame($order->id, $item->order->id);
        $this->assertInstanceOf(Variant::class, $item->variant);
        $this->assertSame($variant->id, $item->variant->id);
    }

    public function test_payment_creation_and_methods(): void
    {
        $user = User::factory()->create();
        $staff = User::factory()->create();

        $order = Order::create([
            'nomor_pesanan' => 'ORD-20261112-TEST03',
            'user_id' => $user->id,
            'metode_pembayaran' => PaymentMethod::EWALLET,
            'total_produk' => 1,
            'total_harga' => 300000,
            'diskon' => 0,
            'total_akhir' => 300000,
            'nominal_kewajiban' => 300000,
            'expires_at' => Carbon::now('UTC')->addHours(2),
        ]);
        $order->status = OrderStatus::MENUNGGU_VERIFIKASI;
        $order->status_pembayaran = PaymentStatus::MENUNGGU_KONFIRMASI;
        $order->save();

        $payment = Payment::create([
            'order_id' => $order->id,
            'nomor_transaksi' => 'PAY-20261112-TEST01',
            'tipe' => PaymentRecordType::PEMBAYARAN,
            'metode' => PaymentMethod::EWALLET,
            'provider_ewallet' => EwalletProvider::DANA,
            'status' => PaymentRecordStatus::MENUNGGU_VERIFIKASI,
            'nominal' => 300000,
            'bukti_transfer_path' => 'payment_proofs/sample_proof.jpg',
            'idempotency_key' => 'idem-pay-test-001',
            'request_hash' => hash('sha256', 'sample-request-data'),
        ]);

        $this->assertInstanceOf(PaymentRecordType::class, $payment->tipe);
        $this->assertSame(PaymentRecordType::PEMBAYARAN, $payment->tipe);
        $this->assertInstanceOf(PaymentMethod::class, $payment->metode);
        $this->assertSame(PaymentMethod::EWALLET, $payment->metode);
        $this->assertInstanceOf(EwalletProvider::class, $payment->provider_ewallet);
        $this->assertSame(EwalletProvider::DANA, $payment->provider_ewallet);
        $this->assertInstanceOf(PaymentRecordStatus::class, $payment->status);
        $this->assertSame(PaymentRecordStatus::MENUNGGU_VERIFIKASI, $payment->status);

        $this->assertFalse($payment->isAccepted());
        $this->assertFalse($payment->isRejected());
        $this->assertFalse($payment->isCashPayment());

        // Verifikasi pembayaran
        $payment->status = PaymentRecordStatus::DITERIMA;
        $payment->staff_user_id = $staff->id;
        $payment->verified_at = Carbon::now('UTC');
        $payment->save();

        $this->assertTrue($payment->isAccepted());
        $this->assertSame($staff->id, $payment->staff->id);
    }

    public function test_cash_payment_at_cashier(): void
    {
        $user = User::factory()->create();
        $staff = User::factory()->create();

        $order = Order::create([
            'nomor_pesanan' => 'ORD-20261112-TEST04',
            'user_id' => $user->id,
            'metode_pembayaran' => PaymentMethod::BAYAR_DI_TOKO,
            'total_produk' => 1,
            'total_harga' => 150000,
            'diskon' => 0,
            'total_akhir' => 150000,
            'nominal_kewajiban' => 150000,
            'expires_at' => Carbon::now('UTC')->addHours(24),
        ]);
        $order->status = OrderStatus::SIAP_DIAMBIL;
        $order->save();

        $this->assertTrue($order->canBePaidAtCashier());

        $payment = Payment::create([
            'order_id' => $order->id,
            'nomor_transaksi' => 'PAY-20261112-TEST02',
            'tipe' => PaymentRecordType::PEMBAYARAN,
            'metode' => PaymentMethod::BAYAR_DI_TOKO,
            'status' => PaymentRecordStatus::DITERIMA,
            'nominal' => 150000,
            'uang_diterima' => 200000,
            'kembalian' => 50000,
            'idempotency_key' => 'idem-pay-test-cash-001',
            'request_hash' => hash('sha256', 'cash-request-001'),
            'staff_user_id' => $staff->id,
            'verified_at' => Carbon::now('UTC'),
        ]);

        $this->assertTrue($payment->isCashPayment());
        $this->assertTrue($payment->isAccepted());
        $this->assertSame(200000, $payment->uang_diterima);
        $this->assertSame(50000, $payment->kembalian);
    }

    public function test_order_audit_log_and_relations(): void
    {
        $user = User::factory()->create();
        $staff = User::factory()->create();

        $order = Order::create([
            'nomor_pesanan' => 'ORD-20261112-TEST05',
            'user_id' => $user->id,
            'metode_pembayaran' => PaymentMethod::TRANSFER_BANK,
            'total_produk' => 1,
            'total_harga' => 200000,
            'diskon' => 0,
            'total_akhir' => 200000,
            'nominal_kewajiban' => 200000,
            'expires_at' => Carbon::now('UTC')->addHours(2),
        ]);

        $log = OrderAuditLog::create([
            'order_id' => $order->id,
            'tipe_aktor' => AuditActorType::STAF,
            'staff_user_id' => $staff->id,
            'status_sebelum' => OrderStatus::MENUNGGU_VERIFIKASI->value,
            'status_sesudah' => OrderStatus::DIKONFIRMASI->value,
            'catatan' => 'Verifikasi pembayaran transfer bank disetujui staf.',
        ]);

        $this->assertInstanceOf(AuditActorType::class, $log->tipe_aktor);
        $this->assertSame(AuditActorType::STAF, $log->tipe_aktor);
        $this->assertNull($log->updated_at);
        $this->assertNotNull($log->created_at);

        // Relasi audit log
        $this->assertSame($order->id, $log->order->id);
        $this->assertSame($staff->id, $log->staffUser->id);
        $this->assertNull($log->buyerUser);

        // Relasi dari Order
        $this->assertCount(1, $order->auditLogs);
        $this->assertSame($log->id, $order->auditLogs->first()->id);
    }
}
