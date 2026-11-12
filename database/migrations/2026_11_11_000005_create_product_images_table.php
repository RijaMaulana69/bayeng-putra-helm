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
        Schema::create('product_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->onDelete('cascade');
            $table->unsignedBigInteger('variant_id')->nullable()->index();
            $table->string('file_path', 255);
            $table->string('alt_text', 255);
            $table->unsignedSmallInteger('urutan')->default(0);
            $table->boolean('is_primary')->default(false)->index();
            $table->boolean('primary_flag')->nullable()->storedAs('IF(is_primary = 1, 1, NULL)');
            $table->timestamps();

            $table->unique(['product_id', 'primary_flag'], 'uk_product_single_primary_image');

            $table->foreign(['variant_id', 'product_id'], 'fk_product_images_variant_product')
                ->references(['id', 'product_id'])
                ->on('variants')
                ->onDelete('restrict');
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE product_images ADD CONSTRAINT chk_primary_image_has_no_variant CHECK (is_primary = 0 OR (is_primary = 1 AND variant_id IS NULL))');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_images');
    }
};
