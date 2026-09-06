<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Thumbnail evidence (webp kecil, dibuat di browser saat upload). Galeri
     * pakai thumb_path; modal preview tetap file asli. Nullable -> baris lama
     * dan file yang tidak sempat di-thumbnail jatuh balik ke file_path.
     */
    public function up(): void
    {
        Schema::table('qe_evidences', function (Blueprint $table) {
            $table->string('thumb_path')->nullable()->after('file_path');
        });
    }

    public function down(): void
    {
        Schema::table('qe_evidences', function (Blueprint $table) {
            $table->dropColumn('thumb_path');
        });
    }
};
