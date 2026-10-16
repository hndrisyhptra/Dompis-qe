<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('qe_lops', function (Blueprint $table): void {
            $table->string('status_aiss')->nullable()->after('status_lop');
        });
    }

    public function down(): void
    {
        if (DB::table('qe_lops')->whereNotNull('status_aiss')->exists()) {
            throw new RuntimeException('Rollback ditolak: Status AISS masih berisi data. Backup dan tentukan penanganannya terlebih dahulu.');
        }
        Schema::table('qe_lops', fn (Blueprint $table) => $table->dropColumn('status_aiss'));
    }
};
