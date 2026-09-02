<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ganti kolom enum `designators.type` (material/jasa) dengan FK
     * designator_type_id -> designator_types, plus designator_category_id
     * (opsional) -> designator_categories. Backfill: semua baris lama
     * dipetakan ke tipe dengan code = UPPER(type).
     */
    public function up(): void
    {
        Schema::table('designators', function (Blueprint $table) {
            $table->foreignId('designator_type_id')->nullable()->after('unit')
                ->constrained('designator_types', 'id_designator_type')->nullOnDelete();
            $table->foreignId('designator_category_id')->nullable()->after('designator_type_id')
                ->constrained('designator_categories', 'id_designator_category')->nullOnDelete();
        });

        $typeIds = DB::table('designator_types')->pluck('id_designator_type', 'code');

        foreach (['material' => 'MATERIAL', 'jasa' => 'JASA'] as $old => $code) {
            if (isset($typeIds[$code])) {
                DB::table('designators')->where('type', $old)->update(['designator_type_id' => $typeIds[$code]]);
            }
        }

        Schema::table('designators', function (Blueprint $table) {
            $table->dropIndex(['type']);
        });

        Schema::table('designators', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }

    public function down(): void
    {
        Schema::table('designators', function (Blueprint $table) {
            $table->enum('type', ['material', 'jasa'])->nullable()->after('unit');
        });

        $codes = DB::table('designator_types')->pluck('code', 'id_designator_type');

        DB::table('designators')->whereNotNull('designator_type_id')->get()->each(function ($d) use ($codes) {
            $legacy = strtolower((string) ($codes[$d->designator_type_id] ?? ''));
            if (in_array($legacy, ['material', 'jasa'], true)) {
                DB::table('designators')->where('id_designator', $d->id_designator)->update(['type' => $legacy]);
            }
        });

        Schema::table('designators', function (Blueprint $table) {
            $table->dropConstrainedForeignId('designator_type_id');
            $table->dropConstrainedForeignId('designator_category_id');
        });
    }
};
