<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * "Datek terdampak" (elemen jaringan yang terimbas) hasil ekstraksi otomatis
     * dari ticket_summary: { odc[], odp[], gpon[{name,ip,ports}], kabel[], ip[],
     * olt, rca, est, pic{nama,telp}, durasi }. Editable admin; murni referensi,
     * tidak dipakai untuk penamaan LOP.
     */
    public function up(): void
    {
        Schema::table('qe_lops', function (Blueprint $table) {
            $table->json('datek')->nullable()->after('ticket_summary');
        });
    }

    public function down(): void
    {
        Schema::table('qe_lops', function (Blueprint $table) {
            $table->dropColumn('datek');
        });
    }
};
