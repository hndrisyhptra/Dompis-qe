<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('regions', function (Blueprint $table) {
            $table->foreignId('area_id')
                ->nullable()
                ->after('code')
                ->constrained('areas', 'id_area')
                ->nullOnDelete()
                ->comment('Region masuk ke Area mana (saat ini semua ke Area 3)');
        });
    }

    public function down(): void
    {
        Schema::table('regions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('area_id');
        });
    }
};
