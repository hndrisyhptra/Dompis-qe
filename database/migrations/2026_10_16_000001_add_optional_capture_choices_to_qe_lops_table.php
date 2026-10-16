<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('qe_lops', fn (Blueprint $table) => $table->json('optional_capture_choices')->nullable());
    }

    public function down(): void
    {
        if (DB::table('qe_lops')->whereNotNull('optional_capture_choices')->exists()) {
            throw new RuntimeException('Pilihan capture sudah tersimpan. Backup dan tangani data sebelum rollback.');
        }
        Schema::table('qe_lops', fn (Blueprint $table) => $table->dropColumn('optional_capture_choices'));
    }
};
