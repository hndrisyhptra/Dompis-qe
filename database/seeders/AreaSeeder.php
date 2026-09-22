<?php

namespace Database\Seeders;

use App\Models\Area;
use App\Models\Region;
use Illuminate\Database\Seeder;

class AreaSeeder extends Seeder
{
    public function run(): void
    {
        $area = Area::updateOrCreate(
            ['code' => '3'],
            ['name' => 'Area 3', 'description' => 'Area 3 — mencakup Region JATIM, JATENG DIY, BALNUS (BAL I NUSRA)', 'is_active' => true]
        );

        // Semua region existing masuk ke Area 3
        Region::query()->update(['area_id' => $area->id_area]);

        $this->command->info("Area seeded: {$area->code} — {$area->name} (regions linked: ".Region::where('area_id', $area->id_area)->count().")");
    }
}
