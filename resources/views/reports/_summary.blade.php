{{-- Kartu ringkasan grand-total. Var: $report, $data (payload), $priced. --}}
@php
    $g = $data['grand'];
    $tiles = [
        ['label' => 'Jumlah LOP', 'value' => number_format($g['lop_count'] ?? 0)],
        ['label' => $data['mode'] === 'rekap' ? 'Jumlah Designator' : 'Designator', 'value' => number_format($g['designator_count'] ?? 0)],
        ['label' => 'Total Qty Actual', 'value' => (float) $g['qty_actual']],
    ];
    if ($report === 'sisa') {
        $tiles[] = ['label' => 'Total Sisa Material', 'value' => (float) $g['sisa'], 'accent' => ((float) $g['sisa'] > 0)];
    }
    if ($priced) {
        $v = $report === 'boq' ? $g['total_actual'] : $g['nilai_sisa'];
        $tiles[] = ['label' => $report === 'boq' ? 'Total Nilai Actual' : 'Total Nilai Sisa', 'value' => 'Rp ' . number_format($v ?? 0, 0, ',', '.'), 'money' => true];
    }

    $gridCols = match (count($tiles)) {
        3 => 'lg:grid-cols-3',
        5 => 'lg:grid-cols-5',
        default => 'lg:grid-cols-4',
    };
@endphp

<div class="grid gap-3 sm:grid-cols-2 {{ $gridCols }}">
    @foreach ($tiles as $t)
        <div class="rounded-2xl border p-4 {{ ($t['accent'] ?? false) ? 'border-amber-200 bg-amber-50/60 dark:border-amber-900/60 dark:bg-amber-950/30' : 'border-ink-100 bg-white dark:border-ink-800 dark:bg-ink-900' }}">
            <p class="text-[11px] font-bold uppercase tracking-wider text-ink-400">{{ $t['label'] }}</p>
            <p class="mt-1 text-xl font-extrabold tabular-nums {{ ($t['accent'] ?? false) ? 'text-amber-700 dark:text-amber-300' : 'text-ink-900 dark:text-white' }} {{ ($t['money'] ?? false) ? '!text-lg' : '' }}">{{ $t['value'] }}</p>
        </div>
    @endforeach
</div>

@if ($priced && ($g['price_missing_count'] ?? 0) > 0)
    <p class="mt-2 text-xs text-amber-600 dark:text-amber-400">
        {{ $g['price_missing_count'] }} designator belum ada harga di paket terpilih — tidak dihitung ke total nilai.
    </p>
@endif
