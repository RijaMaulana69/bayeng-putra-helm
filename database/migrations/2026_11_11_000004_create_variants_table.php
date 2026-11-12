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
        Schema::create('variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->onDelete('restrict');
            $table->string('sku', 60)->unique();
            $table->string('warna', 100);
            $table->string('ukuran', 20);
            $table->unsignedBigInteger('harga');
            $table->unsignedInteger('stok_fisik')->default(0);
            $table->unsignedInteger('stok_dipesan')->default(0);
            $table->unsignedInteger('berat_gram')->nullable()->default(null);
            $table->softDeletes();
            $table->boolean('deleted_unique')->nullable()->storedAs('IF(deleted_at IS NULL, 1, NULL)');
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();

            $table->unique(['product_id', 'warna', 'ukuran', 'deleted_unique'], 'uk_variants_active_combination');
            $table->unique(['id', 'product_id'], 'uk_variants_id_product');
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE variants ADD CONSTRAINT chk_variants_harga_safe CHECK (harga > 0 AND harga <= 9223372036854775807)');
            DB::statement('ALTER TABLE variants ADD CONSTRAINT chk_variants_stok_valid CHECK (stok_fisik >= stok_dipesan)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('variants');
    }
};
