<?php

namespace Database\Seeders;

use App\Models\Branch;
use Illuminate\Database\Seeder;

class BranchSeeder extends Seeder
{
    public function run(): void
    {
        $branches = [
            'REGION JATIM' => [
                'SDA' => 'SIDOARJO',
                'SBY' => 'SURABAYA',
                'MDN' => 'MADIUN',
                'JBR' => 'JEMBER',
                'LMG' => 'LAMONGAN',
                'MLG' => 'MALANG',
            ],
            'REGION JATENG DIY' => [
                'YGY' => 'YOGYAKARTA',
                'SMG' => 'SEMARANG',
                'PWT' => 'PURWOKERTO',
                'PKL' => 'PEKALONGAN',
                'SKA' => 'SURAKARTA',
                'MGL' => 'MAGELANG',
            ],
            'REGION BALNUS' => [
                'DPS' => 'DENPASAR',
                'KPG' => 'KUPANG',
                'MTR' => 'MATARAM',
                'FLS' => 'FLORES',
            ],
        ];

        foreach ($branches as $region => $items) {
            foreach ($items as $code => $name) {
                Branch::updateOrCreate(
                    ['code' => $code],
                    ['name' => $name, 'region' => $region],
                );
            }
        }
    }
}
