<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * "Paket KHS" - wadah harga satuan (lihat designator_package_prices).
     * Pola sama seperti designators: custom PK, created_by/updated_by +
     * soft delete sebagai audit trail (bukan tabel histori terpisah).
     */
    public function up(): void
    {
        Schema::create('packages', function (Blueprint $table) {
            $table->id('id_package');

            $table->string('code')->unique();
            $table->string('name');
            $table->text('description')->nullable();

            $table->foreignId('created_by')->nullable()
                ->constrained('users', 'id_user')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()
                ->constrained('users', 'id_user')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('packages');
    }
};
