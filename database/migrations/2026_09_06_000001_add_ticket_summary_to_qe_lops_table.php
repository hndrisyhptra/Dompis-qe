<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Snapshot ringkas baris tiket dari DB operasional eksternal (dompis_db.ticket)
     * yang ikut disimpan saat Input LOP Baru. Editable oleh admin; tidak dipakai
     * untuk penamaan LOP, murni referensi.
     */
    public function up(): void
    {
        Schema::table('qe_lops', function (Blueprint $table) {
            $table->text('ticket_summary')->nullable()->after('job_description');
        });
    }

    public function down(): void
    {
        Schema::table('qe_lops', function (Blueprint $table) {
            $table->dropColumn('ticket_summary');
        });
    }
};
