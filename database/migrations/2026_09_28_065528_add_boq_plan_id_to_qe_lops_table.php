<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('qe_lops', function (Blueprint $table) {
            $table->foreignId('boq_plan_id')->nullable()->constrained('qe_boq_plans', 'id_plan')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('qe_lops', function (Blueprint $table) {
            $table->dropForeign(['boq_plan_id']);
            $table->dropColumn('boq_plan_id');
        });
    }
};
