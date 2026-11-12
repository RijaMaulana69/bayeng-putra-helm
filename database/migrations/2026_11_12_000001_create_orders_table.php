<?php

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('nomor_pesanan', 50)->unique();
            $table->foreignId('user_id')->constrained('users')->onDelete('restrict');
            $table->string('status', 40)->default(OrderStatus::DIBUAT->value)->index();
            $table->string('status_pembayaran', 40)->default(PaymentStatus::BELUM_DIBAYAR->value)->index();
            $table->string('metode_pembayaran', 40)->index();
            $table->unsignedInteger('total_produk')->default(0);
            $table->unsignedBigInteger('total_harga')->default(0);
            $table->unsignedBigInteger('diskon')->default(0);
            $table->unsignedBigInteger('total_akhir')->default(0);
            $table->unsignedBigInteger('nominal_kewajiban')->default(0);
            $table->text('catatan')->nullable();
            $table->boolean('grace_period_used')->default(false);
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('expired_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'metode_pembayaran', 'expires_at'], 'idx_orders_scheduler_lookup');
            $table->index(['user_id', 'created_at'], 'idx_orders_user_history');
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement('
                ALTER TABLE orders
                ADD CONSTRAINT chk_orders_nominal_math
                CHECK (
                    diskon <= total_harga AND
                    total_akhir = (total_harga - diskon) AND
                    total_akhir <= 9223372036854775807
                )
            ');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
