<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Designator;
use App\Models\DesignatorType;
use App\Models\Package;
use App\Models\User;
use App\Services\DesignatorImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class DesignatorCsvImportTest extends TestCase
{
    use RefreshDatabase;

    private function csvFile(string $content, string $name = 'import.csv'): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'csv');
        file_put_contents($path, $content);

        return new UploadedFile($path, $name, 'text/csv', null, true);
    }

    public function test_import_designators_succeeds_and_is_idempotent_on_reupload(): void
    {
        $actor = User::factory()->role(UserRole::SUPER_ADMIN->value)->create();

        $csv = "code,item_name,unit,type\nM-0001,Kabel FO,meter,material\nJ-0001,Instalasi,unit,jasa\n";

        $service = app(DesignatorImportService::class);
        $result = $service->importDesignators($this->csvFile($csv), $actor);

        $this->assertSame(2, $result['imported']);
        $this->assertEmpty($result['errors']);
        $materialId = DesignatorType::where('code', 'MATERIAL')->value('id_designator_type');
        $jasaId = DesignatorType::where('code', 'JASA')->value('id_designator_type');
        $this->assertDatabaseHas('designators', ['code' => 'M-0001', 'designator_type_id' => $materialId]);
        $this->assertDatabaseHas('designators', ['code' => 'J-0001', 'designator_type_id' => $jasaId]);

        // Re-import file yang sama - tidak boleh duplikat / error unique.
        $result2 = $service->importDesignators($this->csvFile($csv), $actor);
        $this->assertSame(2, $result2['imported']);
        $this->assertSame(2, Designator::count());
    }

    public function test_import_designators_with_one_bad_row_commits_nothing(): void
    {
        $actor = User::factory()->role(UserRole::SUPER_ADMIN->value)->create();

        $csv = "code,item_name,unit,type\nM-0001,Kabel FO,meter,material\nM-0002,Closure,pcs,tidak_valid\n";

        $result = app(DesignatorImportService::class)->importDesignators($this->csvFile($csv), $actor);

        $this->assertSame(0, $result['imported']);
        $this->assertNotEmpty($result['errors']);
        $this->assertDatabaseMissing('designators', ['code' => 'M-0001']);
        $this->assertDatabaseMissing('designators', ['code' => 'M-0002']);
    }

    public function test_import_prices_resolves_by_code_and_rejects_unknown_codes(): void
    {
        $actor = User::factory()->role(UserRole::SUPER_ADMIN->value)->create();
        Designator::create(['code' => 'M-0001', 'item_name' => 'Kabel FO', 'unit' => 'meter']);
        Package::create(['code' => 'PKT-01', 'name' => 'Paket A']);

        $service = app(DesignatorImportService::class);

        $badCsv = "designator_code,package_code,price\nM-9999,PKT-01,10000\n";
        $badResult = $service->importPrices($this->csvFile($badCsv), $actor);
        $this->assertSame(0, $badResult['imported']);
        $this->assertNotEmpty($badResult['errors']);

        $goodCsv = "designator_code,package_code,price\nM-0001,PKT-01,25000\n";
        $goodResult = $service->importPrices($this->csvFile($goodCsv), $actor);
        $this->assertSame(1, $goodResult['imported']);
        $this->assertDatabaseHas('designator_package_prices', ['price' => 25000.00]);
    }
}
