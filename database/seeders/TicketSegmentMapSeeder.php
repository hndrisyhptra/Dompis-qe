<?php

namespace Database\Seeders;

use App\Models\TicketSegmentMap;
use Illuminate\Database\Seeder;

class TicketSegmentMapSeeder extends Seeder
{
    /**
     * Pemetaan awal jenis_tiket_2 -> segment. Hanya nilai GAMAS* yang punya
     * padanan jelas ke enum jaringan; sisanya sengaja tidak dipetakan supaya
     * SUPER_ADMIN menambah sendiri lewat menu Master Data sesuai kebutuhan.
     * updateOrCreate agar aman dijalankan berulang.
     */
    public function run(): void
    {
        $maps = [
            'GAMAS FEEDER' => 'feeder',
            'GAMAS DISTRIBUSI' => 'distribusi',
            'GAMAS ODP' => 'odp',
            'GAMAS GPON' => 'gpon',
        ];

        foreach ($maps as $source => $segment) {
            TicketSegmentMap::updateOrCreate(
                ['source_value' => $source],
                ['segment' => $segment],
            );
        }
    }
}
