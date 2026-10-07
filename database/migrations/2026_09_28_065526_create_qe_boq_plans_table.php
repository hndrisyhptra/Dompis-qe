<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('qe_boq_plans', function (Blueprint $table) {
            $table->id('id_plan');
            $table->foreignId('qe_lop_id')->constrained('qe_lops', 'id_qe_lops')->cascadeOnDelete();
            $table->foreignId('package_id')->nullable()->constrained('packages', 'id_package')->nullOnDelete();
            $table->text('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users', 'id_user')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users', 'id_user')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique('qe_lop_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qe_boq_plans');
    }
};
