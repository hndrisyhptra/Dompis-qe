<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('designators', function (Blueprint $table) {
            $table->text('item_name')->change();
        });

        Schema::table('qe_boq_items', function (Blueprint $table) {
            $table->text('item_name')->change();
        });
    }

    public function down(): void
    {
        $hasLongDesignator = DB::table('designators')
            ->whereRaw('CHAR_LENGTH(item_name) > 255')
            ->exists();
        $hasLongBoqItem = DB::table('qe_boq_items')
            ->whereRaw('CHAR_LENGTH(item_name) > 255')
            ->exists();

        if ($hasLongDesignator || $hasLongBoqItem) {
            throw new RuntimeException(
                'Migration tidak dapat dibatalkan karena terdapat uraian item lebih dari 255 karakter.'
            );
        }

        Schema::table('designators', function (Blueprint $table) {
            $table->string('item_name')->change();
        });

        Schema::table('qe_boq_items', function (Blueprint $table) {
            $table->string('item_name')->change();
        });
    }
};
