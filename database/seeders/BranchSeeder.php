<?php

namespace Database\Seeders;

use App\Models\Branch;
<<<<<<< HEAD
use App\Models\Region;
=======
>>>>>>> a86f15e45cd25dd3304798754e1cd5bfc0ffbc8c
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
<<<<<<< HEAD
            $regionId = Region::where('name', $region)->value('id_region');

            foreach ($items as $code => $name) {
                Branch::updateOrCreate(
                    ['code' => $code],
                    ['name' => $name, 'region' => $region, 'region_id' => $regionId, 'is_active' => true],
=======
            foreach ($items as $code => $name) {
                Branch::updateOrCreate(
                    ['code' => $code],
                    ['name' => $name, 'region' => $region],
>>>>>>> a86f15e45cd25dd3304798754e1cd5bfc0ffbc8c
                );
            }
        }
    }
}
