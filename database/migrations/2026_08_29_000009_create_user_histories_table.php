<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Audit trail modul User Management, meniru pola qe_lop_histories:
     * append-only, tidak ada soft delete - histori tidak boleh dihapus.
     */
    public function up(): void
    {
        Schema::create('user_histories', function (Blueprint $table) {
            $table->id('id_user_history');

            $table->foreignId('target_user_id')
                ->constrained('users', 'id_user')
                ->cascadeOnDelete();

            $table->foreignId('actor_id')
                ->nullable()
                ->constrained('users', 'id_user')
                ->nullOnDelete();

            $table->string('event_type');
            $table->json('changes')->nullable();
            $table->text('note')->nullable();

            $table->timestamps();

            $table->index(['target_user_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_histories');
    }
};
