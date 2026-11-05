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
        Schema::table('qe_lops', function (Blueprint $table) {
            $table->enum('program_type', ['recovery', 'preventive', 'relok_utilitas', 'relok_tiang', 'kuratif'])
                  ->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('qe_lops', function (Blueprint $table) {
            $table->enum('program_type', ['recovery', 'preventive', 'relok_utilitas', 'relok_tiang'])
                  ->change();
        });
    }
};