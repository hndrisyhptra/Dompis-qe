<?php

namespace Database\Seeders;

use App\Models\DesignatorCategory;
use Illuminate\Database\Seeder;

class DesignatorCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            'ODP' => 'ODP',
            'ODC' => 'ODC',
            'KABEL' => 'Kabel',
            'MATERIAL' => 'Material',
            'JASA' => 'Jasa',
            'TESTING' => 'Testing',
            'DISMANTLE' => 'Dismantle',
        ];

        foreach ($categories as $code => $name) {
            DesignatorCategory::updateOrCreate(['code' => $code], ['name' => $name, 'is_active' => true]);
        }
    }
}
