<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('qe_lops', function (Blueprint $table) {
            $table->enum('status_project', ['usulan', 'on_going'])->nullable()->after('program_type')->index();
            $table->foreignId('branch_id')->nullable()->after('branch')
                ->constrained('branches', 'id_branch')->nullOnDelete();
            $table->foreignId('service_area_id')->nullable()->after('branch_id')
                ->constrained('service_areas', 'id_service_area')->nullOnDelete();
            $table->index(['program_type', 'status_project'], 'qe_lops_program_project_status_idx');
            $table->index(['branch_id', 'service_area_id'], 'qe_lops_location_fk_idx');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->enum('admin_scope_type', ['area', 'region', 'branch', 'service_area'])->nullable()->after('branch_id')->index();
            $table->foreignId('area_id')->nullable()->after('admin_scope_type')
                ->constrained('areas', 'id_area')->nullOnDelete();
            $table->foreignId('region_id')->nullable()->after('area_id')
                ->constrained('regions', 'id_region')->nullOnDelete();
            $table->foreignId('service_area_id')->nullable()->after('region_id')
                ->constrained('service_areas', 'id_service_area')->nullOnDelete();
        });

        Schema::create('user_service_areas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users', 'id_user')->cascadeOnDelete();
            $table->foreignId('service_area_id')->constrained('service_areas', 'id_service_area')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'service_area_id'], 'user_service_area_unique');
        });

        DB::table('qe_lops')->orderBy('id_qe_lops')->chunkById(200, function ($lops): void {
            foreach ($lops as $lop) {
                $serviceArea = filled($lop->sto)
                    ? DB::table('service_areas')->where('workzone', $lop->sto)->first()
                    : null;
                $branchId = $serviceArea?->branch_id;
                if ($branchId === null && filled($lop->branch)) {
                    $branchId = DB::table('branches')->where('name', $lop->branch)->value('id_branch');
                }

                $statusProject = null;
                if (in_array($lop->program_type, ['preventive', 'relok_utilitas'], true)) {
                    $hasActiveAssignment = DB::table('qe_lop_assignments')
                        ->where('qe_lop_id', $lop->id_qe_lops)
                        ->where('status', 'active')
                        ->exists();
                    $statusProject = $lop->status_lop === 'draft' && ! $hasActiveAssignment
                        ? 'usulan'
                        : 'on_going';
                }

                DB::table('qe_lops')->where('id_qe_lops', $lop->id_qe_lops)->update([
                    'branch_id' => $branchId,
                    'service_area_id' => $serviceArea?->id_service_area,
                    'status_project' => $statusProject,
                ]);
            }
        }, 'id_qe_lops');

        $adminRoleId = DB::table('roles')->where('code', 'ADMIN')->value('id');
        DB::table('users')->whereNotNull('branch_id')->orderBy('id_user')->chunkById(200, function ($users) use ($adminRoleId): void {
            foreach ($users as $user) {
                $branch = DB::table('branches')->where('id_branch', $user->branch_id)->first();
                $region = $branch?->region_id
                    ? DB::table('regions')->where('id_region', $branch->region_id)->first()
                    : null;

                DB::table('users')->where('id_user', $user->id_user)->update([
                    'admin_scope_type' => (int) $user->role_id === (int) $adminRoleId ? 'branch' : null,
                    'region_id' => $region?->id_region,
                    'area_id' => $region?->area_id,
                ]);
            }
        }, 'id_user');
    }

    public function down(): void
    {
        Schema::dropIfExists('user_service_areas');

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('service_area_id');
            $table->dropConstrainedForeignId('region_id');
            $table->dropConstrainedForeignId('area_id');
            $table->dropColumn('admin_scope_type');
        });

        Schema::table('qe_lops', function (Blueprint $table) {
            $table->dropIndex('qe_lops_program_project_status_idx');
            $table->dropIndex('qe_lops_location_fk_idx');
            $table->dropConstrainedForeignId('service_area_id');
            $table->dropConstrainedForeignId('branch_id');
            $table->dropColumn('status_project');
        });
    }
};
