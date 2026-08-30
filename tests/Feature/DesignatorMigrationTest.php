<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DesignatorMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_designators_table_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('designators'));
        $this->assertTrue(Schema::hasColumns('designators', [
            'id_designator', 'code', 'item_name', 'unit', 'type',
            'created_by', 'updated_by', 'deleted_at',
        ]));
    }

    public function test_packages_table_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('packages'));
        $this->assertTrue(Schema::hasColumns('packages', [
            'id_package', 'code', 'name', 'description',
            'created_by', 'updated_by', 'deleted_at',
        ]));
    }

    public function test_designator_package_prices_table_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('designator_package_prices'));
        $this->assertTrue(Schema::hasColumns('designator_package_prices', [
            'id_designator_package_price', 'designator_id', 'package_id', 'price',
            'created_by', 'updated_by', 'deleted_at',
        ]));
    }
}
