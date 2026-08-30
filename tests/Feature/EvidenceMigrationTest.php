<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class EvidenceMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_qe_evidences_table_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('qe_evidences'));
        $this->assertTrue(Schema::hasColumns('qe_evidences', [
            'id_evidence', 'qe_lop_id', 'designator_id', 'uploaded_by',
            'step', 'type', 'file_path', 'metadata', 'note',
            'status', 'review_note', 'reviewed_by', 'reviewed_at', 'deleted_at',
        ]));
    }
}
