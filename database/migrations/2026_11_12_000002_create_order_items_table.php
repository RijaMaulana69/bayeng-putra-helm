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
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->onDelete('restrict');
            $table->foreignId('variant_id')->constrained('variants')->onDelete('restrict');
            $table->string('nama_produk', 255);
            $table->string('sku', 100);
            $table->string('warna', 50);
            $table->string('ukuran', 50);
            $table->unsignedBigInteger('harga_satuan');
            $table->unsignedInteger('kuantitas');
            $table->unsignedBigInteger('subtotal');
            $table->unsignedBigInteger('diskon_dialokasikan')->default(0);
            $table->unsignedBigInteger('subtotal_bersih')->default(0);
            $table->unsignedInteger('kuantitas_diserahkan')->default(0);
            $table->unsignedInteger('kuantitas_dibatalkan')->default(0);
            $table->unsignedInteger('kuantitas_kedaluwarsa')->default(0);
            $table->timestamps();

            $table->unique(['id', 'order_id', 'variant_id'], 'uk_order_items_composite');
            $table->index(['order_id'], 'idx_order_items_order_id');
            $table->index(['variant_id'], 'idx_order_items_variant_id');
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement('
                ALTER TABLE order_items
                ADD CONSTRAINT chk_order_items_kuantitas_positif
                CHECK (kuantitas > 0)
            ');

            DB::statement('
                ALTER TABLE order_items
                ADD CONSTRAINT chk_order_items_counters
                CHECK (
                    kuantitas_diserahkan + kuantitas_dibatalkan + kuantitas_kedaluwarsa <= kuantitas
                )
            ');

            DB::statement('
                ALTER TABLE order_items
                ADD CONSTRAINT chk_order_items_subtotal_math
                CHECK (
                    subtotal = (harga_satuan * kuantitas) AND
                    diskon_dialokasikan <= subtotal AND
                    subtotal_bersih = (subtotal - diskon_dialokasikan) AND
                    subtotal <= 9223372036854775807
                )
            ');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
