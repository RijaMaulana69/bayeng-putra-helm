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
        Schema::create('order_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->onDelete('restrict');
            $table->string('tipe_aktor', 30)->index(); // 'staf', 'pembeli', 'sistem'
            $table->foreignId('staff_user_id')->nullable()->constrained('users')->onDelete('restrict');
            $table->foreignId('buyer_user_id')->nullable()->constrained('users')->onDelete('restrict');
            $table->string('status_sebelum', 50)->nullable();
            $table->string('status_sesudah', 50);
            $table->text('catatan')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['order_id'], 'idx_order_audit_logs_order_id');
            $table->index(['staff_user_id'], 'idx_order_audit_logs_staff_user_id');
            $table->index(['buyer_user_id'], 'idx_order_audit_logs_buyer_user_id');
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement('
                ALTER TABLE order_audit_logs
                ADD CONSTRAINT chk_order_audit_actors
                CHECK (
                    (tipe_aktor = \'staf\' AND staff_user_id IS NOT NULL AND buyer_user_id IS NULL) OR
                    (tipe_aktor = \'pembeli\' AND buyer_user_id IS NOT NULL AND staff_user_id IS NULL) OR
                    (tipe_aktor = \'sistem\' AND staff_user_id IS NULL AND buyer_user_id IS NULL)
                )
            ');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_audit_logs');
    }
};
