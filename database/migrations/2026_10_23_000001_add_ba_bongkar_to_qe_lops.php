<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('qe_lops', function (Blueprint $table) {
            $table->boolean('ba_bongkar_required')->nullable();
            $table->json('ba_bongkar_data')->nullable();
        });
    }

    public function down(): void
    {
        if (DB::table('qe_lops')->whereNotNull('ba_bongkar_required')->orWhereNotNull('ba_bongkar_data')->exists()) {
            throw new RuntimeException('Rollback ditolak: keputusan atau draft BA Bongkar sudah tersimpan.');
        }
        Schema::table('qe_lops', fn (Blueprint $table) => $table->dropColumn(['ba_bongkar_required', 'ba_bongkar_data']));
    }
};
