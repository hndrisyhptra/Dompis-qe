<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('qe_import_batches', function (Blueprint $table) {
            $table->char('file_hash', 64)->nullable()->unique()->after('file_path');
            $table->index('file_hash');
        });
    }

    public function down(): void
    {
        Schema::table('qe_import_batches', function (Blueprint $table) {
            $table->dropUnique(['file_hash']);
            $table->dropIndex(['file_hash']);
            $table->dropColumn('file_hash');
        });
    }
};