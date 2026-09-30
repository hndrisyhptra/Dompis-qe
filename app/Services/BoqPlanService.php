<?php

namespace App\Services;

use App\Enums\LopStatus;
use App\Models\Designator;
use App\Models\QeBoq;
use App\Models\QeBoqPlan;
use App\Models\QeBoqPlanItem;
use App\Models\QeLop;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BoqPlanService
{
    public function __construct(
        protected BoqService $boqService
    ) {}

    /**
     * Simpan BOQ Plan dari input manual atau import.
     * 
     * @param array<int, array{designator_id:int, qty:float, unit_price:float}> $items
     */
    public function save(QeLop $lop, array $items, User $actor): QeBoqPlan
    {
        if ($lop->status_lop === LopStatus::COMPLETED) {
            throw ValidationException::withMessages([
                'boq_plan' => 'BOQ Plan tidak dapat diubah setelah LOP selesai.',
            ]);
        }

        return DB::transaction(function () use ($lop, $items, $actor) {
            $plan = QeBoqPlan::firstOrNew(['qe_lop_id' => $lop->id_qe_lops]);
            
            $plan->fill([
                'package_id' => $lop->package_id,
                'updated_by' => $actor->id_user,
            ]);

            if (!$plan->exists) {
                $plan->created_by = $actor->id_user;
            }

            $plan->save();
            $plan->items()->delete();

            foreach ($items as $item) {
                $designator = Designator::with('type')->find($item['designator_id']);
                if (!$designator) continue;

                $plan->items()->create([
                    'designator_id' => $designator->id_designator,
                    'designator_code' => $designator->code,
                    'item_name' => $designator->item_name,
                    'unit' => $designator->unit,
                    'type' => strtoupper($designator->type->code),
                    'qty' => $item['qty'],
                    'unit_price' => $item['unit_price'],
                    'total_price' => $item['qty'] * $item['unit_price'],
                ]);
            }

            $lop->update(['boq_plan_id' => $plan->id_plan]);

            return $plan->load('items');
        });
    }

    /**
     * Konversi BOQ Plan menjadi BOQ Actual dan Material Reservation.
     * Dipanggil saat LOP di-pickup.
     */
    public function convertToActual(QeLop $lop): void
    {
        $plan = $lop->boqPlan;
        if (!$plan) return;

        DB::transaction(function () use ($lop, $plan) {
            // 1. Generate BOQ Actual (QeBoq)
            $boqItems = $plan->items->map(fn ($item) => [
                'designator_id' => $item->designator_id,
                'qty' => $item->qty,
                'unit_price' => $item->unit_price,
            ])->toArray();

            $actor = $plan->creator ?: User::where('username', 'system')->first() ?: $lop->creator;
            
            // simpan BOQ Actual dan otomatis sync ke material reservation di dalamnya
            $this->boqService->save($lop, $boqItems, $plan->package, $actor, 'plan_conversion');
        });
    }

    /**
     * Ambil data perbandingan Plan vs Actual.
     */
    public function getComparison(QeLop $lop): array
    {
        $plan = $lop->boqPlan()->with('items')->first();
        $actual = $lop->boq()->with('items')->first();
        $reservation = $lop->materialReservation()->with('items.designator')->first();

        return [
            'plan' => $plan,
            'actual' => $actual,
            'reservation' => $reservation,
        ];
    }
}
