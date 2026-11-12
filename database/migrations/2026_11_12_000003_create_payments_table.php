<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->onDelete('restrict');
            $table->string('nomor_transaksi', 100)->unique();
            $table->string('tipe', 30)->index(); // 'pembayaran', 'refund'
            $table->string('metode', 30)->index(); // 'transfer_bank', 'ewallet', 'bayar_di_toko'
            $table->string('provider_ewallet', 30)->nullable(); // 'DANA', 'GoPay', 'ShopeePay'
            $table->unsignedBigInteger('nominal');
            $table->unsignedBigInteger('uang_diterima')->nullable();
            $table->unsignedBigInteger('kembalian')->nullable();
            $table->string('status', 30)->index(); // 'menunggu_verifikasi', 'diterima', 'ditolak', dll.
            $table->string('bukti_transfer_path', 255)->nullable();
            $table->text('alasan')->nullable();
            $table->string('idempotency_key', 100)->unique();
            $table->char('request_hash', 64);
            $table->foreignId('staff_user_id')->nullable()->constrained('users')->onDelete('restrict');
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();

            $table->index(['order_id'], 'idx_payments_order_id');
            $table->index(['staff_user_id'], 'idx_payments_staff_user_id');
            $table->index(['tipe', 'status'], 'idx_payments_tipe_status');
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement('
                ALTER TABLE payments
                ADD CONSTRAINT chk_payments_ewallet_provider
                CHECK (
                    (metode = \'ewallet\' AND provider_ewallet IS NOT NULL) OR
                    (metode <> \'ewallet\' AND provider_ewallet IS NULL)
                )
            ');

            DB::statement('
                ALTER TABLE payments
                ADD CONSTRAINT chk_payments_tunai_kasir
                CHECK (
                    (tipe = \'pembayaran\' AND metode = \'bayar_di_toko\' AND uang_diterima IS NOT NULL AND kembalian IS NOT NULL AND uang_diterima >= nominal) OR
                    (tipe = \'pembayaran\' AND metode <> \'bayar_di_toko\' AND uang_diterima IS NULL AND kembalian IS NULL) OR
                    (tipe = \'refund\')
                )
            ');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
