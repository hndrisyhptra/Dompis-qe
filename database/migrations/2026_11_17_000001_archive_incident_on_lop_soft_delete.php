<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('qe_lops', function (Blueprint $table) {
            $table->string('deleted_incident')->nullable()->after('incident');
            $table->string('incident')->nullable()->change();
        });

        DB::table('qe_lops')
            ->whereNotNull('deleted_at')
            ->whereNotNull('incident')
            ->update([
                'deleted_incident' => DB::raw('incident'),
                'incident' => null,
            ]);
    }

    public function down(): void
    {
        $duplicateArchivedIncidents = DB::table('qe_lops')
            ->select('deleted_incident')
            ->whereNotNull('deleted_incident')
            ->groupBy('deleted_incident')
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        $incidentAlreadyReused = DB::table('qe_lops as archived')
            ->join('qe_lops as current', function ($join) {
                $join->on('current.incident', '=', 'archived.deleted_incident')
                    ->whereColumn('current.id_qe_lops', '!=', 'archived.id_qe_lops');
            })
            ->whereNotNull('archived.deleted_incident')
            ->exists();

        $activeIncidentMissing = DB::table('qe_lops')
            ->whereNull('deleted_at')
            ->whereNull('incident')
            ->exists();

        if ($duplicateArchivedIncidents || $incidentAlreadyReused || $activeIncidentMissing) {
            throw new RuntimeException(
                'Migration tidak dapat di-rollback: ada incident arsip yang sudah digunakan ulang atau LOP aktif tanpa incident.'
            );
        }

        DB::table('qe_lops')
            ->whereNotNull('deleted_incident')
            ->update([
                'incident' => DB::raw('deleted_incident'),
                'deleted_incident' => null,
            ]);

        Schema::table('qe_lops', function (Blueprint $table) {
            $table->dropColumn('deleted_incident');
            $table->string('incident')->nullable(false)->change();
        });
    }
};
