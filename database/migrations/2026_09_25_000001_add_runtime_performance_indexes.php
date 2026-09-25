<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('qe_lops', function (Blueprint $table) {
            $table->index(
                ['branch', 'program_type', 'status_lop'],
                'qe_lops_dashboard_matrix_idx'
            );
        });

        Schema::table('qe_lop_assignments', function (Blueprint $table) {
            $table->index(
                ['assigned_by', 'status', 'qe_lop_id'],
                'qe_assignments_reviewer_scope_idx'
            );
        });

        Schema::table('qe_evidences', function (Blueprint $table) {
            $table->index(
                ['qe_lop_id', 'status'],
                'qe_evidences_lop_status_idx'
            );
        });

        Schema::table('qe_import_batches', function (Blueprint $table) {
            $table->index(
                ['type', 'created_at'],
                'qe_import_batches_history_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::table('qe_import_batches', function (Blueprint $table) {
            $table->dropIndex('qe_import_batches_history_idx');
        });

        Schema::table('qe_evidences', function (Blueprint $table) {
            $table->dropIndex('qe_evidences_lop_status_idx');
        });

        Schema::table('qe_lop_assignments', function (Blueprint $table) {
            $table->dropIndex('qe_assignments_reviewer_scope_idx');
        });

        Schema::table('qe_lops', function (Blueprint $table) {
            $table->dropIndex('qe_lops_dashboard_matrix_idx');
        });
    }
};
