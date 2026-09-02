<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Append-only audit trail untuk setiap perubahan status qe_lops (dan event
     * terkait seperti assignment/re-assignment). Tidak diberi soft delete -
     * histori tidak boleh dihapus sama sekali.
     */
    public function up(): void
    {
        Schema::create('qe_lop_histories', function (Blueprint $table) {
            $table->id('id_qe_lop_histories');

            $table->foreignId('qe_lop_id')
                ->constrained('qe_lops', 'id_qe_lops')
                ->cascadeOnDelete();

            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('status_before')->nullable();
            $table->string('status_after');
            $table->string('event_type')->default('status_change');
            $table->text('note')->nullable();
            $table->json('meta')->nullable();

            $table->timestamps();

            $table->index(['qe_lop_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('qe_lop_histories');
    }
};
