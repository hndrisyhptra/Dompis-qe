<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * designator_id SENGAJA nullable - evidence step SURVEY/DISMANTLE bersifat
     * umum per-LOP (bukan per-item), sementara BEFORE/PROGRESS/AFTER memang
     * "sesuai item designator". Audit approve/reject cukup di kolom
     * status/review_note/reviewed_by/reviewed_at pada baris ini sendiri -
     * bukan tabel histori terpisah (satu transisi state, beda dengan
     * lifecycle qe_lops yang punya banyak tahap).
     */
    public function up(): void
    {
        Schema::create('qe_evidences', function (Blueprint $table) {
            $table->id('id_evidence');

            $table->foreignId('qe_lop_id')
                ->constrained('qe_lops', 'id_qe_lops')
                ->cascadeOnDelete();

            $table->foreignId('designator_id')->nullable()
                ->constrained('designators', 'id_designator')
                ->nullOnDelete();

            $table->foreignId('uploaded_by')
                ->constrained('users', 'id_user')
                ->restrictOnDelete();

            $table->enum('step', ['SURVEY', 'BEFORE', 'PROGRESS', 'AFTER', 'DISMANTLE']);
            $table->enum('type', ['PHOTO', 'DOCUMENT', 'OTDR', 'OPM', 'TELNET']);

            $table->string('file_path');
            $table->json('metadata')->nullable();
            $table->text('note')->nullable();

            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->text('review_note')->nullable();
            $table->foreignId('reviewed_by')->nullable()
                ->constrained('users', 'id_user')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['qe_lop_id', 'step']);
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('qe_evidences');
    }
};
