<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Master table branches - belum ada di dompis-qe maupun dompis-cons
     * (dompis-cons hanya pakai kolom string bebas). Dibuat minimal dulu;
     * kolom lain (alamat, region, dst) bisa ditambah lewat migration
     * terpisah saat modul Master Data digarap.
     */
    public function up(): void
    {
        Schema::create('branches', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('branches');
    }
};
