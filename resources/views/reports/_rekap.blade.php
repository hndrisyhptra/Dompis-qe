{{-- di-include dengan ['report' => …, 'data' => …, 'priced' => …] --}}
@php
    $rows = $data['rows'];
    $grand = $data['grand'];
    $isPaginator = $rows instanceof \Illuminate\Contracts\Pagination\Paginator;
    $th = 'px-4 py-2.5 text-left text-[11px] font-bold uppercase tracking-wider text-ink-500';
    $thNum = 'px-4 py-2.5 text-right text-[11px] font-bold uppercase tracking-wider text-ink-500';
    $td = 'px-4 py-2 text-ink-700 dark:text-ink-200';
    $tdNum = 'px-4 py-2 text-right tabular-nums text-ink-800 dark:text-ink-100';
    $money = fn ($v) => $v === null ? '—' : 'Rp ' . number_format($v, 0, ',', '.');
    $valueKey = $report === 'boq' ? 'total_actual' : 'nilai_sisa';
    $valueLabel = $report === 'boq' ? 'Total Actual' : 'Total Nilai Sisa';
@endphp

<x-table class="!rounded-2xl shadow-sm">
    <thead class="bg-ink-50/80 dark:bg-ink-800">
        <tr>
            <th class="{{ $th }}">#</th>
            <th class="{{ $th }}">Designator</th>
            <th class="{{ $th }}">Uraian</th>
            <th class="{{ $th }}">Satuan</th>
            <th class="{{ $thNum }}">Total Qty Rencana</th>
            <th class="{{ $thNum }}">Total Qty Actual</th>
            <th class="{{ $thNum }}">Total Sisa</th>
            @if ($priced)
                <th class="{{ $thNum }}">Harga KHS</th>
                <th class="{{ $thNum }}">{{ $valueLabel }}</th>
            @endif
            <th class="{{ $thNum }}">Jml LOP</th>
        </tr>
    </thead>
    <tbody class="divide-y divide-ink-100 dark:divide-ink-800">
        @forelse ($rows as $i => $row)
            <tr>
                <td class="{{ $td }}">{{ $i + 1 }}</td>
                <td class="{{ $td }} font-medium">{{ $row['designator_code'] }}</td>
                <td class="{{ $td }} text-ink-500 dark:text-ink-400">{{ $row['designator_name'] }}</td>
                <td class="{{ $td }}">{{ $row['unit'] }}</td>
                <td class="{{ $tdNum }}">{{ (float) $row['qty'] }}</td>
                <td class="{{ $tdNum }}">{{ (float) $row['qty_actual'] }}</td>
                <td class="{{ $tdNum }} font-semibold">{{ (float) $row['sisa'] }}</td>
                @if ($priced)
                    <td class="{{ $tdNum }}">
                        @if ($row['price_missing'])
                            <span class="text-amber-600 dark:text-amber-400">belum diset</span>
                        @else
                            Rp {{ number_format($row['price'], 0, ',', '.') }}
                        @endif
                    </td>
                    <td class="{{ $tdNum }}">{{ $money($row[$valueKey]) }}</td>
                @endif
                <td class="{{ $tdNum }}">{{ $row['lop_count'] }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="99" class="px-6 py-14 text-center text-sm text-ink-400">
                    Belum ada material dari LOP dengan rekap yang sudah disubmit untuk filter ini.
                </td>
            </tr>
        @endforelse

        @if ((is_countable($rows) ? count($rows) : $rows->count()) > 0)
            <tr class="bg-ink-50/70 font-bold dark:bg-ink-800/70">
                <td class="{{ $td }}"></td>
                <td class="{{ $td }}" colspan="3">TOTAL</td>
                <td class="{{ $tdNum }}">{{ (float) $grand['qty'] }}</td>
                <td class="{{ $tdNum }}">{{ (float) $grand['qty_actual'] }}</td>
                <td class="{{ $tdNum }}">{{ (float) $grand['sisa'] }}</td>
                @if ($priced)
                    <td class="{{ $tdNum }}"></td>
                    <td class="{{ $tdNum }}">{{ $money($report === 'boq' ? $grand['total_actual'] : $grand['nilai_sisa']) }}</td>
                @endif
                <td class="{{ $tdNum }}">{{ $grand['lop_count'] }}</td>
            </tr>
        @endif
    </tbody>
</x-table>

@if ($isPaginator)
    <div class="no-print mt-4">{{ $rows->links() }}</div>
@endif
