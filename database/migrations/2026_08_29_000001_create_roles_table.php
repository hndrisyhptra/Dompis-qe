<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Master table roles (menggantikan pendekatan enum-kolom di modul LOP
     * sebelumnya) - lebih maintainable & auditable. Data seed langsung di
     * migration karena ini lookup table fundamental yang tidak berubah
     * lewat UI (bukan data transaksional).
     */
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->timestamps();
        });

        DB::table('roles')->insert([
            ['code' => 'SUPER_ADMIN', 'name' => 'Super Admin', 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'ADMIN', 'name' => 'Admin', 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'TEKNISI', 'name' => 'Teknisi', 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'MANAGER', 'name' => 'Manager', 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'APPROVER', 'name' => 'Approver', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('roles');
    }
};
