<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Branch dijadikan master data yang bisa dikelola admin: tambah relasi
     * ke tabel regions (region_id FK), flag is_active, audit created_by/
     * updated_by, dan soft delete.
     *
     * Kolom string `branches.region` SENGAJA DIPERTAHANKAN sebagai cache
     * denormalisasi (dibaca ~20 tempat di dashboard/evidence-approval).
     * region_id jadi source of truth; string region dijaga sinkron oleh
     * Branch/Region CRUD. Drop kolom string + refactor agregasi dashboard
     * adalah follow-up terpisah.
     */
    public function up(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->foreignId('region_id')->nullable()->after('region')
                ->constrained('regions', 'id_region')->nullOnDelete();
            $table->boolean('is_active')->default(true)->after('region_id')->index();
            $table->foreignId('created_by')->nullable()
                ->constrained('users', 'id_user')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()
                ->constrained('users', 'id_user')->nullOnDelete();
            $table->softDeletes();
        });

        // Backfill region_id dari string region yang sudah ada.
        DB::table('branches')->whereNotNull('region')->get()->each(function ($branch) {
            $regionId = DB::table('regions')->where('name', $branch->region)->value('id_region');

            if ($regionId) {
                DB::table('branches')->where('id_branch', $branch->id_branch)->update(['region_id' => $regionId]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->dropConstrainedForeignId('region_id');
            $table->dropConstrainedForeignId('created_by');
            $table->dropConstrainedForeignId('updated_by');
            $table->dropColumn(['is_active', 'deleted_at']);
        });
    }
};
