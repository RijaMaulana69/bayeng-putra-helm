<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::unprepared('
                CREATE TRIGGER trg_stock_movements_prevent_update
                BEFORE UPDATE ON stock_movements
                FOR EACH ROW
                BEGIN
                    SIGNAL SQLSTATE \'45000\'
                    SET MESSAGE_TEXT = \'Security Violation: Tabel stock_movements bersifat immutable. UPDATE dilarang.\';
                END
            ');

            DB::unprepared('
                CREATE TRIGGER trg_stock_movements_prevent_delete
                BEFORE DELETE ON stock_movements
                FOR EACH ROW
                BEGIN
                    SIGNAL SQLSTATE \'45000\'
                    SET MESSAGE_TEXT = \'Security Violation: Tabel stock_movements bersifat immutable. DELETE dilarang.\';
                END
            ');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::unprepared('DROP TRIGGER IF EXISTS trg_stock_movements_prevent_update');
            DB::unprepared('DROP TRIGGER IF EXISTS trg_stock_movements_prevent_delete');
        }
    }
};
