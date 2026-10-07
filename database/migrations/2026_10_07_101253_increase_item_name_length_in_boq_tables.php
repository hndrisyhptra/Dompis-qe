<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('qe_boq_items', function (Blueprint $table) {
            $table->text('item_name')->change();
        });

        Schema::table('qe_boq_plan_items', function (Blueprint $table) {
            $table->text('item_name')->change();
        });

        Schema::table('designators', function (Blueprint $table) {
            $table->text('item_name')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('qe_boq_items', function (Blueprint $table) {
            $table->string('item_name', 255)->change();
        });

        Schema::table('qe_boq_plan_items', function (Blueprint $table) {
            $table->string('item_name', 255)->change();
        });

        Schema::table('designators', function (Blueprint $table) {
            $table->string('item_name', 255)->change();
        });
    }
};
