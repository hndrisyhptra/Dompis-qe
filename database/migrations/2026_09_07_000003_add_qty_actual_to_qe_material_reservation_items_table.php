<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Qty aktual material yang benar-benar terpakai per designator (diisi
     * teknisi di rekap Step 5). Null = belum direkap. Selalu <= qty reservasi
     * (divalidasi di form); selisih qty - qty_actual = material sisa.
     */
    public function up(): void
    {
        Schema::table('qe_material_reservation_items', function (Blueprint $table) {
            $table->decimal('qty_actual', 15, 3)->nullable()->after('qty');
        });
    }

    public function down(): void
    {
        Schema::table('qe_material_reservation_items', function (Blueprint $table) {
            $table->dropColumn('qty_actual');
        });
    }
};
