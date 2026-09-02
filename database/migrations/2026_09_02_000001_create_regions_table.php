<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Master Region. Sebelumnya region cuma kolom string bebas di `branches`.
     * Lookup table -> seed inline di migration (pola sama roles/permissions)
     * supaya RefreshDatabase di test langsung punya datanya. Audit pakai
     * created_by/updated_by + soft delete (konvensi master data katalog).
     */
    public function up(): void
    {
        Schema::create('regions', function (Blueprint $table) {
            $table->id('id_region');

            $table->string('code')->unique();
            $table->string('name');
            $table->boolean('is_active')->default(true)->index();

            $table->foreignId('created_by')->nullable()
                ->constrained('users', 'id_user')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()
                ->constrained('users', 'id_user')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();
        });

        $now = now();

        DB::table('regions')->insert([
            ['code' => 'JATIM', 'name' => 'REGION JATIM', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'JATENGDIY', 'name' => 'REGION JATENG DIY', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'BALNUS', 'name' => 'REGION BALNUS', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('regions');
    }
};
