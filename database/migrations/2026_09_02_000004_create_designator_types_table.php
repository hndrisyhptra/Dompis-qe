<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Master tipe pekerjaan designator (Material/Jasa/Instalasi/...),
     * menggantikan enum `designators.type`. Lookup table -> seed inline.
     * Kode MATERIAL dipakai query reservasi material teknisi
     * (whereRelation('type','code','MATERIAL')), jangan diubah kodenya.
     */
    public function up(): void
    {
        Schema::create('designator_types', function (Blueprint $table) {
            $table->id('id_designator_type');

            $table->string('code')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true)->index();

            $table->foreignId('created_by')->nullable()
                ->constrained('users', 'id_user')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()
                ->constrained('users', 'id_user')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();
        });

        $now = now();
        $rows = [
            ['code' => 'MATERIAL', 'name' => 'Material'],
            ['code' => 'JASA', 'name' => 'Jasa'],
            ['code' => 'INSTALASI', 'name' => 'Instalasi'],
            ['code' => 'PENGUKURAN', 'name' => 'Pengukuran'],
            ['code' => 'DISMANTLE', 'name' => 'Dismantle'],
        ];

        DB::table('designator_types')->insert(array_map(fn ($r) => [
            ...$r, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
        ], $rows));
    }

    public function down(): void
    {
        Schema::dropIfExists('designator_types');
    }
};
