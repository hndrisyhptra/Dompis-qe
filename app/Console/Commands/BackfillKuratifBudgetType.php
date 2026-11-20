<?php

namespace App\Console\Commands;

use App\Enums\LopBudgetType;
use App\Enums\ProgramType;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillKuratifBudgetType extends Command
{
    protected $signature = 'dompis:backfill-kuratif-budget {--apply : Terapkan perubahan; tanpa opsi ini hanya audit read-only}';

    protected $description = 'Backfill anggaran OPEX menjadi CAPEX pada LOP QE Kuratif';

    public function handle(): int
    {
        $query = fn () => DB::table('qe_lops')
            ->where('program_type', ProgramType::KURATIF->value)
            ->where('budget_type', LopBudgetType::OPEX->value);

        $candidates = $query()->count();
        $this->line("LOP QE Kuratif beranggaran OPEX: {$candidates}");

        if (! $this->option('apply')) {
            $this->info('DRY RUN — tidak ada data yang diubah. Gunakan --apply untuk menerapkan backfill.');

            return self::SUCCESS;
        }

        $updated = DB::transaction(fn () => $query()->update([
            'budget_type' => LopBudgetType::CAPEX->value,
        ]));

        $this->info("Backfill selesai: {$updated} LOP diubah dari OPEX menjadi CAPEX.");

        return self::SUCCESS;
    }
}
