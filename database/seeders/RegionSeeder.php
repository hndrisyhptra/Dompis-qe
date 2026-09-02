<?php

namespace Database\Seeders;

use App\Models\Region;
use Illuminate\Database\Seeder;

class RegionSeeder extends Seeder
{
    public function run(): void
    {
        $regions = [
            'JATIM' => 'REGION JATIM',
            'JATENGDIY' => 'REGION JATENG DIY',
            'BALNUS' => 'REGION BALNUS',
        ];

        foreach ($regions as $code => $name) {
            Region::updateOrCreate(['code' => $code], ['name' => $name, 'is_active' => true]);
        }
    }
}
