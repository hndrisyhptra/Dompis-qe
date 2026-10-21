<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('qe_lops', function (Blueprint $table) {
            // Salinan normalized dari ticket.reported_date agar filter/sort lokal
            // tidak perlu query ke koneksi read-only mysql_dompis.
            $table->date('reported_date')->nullable()->after('datek');
            $table->index('reported_date');
        });
    }

    public function down(): void
    {
        Schema::table('qe_lops', function (Blueprint $table) {
            $table->dropIndex(['reported_date']);
            $table->dropColumn('reported_date');
        });
    }
};
