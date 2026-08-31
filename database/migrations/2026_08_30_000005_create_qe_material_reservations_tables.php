<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('qe_material_reservations', function (Blueprint $table) {
            $table->id('id_reservation');
            $table->foreignId('qe_lop_id')->constrained('qe_lops', 'id_qe_lops')->cascadeOnDelete();
            $table->foreignId('technician_id')->constrained('users', 'id_user')->restrictOnDelete();
            $table->enum('status', ['draft', 'submitted'])->default('draft');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique('qe_lop_id');
            $table->index(['technician_id', 'status']);
        });

        Schema::create('qe_material_reservation_items', function (Blueprint $table) {
            $table->id('id_reservation_item');
            $table->foreignId('reservation_id')
                ->constrained('qe_material_reservations', 'id_reservation')->cascadeOnDelete();
            $table->foreignId('designator_id')
                ->constrained('designators', 'id_designator')->restrictOnDelete();
            $table->decimal('qty', 15, 3);
            $table->timestamps();

            // Nama eksplisit dijaga di bawah batas identifier 64 karakter
            // MySQL/MariaDB.
            $table->unique(['reservation_id', 'designator_id'], 'reservation_item_designator_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qe_material_reservation_items');
        Schema::dropIfExists('qe_material_reservations');
    }
};
