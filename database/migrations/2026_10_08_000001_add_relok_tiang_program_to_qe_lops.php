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
            $table->enum('program_type', ['recovery', 'preventive', 'relok_utilitas', 'relok_tiang'])->change();
        });
    }

    public function down(): void
    {
        if (DB::table('qe_lops')->where('program_type', 'relok_tiang')->exists()) {
            throw new RuntimeException('Rollback dibatalkan: masih ada LOP QE Relok Tiang, termasuk yang soft-deleted.');
        }

        Schema::table('qe_lops', function (Blueprint $table) {
            $table->enum('program_type', ['recovery', 'preventive', 'relok_utilitas'])->change();
        });
    }
};
