<?php

namespace Tests\Concerns;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;

trait InteractsWithIncidentTickets
{
    protected function fakeIncidentTickets(array $types): void
    {
        config()->set('database.connections.mysql_dompis', [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => false,
        ]);
        DB::purge('mysql_dompis');
        $connection = DB::connection('mysql_dompis');
        $connection->getSchemaBuilder()->create('ticket', function (Blueprint $table) {
            $table->increments('id_ticket');
            $table->string('incident');
            $table->string('jenis_tiket_2')->nullable();
            $table->string('workzone')->nullable();
            $table->string('reported_date')->nullable();
            $table->dateTime('reported_date_dt')->nullable();
        });
        foreach ($types as $incident => $type) {
            $connection->table('ticket')->insert(['incident' => $incident, 'jenis_tiket_2' => $type]);
        }
    }
}
