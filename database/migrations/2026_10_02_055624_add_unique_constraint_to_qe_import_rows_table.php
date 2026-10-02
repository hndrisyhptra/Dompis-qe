<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Bersihkan duplikat sebelum menambahkan constraint
        \Illuminate\Support\Facades\DB::statement('
            DELETE t1 FROM qe_import_rows t1
            INNER JOIN qe_import_rows t2 
            WHERE t1.id_import_row > t2.id_import_row 
            AND t1.import_batch_id = t2.import_batch_id 
            AND t1.row_number = t2.row_number
        ');

        Schema::table('qe_import_rows', function (Blueprint $table) {
            $table->unique(['import_batch_id', 'row_number'], 'qe_import_rows_unique_combo');
        });
    }

    public function down(): void
    {
        Schema::table('qe_import_rows', function (Blueprint $table) {
            $table->dropUnique('qe_import_rows_unique_combo');
        });
    }
};
