<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Reservasi draft hasil import Excel dibuat tanpa teknisi
     * (teknisi diisi saat teknisi menyimpan reservasi / assignment).
     * Baris existing selalu punya technician_id sehingga aman.
     */
    public function up(): void
    {
        Schema::table('qe_material_reservations', function (Blueprint $table) {
            $table->foreignId('technician_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('qe_material_reservations', function (Blueprint $table) {
            $table->foreignId('technician_id')->nullable(false)->change();
        });
    }
};
