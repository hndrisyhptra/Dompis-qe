<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * package_id sengaja belum diberi foreign key constraint karena tabel
     * qe_packages (Master Designator/KHS/Paket KHS) belum dibangun di fase ini.
     * Kolom disiapkan nullable agar tidak perlu migration ubah struktur saat
     * modul Master Designator digarap - FK akan ditambahkan lewat migration
     * terpisah saat itu.
     */
    public function up(): void
    {
        Schema::create('qe_lops', function (Blueprint $table) {
            $table->id('id_qe_lops');

            $table->string('kode_lop')->unique();
            $table->string('nama_lop');
            $table->enum('wbs_type', ['recovery', 'preventive', 'relok_utilitas']);

            $table->string('sto')->nullable();
            $table->string('branch')->nullable();

            $table->unsignedBigInteger('package_id')->nullable();

            $table->enum('status_lop', [
                'draft',
                'assigned',
                'picked_up',
                'survey',
                'progress',
                'waiting_approval',
                'completed',
                'rejected',
            ])->default('draft');

            $table->foreignId('created_by')
                ->constrained('users')
                ->restrictOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index('status_lop');
            $table->index(['status_lop', 'wbs_type']);
            $table->index('package_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('qe_lops');
    }
};
