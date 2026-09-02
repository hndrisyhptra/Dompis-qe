<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('qe_evidences', function (Blueprint $table) {
            $table->enum('category', ['pre', 'material_arrival', 'before', 'progress', 'after'])
                ->nullable()->after('type');
            $table->index(['qe_lop_id', 'category']);
        });
    }

    public function down(): void
    {
        Schema::table('qe_evidences', function (Blueprint $table) {
            $table->dropIndex(['qe_lop_id', 'category']);
            $table->dropColumn('category');
        });
    }
};
