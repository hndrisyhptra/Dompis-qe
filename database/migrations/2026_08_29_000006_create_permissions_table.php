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
     * Master/lookup table (sama seperti roles) - bukan data transaksional,
     * jadi seed langsung di migration, bukan lewat DatabaseSeeder. Kode
     * permission diambil dari matriks RBAC di CLAUDE.md.
     */
    public function up(): void
    {
        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->timestamps();
        });

        $now = now();

        DB::table('permissions')->insert([
            ['code' => 'manage_users', 'name' => 'Manage Users', 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'manage_roles', 'name' => 'Manage Roles', 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'manage_master_data', 'name' => 'Manage Master Data', 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'system_setting', 'name' => 'System Setting', 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'create_lop', 'name' => 'Create LOP', 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'assign_lop', 'name' => 'Assign LOP', 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'approve_evidence', 'name' => 'Approve Evidence', 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'view_assigned_lop', 'name' => 'View Assigned LOP', 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'pickup_lop', 'name' => 'Pickup LOP', 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'upload_evidence', 'name' => 'Upload Evidence', 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'survey', 'name' => 'Survey', 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'view_dashboard', 'name' => 'View Dashboard', 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'monitoring_progress', 'name' => 'Monitoring Progress', 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'reporting', 'name' => 'Reporting', 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'review_evidence', 'name' => 'Review Evidence', 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('permissions');
    }
};
