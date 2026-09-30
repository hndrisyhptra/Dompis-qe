<?php

namespace App\Http\Controllers;

use App\Models\QeLop;
use Illuminate\Http\JsonResponse;

class BoqPlanController extends Controller
{
    public function show(QeLop $qe_lop): JsonResponse
    {
        $this->authorize('view', $qe_lop);
        $plan = $qe_lop->boqPlan()->with('items.designator')->first();

        if (! $plan) {
            return response()->json(['lines' => []]);
        }

        return response()->json([
            'lines' => $plan->items->map(fn ($item) => [
                'designator_id' => $item->designator_id,
                'designator_code' => $item->designator_code,
                'item_name' => $item->item_name,
                'unit' => $item->unit,
                'type' => $item->type,
                'qty' => $item->qty,
                'unit_price' => $item->unit_price,
                'total_price' => $item->total_price,
            ]),
        ]);
    }
}
