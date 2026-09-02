<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabel pemetaan nilai `jenis_tiket_2` (dari DB eksternal dompis_db.ticket)
     * ke enum LopSegment. Dipakai TicketLookupService saat auto-fill Input LOP
     * Baru. Dikelola SUPER_ADMIN lewat menu Master Data. Nilai jenis_tiket_2
     * yang tidak punya baris di sini -> field segment dibiarkan kosong.
     */
    public function up(): void
    {
        Schema::create('ticket_segment_maps', function (Blueprint $table) {
            $table->id('id_ticket_segment_map');

            $table->string('source_value', 100)->unique();
            $table->string('segment', 30);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_segment_maps');
    }
};
