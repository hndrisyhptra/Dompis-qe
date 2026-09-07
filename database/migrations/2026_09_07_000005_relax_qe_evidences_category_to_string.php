<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ubah qe_evidences.category dari enum ketat -> string(30). Kategori
     * evidence bertambah seiring workflow (mis. `insera` = capture tiket
     * Insera di Step Pra); enum di-enforce di app layer lewat
     * App\Enums\EvidenceCategory (pola sama seperti qe_lops.segment &
     * ticket_segment_maps.segment). Index (qe_lop_id, category) dipertahankan.
     */
    public function up(): void
    {
        Schema::table('qe_evidences', function (Blueprint $table) {
            $table->string('category', 30)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('qe_evidences', function (Blueprint $table) {
            $table->enum('category', ['pre', 'material_arrival', 'before', 'progress', 'after'])
                ->nullable()->change();
        });
    }
};
