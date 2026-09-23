<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Snapshot BOQ hasil import Excel per LOP (MATERIAL + JASA).
     * Reservasi material teknisi tetap MATERIAL-only (guard di
     * TechnicianWorkflowService); baris JASA disimpan di sini agar total
     * harga per LOP tetap akurat tanpa melanggar guard tersebut.
     * Harga diambil dari kolom Excel (snapshot), bukan priceMap DB.
     */
    public function up(): void
    {
        Schema::table('qe_lops', function (Blueprint $table) {
            $table->json('boq_snapshot')->nullable()->after('package_id');
        });
    }

    public function down(): void
    {
        Schema::table('qe_lops', function (Blueprint $table) {
            $table->dropColumn('boq_snapshot');
        });
    }
};
