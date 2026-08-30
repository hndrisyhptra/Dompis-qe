<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * "KHS" - harga satuan sebuah designator di dalam satu paket tertentu.
     * cascadeOnDelete di kedua FK karena baris harga tidak bermakna tanpa
     * designator/package induknya (sama seperti role_permissions).
     */
    public function up(): void
    {
        Schema::create('designator_package_prices', function (Blueprint $table) {
            $table->id('id_designator_package_price');

            $table->foreignId('designator_id')
                ->constrained('designators', 'id_designator')
                ->cascadeOnDelete();

            $table->foreignId('package_id')
                ->constrained('packages', 'id_package')
                ->cascadeOnDelete();

            $table->decimal('price', 15, 2);

            $table->foreignId('created_by')->nullable()
                ->constrained('users', 'id_user')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()
                ->constrained('users', 'id_user')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['designator_id', 'package_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('designator_package_prices');
    }
};
