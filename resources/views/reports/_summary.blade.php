{{-- Kartu ringkasan grand-total. Var: $report, $data (payload), $priced. --}}
@php
    $g = $data['grand'];
    $num = fn ($v) => rtrim(rtrim(number_format((float) $v, 3, ',', '.'), '0'), ',');

    $rencana = (float) ($g['qty'] ?? 0);
    $actual = (float) ($g['qty_actual'] ?? 0);
    $sisa = (float) ($g['sisa'] ?? 0);
    $actualPct = $rencana > 0 ? round($actual / $rencana * 100) : 0;
    $sisaPct = $rencana > 0 ? round($sisa / $rencana * 100) : 0;

    $tiles = [
        [
            'label' => 'Cakupan',
            'value' => number_format($g['lop_count'] ?? 0).' LOP',
            'sub' => number_format($g['designator_count'] ?? 0).' designator · '.number_format($g['line_count'] ?? 0).' baris material',
        ],
        [
            'label' => 'Qty Rencana',
            'value' => $num($rencana),
            'sub' => 'total kebutuhan pada reservasi',
        ],
        [
            'label' => 'Qty Actual',
            'value' => $num($actual),
            'sub' => $actualPct.'% dari rencana terpakai',
        ],
    ];

    if ($report === 'sisa') {
        $tiles[] = [
            'label' => 'Sisa Material',
            'value' => $num($sisa),
            'sub' => $sisaPct.'% dari rencana · '.number_format($g['sisa_items_count'] ?? 0).' item bersisa',
            'accent' => $sisa > 0,
        ];
    }

    if ($priced) {
        $v = $report === 'boq' ? ($g['total_actual'] ?? 0) : ($g['nilai_sisa'] ?? 0);
        $tiles[] = [
            'label' => $report === 'boq' ? 'Nilai Material Terpakai' : 'Nilai Material Sisa',
            'value' => 'Rp '.number_format($v ?? 0, 0, ',', '.'),
            'sub' => $report === 'boq' ? 'qty actual × harga KHS' : 'sisa × harga KHS',
            'money' => true,
        ];
    }

    $gridCols = match (count($tiles)) {
        3 => 'lg:grid-cols-3',
        5 => 'lg:grid-cols-5',
        default => 'lg:grid-cols-4',
    };

    $notes = [];
    if (($g['not_recapped_count'] ?? 0) > 0) {
        $notes[] = number_format($g['not_recapped_count']).' baris belum diisi qty actual oleh teknisi — belum dihitung ke total.';
    }
    if ($priced && ($g['price_missing_count'] ?? 0) > 0) {
        $notes[] = number_format($g['price_missing_count']).' designator belum ada harga di paket terpilih — tidak masuk total nilai.';
    }
@endphp

<div class="grid gap-3 sm:grid-cols-2 {{ $gridCols }}">
    @foreach ($tiles as $t)
        <div class="rounded-2xl border p-4 {{ ($t['accent'] ?? false) ? 'border-amber-200 bg-amber-50/60 dark:border-amber-900/60 dark:bg-amber-950/30' : 'border-ink-100 bg-white dark:border-ink-800 dark:bg-ink-900' }}">
            <p class="text-[11px] font-bold uppercase tracking-wider text-ink-400">{{ $t['label'] }}</p>
            <p class="mt-1 font-extrabold tabular-nums {{ ($t['money'] ?? false) ? 'text-lg' : 'text-xl' }} {{ ($t['accent'] ?? false) ? 'text-amber-700 dark:text-amber-300' : 'text-ink-900 dark:text-white' }}">{{ $t['value'] }}</p>
            <p class="mt-1 text-[11px] leading-4 text-ink-400">{{ $t['sub'] }}</p>
        </div>
    @endforeach
</div>

@if ($notes)
    <ul class="mt-2 space-y-1 text-xs text-amber-600 dark:text-amber-400">
        @foreach ($notes as $n)
            <li class="flex gap-1.5"><span aria-hidden="true">•</span><span>{{ $n }}</span></li>
        @endforeach
    </ul>
@endif
