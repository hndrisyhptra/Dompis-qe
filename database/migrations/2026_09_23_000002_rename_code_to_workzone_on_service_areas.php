<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('service_areas', 'code')) {
            return;
        }
        if (Schema::hasColumn('service_areas', 'workzone')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'sqlite') {
            // SQLite: gunakan statement mentah tanpa double-quote ganda (Laravel renameColumn bug di sqlite :memory:)
            \Illuminate\Support\Facades\DB::statement('ALTER TABLE service_areas RENAME COLUMN code TO workzone');
        } else {
            Schema::table('service_areas', function (Blueprint $table) {
                $table->renameColumn('code', 'workzone');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('service_areas', 'workzone')) {
            return;
        }
        if (Schema::hasColumn('service_areas', 'code')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'sqlite') {
            \Illuminate\Support\Facades\DB::statement('ALTER TABLE service_areas RENAME COLUMN workzone TO code');
        } else {
            Schema::table('service_areas', function (Blueprint $table) {
                $table->renameColumn('workzone', 'code');
            });
        }
    }
};
