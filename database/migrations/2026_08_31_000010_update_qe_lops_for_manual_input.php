<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('qe_lops', function (Blueprint $table) {
            $table->renameColumn('kode_lop', 'incident');
        });

        Schema::table('qe_lops', function (Blueprint $table) {
            $table->string('area', 20)->default('3');
            $table->string('segment', 30)->nullable();
            $table->string('budget_type', 10)->nullable();
            $table->text('job_description')->nullable();
            $table->string('ihld_id', 100)->nullable();
            $table->index(['area', 'branch'], 'qe_lops_area_branch_idx');
            $table->index('segment', 'qe_lops_segment_idx');
        });
    }

    public function down(): void
    {
        Schema::table('qe_lops', function (Blueprint $table) {
            $table->dropIndex('qe_lops_area_branch_idx');
            $table->dropIndex('qe_lops_segment_idx');
            $table->dropColumn(['area', 'segment', 'budget_type', 'job_description', 'ihld_id']);
        });

        Schema::table('qe_lops', function (Blueprint $table) {
            $table->renameColumn('incident', 'kode_lop');
        });
    }
};
