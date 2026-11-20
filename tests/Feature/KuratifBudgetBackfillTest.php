<?php

namespace Tests\Feature;

use App\Models\QeLop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KuratifBudgetBackfillTest extends TestCase
{
    use RefreshDatabase;

    public function test_backfill_is_dry_run_by_default_then_updates_only_kuratif_opex_idempotently(): void
    {
        $actor = User::factory()->role('SUPER_ADMIN')->create();
        $kuratif = $this->lop('KURATIF-OPEX', 'kuratif', 'OPEX', $actor);
        $archivedKuratif = $this->lop('KURATIF-OPEX-ARCHIVED', 'kuratif', 'OPEX', $actor);
        $archivedKuratif->delete();
        $alreadyCapex = $this->lop('KURATIF-CAPEX', 'kuratif', 'CAPEX', $actor);
        $otherProgram = $this->lop('RECOVERY-OPEX', 'recovery', 'OPEX', $actor);

        $this->artisan('dompis:backfill-kuratif-budget')
            ->expectsOutput('LOP QE Kuratif beranggaran OPEX: 2')
            ->expectsOutput('DRY RUN — tidak ada data yang diubah. Gunakan --apply untuk menerapkan backfill.')
            ->assertSuccessful();

        $this->assertSame('OPEX', $kuratif->fresh()->budget_type->value);
        $this->assertSame('OPEX', QeLop::withTrashed()->findOrFail($archivedKuratif->getKey())->budget_type->value);

        $this->artisan('dompis:backfill-kuratif-budget', ['--apply' => true])
            ->expectsOutput('LOP QE Kuratif beranggaran OPEX: 2')
            ->expectsOutput('Backfill selesai: 2 LOP diubah dari OPEX menjadi CAPEX.')
            ->assertSuccessful();

        $this->assertSame('CAPEX', $kuratif->fresh()->budget_type->value);
        $this->assertSame('CAPEX', QeLop::withTrashed()->findOrFail($archivedKuratif->getKey())->budget_type->value);
        $this->assertSame('CAPEX', $alreadyCapex->fresh()->budget_type->value);
        $this->assertSame('OPEX', $otherProgram->fresh()->budget_type->value);

        $this->artisan('dompis:backfill-kuratif-budget', ['--apply' => true])
            ->expectsOutput('LOP QE Kuratif beranggaran OPEX: 0')
            ->expectsOutput('Backfill selesai: 0 LOP diubah dari OPEX menjadi CAPEX.')
            ->assertSuccessful();
    }

    private function lop(string $incident, string $program, string $budget, User $actor): QeLop
    {
        return QeLop::create([
            'incident' => $incident,
            'nama_lop' => $incident,
            'program_type' => $program,
            'budget_type' => $budget,
            'status_lop' => 'draft',
            'created_by' => $actor->getKey(),
        ]);
    }
}
