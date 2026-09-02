<?php

namespace Database\Seeders;

use App\Models\DesignatorType;
use Illuminate\Database\Seeder;

class DesignatorTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            'MATERIAL' => 'Material',
            'JASA' => 'Jasa',
            'INSTALASI' => 'Instalasi',
            'PENGUKURAN' => 'Pengukuran',
            'DISMANTLE' => 'Dismantle',
        ];

        foreach ($types as $code => $name) {
            DesignatorType::updateOrCreate(['code' => $code], ['name' => $name, 'is_active' => true]);
        }
    }
}
