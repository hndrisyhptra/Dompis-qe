<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * technician_id TIDAK PERNAH disimpan di qe_lops langsung (aturan CLAUDE.md) -
     * semua penugasan teknisi wajib lewat tabel ini. Baris lama saat re-assign
     * tidak dihapus, hanya di-set status=replaced + unassigned_at, sehingga
     * histori assignment tetap utuh.
     */
    public function up(): void
    {
        Schema::create('qe_lop_assignments', function (Blueprint $table) {
            $table->id('id_qe_lop_assignments');

            $table->foreignId('qe_lop_id')
                ->constrained('qe_lops', 'id_qe_lops')
                ->cascadeOnDelete();

            $table->foreignId('technician_id')
                ->constrained('users')
                ->restrictOnDelete();

            $table->foreignId('assigned_by')
                ->constrained('users')
                ->restrictOnDelete();

            $table->timestamp('assigned_at');
            $table->timestamp('unassigned_at')->nullable();
            $table->enum('status', ['active', 'replaced'])->default('active');

            $table->timestamps();

            $table->index(['qe_lop_id', 'status']);
            $table->index('technician_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('qe_lop_assignments');
    }
};
