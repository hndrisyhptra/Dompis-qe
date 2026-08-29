<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Kolom baru sesuai skema Authentication. nik & username dibuat
     * nullable+unique (bukan NOT NULL) karena tabel users bisa saja sudah
     * berisi data - unique index tetap valid dengan banyak NULL di MySQL
     * maupun SQLite. Enforcement "wajib diisi utk user baru" dilakukan di
     * StoreUserRequest (form request), bukan di level kolom. Admin perlu
     * backfill nik/username untuk user lama yang sudah ada sebelum modul
     * ini di-deploy.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('role_id')->nullable()->after('id_user')
                ->constrained('roles')->nullOnDelete();

            $table->string('nik')->nullable()->unique()->after('role_id');
            $table->string('username')->nullable()->unique()->after('nik');

            $table->string('phone')->nullable()->after('email');

            $table->foreignId('branch_id')->nullable()->after('phone')
                ->constrained('branches')->nullOnDelete();

            $table->enum('status', ['active', 'inactive'])->default('active')->after('branch_id');
            $table->timestamp('last_login_at')->nullable()->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('branch_id');
            $table->dropColumn(['status', 'last_login_at', 'phone', 'username', 'nik']);
            $table->dropConstrainedForeignId('role_id');
        });
    }
};
