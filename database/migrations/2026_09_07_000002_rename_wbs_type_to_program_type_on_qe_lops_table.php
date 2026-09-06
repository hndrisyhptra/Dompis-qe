<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Istilah "WBS" diganti "Program" di seluruh aplikasi -> rename kolom
     * qe_lops.wbs_type menjadi program_type.
     *
     * Guarded: instalasi baru sudah membuat kolom `program_type` langsung
     * (migration create_qe_lops sudah diperbarui), jadi rename di-skip.
     */
    public function up(): void
    {
        if (Schema::hasColumn('qe_lops', 'wbs_type') && ! Schema::hasColumn('qe_lops', 'program_type')) {
            Schema::table('qe_lops', function (Blueprint $table) {
                $table->renameColumn('wbs_type', 'program_type');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('qe_lops', 'program_type') && ! Schema::hasColumn('qe_lops', 'wbs_type')) {
            Schema::table('qe_lops', function (Blueprint $table) {
                $table->renameColumn('program_type', 'wbs_type');
            });
        }
    }
};
