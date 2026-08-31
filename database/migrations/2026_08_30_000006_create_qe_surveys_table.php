<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('qe_surveys', function (Blueprint $table) {
            $table->id('id_survey');
            $table->foreignId('qe_lop_id')->constrained('qe_lops', 'id_qe_lops')->cascadeOnDelete();
            $table->foreignId('captured_by')->constrained('users', 'id_user')->restrictOnDelete();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->decimal('accuracy', 10, 2)->nullable();
            $table->enum('location_source', ['gps', 'manual'])->default('manual');
            $table->timestamp('captured_at');
            $table->timestamps();

            $table->unique('qe_lop_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qe_surveys');
    }
};
