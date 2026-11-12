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
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('variant_id')->constrained('variants')->onDelete('restrict');
            $table->unsignedBigInteger('order_id')->nullable()->index();
            $table->unsignedBigInteger('order_item_id')->nullable()->index();
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('restrict');
            $table->string('tipe', 40)->index();
            $table->integer('perubahan_stok_fisik')->default(0);
            $table->integer('perubahan_stok_dipesan')->default(0);
            $table->unsignedInteger('stok_fisik_sebelum');
            $table->unsignedInteger('stok_fisik_sesudah');
            $table->unsignedInteger('stok_dipesan_sebelum');
            $table->unsignedInteger('stok_dipesan_sesudah');
            $table->text('catatan')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement('
                ALTER TABLE stock_movements
                ADD CONSTRAINT chk_stock_movements_order_reference_consistency
                CHECK (
                    (order_id IS NULL AND order_item_id IS NULL)
                    OR
                    (order_id IS NOT NULL AND order_item_id IS NOT NULL)
                )
            ');

            DB::statement('
                ALTER TABLE stock_movements
                ADD CONSTRAINT chk_stock_movements_math_consistency
                CHECK (
                    (CAST(stok_fisik_sebelum AS SIGNED) + CAST(perubahan_stok_fisik AS SIGNED) = CAST(stok_fisik_sesudah AS SIGNED))
                    AND
                    (CAST(stok_dipesan_sebelum AS SIGNED) + CAST(perubahan_stok_dipesan AS SIGNED) = CAST(stok_dipesan_sesudah AS SIGNED))
                    AND
                    (perubahan_stok_fisik != 0 OR perubahan_stok_dipesan != 0)
                    AND
                    (stok_fisik_sebelum >= stok_dipesan_sebelum)
                    AND
                    (stok_fisik_sesudah >= stok_dipesan_sesudah)
                )
            ');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
