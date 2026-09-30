<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('qe_boq_plan_items', function (Blueprint $table) {
            $table->id('id_plan_item');
            $table->foreignId('qe_boq_plan_id')->constrained('qe_boq_plans', 'id_plan')->cascadeOnDelete();
            $table->foreignId('designator_id')->constrained('designators', 'id_designator')->restrictOnDelete();
            $table->string('designator_code');
            $table->string('item_name');
            $table->string('unit', 50);
            $table->string('type', 30);
            $table->decimal('qty', 15, 3);
            $table->decimal('unit_price', 18, 2)->default(0);
            $table->decimal('total_price', 18, 2)->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['qe_boq_plan_id', 'type']);
            $table->index(['qe_boq_plan_id', 'designator_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qe_boq_plan_items');
    }
};
