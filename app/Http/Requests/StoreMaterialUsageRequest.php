<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Rekap qty material aktual yang terpakai per designator (Step 5).
 * qty_actual wajib untuk SETIAP item reservasi dan tidak boleh melebihi
 * qty yang direservasi.
 */
class StoreMaterialUsageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('uploadEvidence', $this->route('qe_lop')) ?? false;
    }

    public function rules(): array
    {
        return [
            'usage' => ['required', 'array', 'min:1', 'max:100'],
            'usage.*.designator_id' => ['required', 'integer', 'distinct'],
            'usage.*.qty_actual' => ['required', 'numeric', 'gte:0', 'max:999999999999.999'],
        ];
    }

    public function after(): array
    {
        return [function ($validator) {
            $lop = $this->route('qe_lop');
            $reserved = $lop?->materialReservation?->items
                ->mapWithKeys(fn ($item) => [(int) $item->designator_id => (float) $item->qty]) ?? collect();

            foreach ((array) $this->input('usage', []) as $i => $row) {
                $designatorId = (int) ($row['designator_id'] ?? 0);
                $qtyActual = (float) ($row['qty_actual'] ?? 0);

                if (! $reserved->has($designatorId)) {
                    $validator->errors()->add("usage.{$i}.designator_id", 'Designator ini tidak ada di reservasi material.');

                    continue;
                }

                if ($qtyActual > $reserved->get($designatorId)) {
                    $validator->errors()->add(
                        "usage.{$i}.qty_actual",
                        'Qty terpakai tidak boleh melebihi qty reservasi ('.rtrim(rtrim(number_format($reserved->get($designatorId), 3, '.', ''), '0'), '.').').',
                    );
                }
            }

            if ($reserved->isNotEmpty()) {
                $submitted = collect($this->input('usage', []))->pluck('designator_id')->map(fn ($id) => (int) $id);

                if ($reserved->keys()->diff($submitted)->isNotEmpty()) {
                    $validator->errors()->add('usage', 'Qty terpakai wajib diisi untuk semua material yang direservasi.');
                }
            }
        }];
    }
}
