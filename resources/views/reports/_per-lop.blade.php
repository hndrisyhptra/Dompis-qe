{{-- di-include dengan ['report' => …, 'data' => …, 'priced' => …] --}}
@php
    $groups = $data['groups'];
    $grand = $data['grand'];
    $isPaginator = $groups instanceof \Illuminate\Contracts\Pagination\Paginator;
    $isPrint = $print ?? false;

    $th = 'px-4 py-2.5 text-left text-[11px] font-bold uppercase tracking-wider text-ink-500';
    $thNum = 'px-4 py-2.5 text-right text-[11px] font-bold uppercase tracking-wider text-ink-500';
    $td = 'px-4 py-2 text-ink-700 dark:text-ink-200';
    $tdNum = 'px-4 py-2 text-right tabular-nums text-ink-800 dark:text-ink-100';
    $money = fn ($v) => $v === null ? '—' : 'Rp ' . number_format($v, 0, ',', '.');
    $subValue = fn ($s) => $report === 'boq' ? $s['total_actual'] : $s['nilai_sisa'];
@endphp

@if (! $isPrint && (is_countable($groups) ? count($groups) : $groups->count()) > 1)
    <div class="no-print flex justify-end" x-data>
        <button type="button" @click="$dispatch('toggle-all-lop', { open: true })" class="text-xs font-semibold text-brand-600 hover:underline">Buka semua</button>
        <span class="mx-2 text-ink-300">·</span>
        <button type="button" @click="$dispatch('toggle-all-lop', { open: false })" class="text-xs font-semibold text-ink-500 hover:underline">Tutup semua</button>
    </div>
@endif

<div class="space-y-3">
    @forelse ($groups as $group)
        @php $sub = $group['subtotal']; @endphp
        <div class="overflow-hidden rounded-2xl border border-ink-100 bg-white shadow-sm dark:border-ink-800 dark:bg-ink-900"
             x-data="{ open: {{ $isPrint ? 'true' : 'false' }} }"
             @toggle-all-lop.window="open = $event.detail.open">

            <button type="button" @click="open = !open"
                    class="flex w-full flex-wrap items-center gap-x-3 gap-y-1.5 px-4 py-3 text-left transition hover:bg-ink-50/60 dark:hover:bg-ink-800/40">
                <svg class="h-4 w-4 shrink-0 text-ink-400 transition" :class="open && 'rotate-90'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m9 5 7 7-7 7" /></svg>
                <span class="font-bold text-ink-900 dark:text-white">{{ $group['lop']['name'] }}</span>
                <span class="text-xs text-ink-400">{{ $group['lop']['incident'] }}</span>
                <span class="hidden sm:inline"><x-badge variant="neutral">{{ $group['lop']['branch'] ?: 'Branch —' }}</x-badge></span>
                @if ($group['lop']['status_raw'])
                    <span class="hidden sm:inline"><x-badge :variant="\App\Enums\LopStatus::from($group['lop']['status_raw'])->badgeVariant()">{{ $group['lop']['status'] }}</x-badge></span>
                @endif
                <span class="ml-auto flex flex-wrap items-center gap-x-4 gap-y-0.5 text-xs text-ink-500 dark:text-ink-400">
                    <span>Actual <strong class="tabular-nums text-ink-800 dark:text-ink-100">{{ (float) $sub['qty_actual'] }}</strong></span>
                    @if ($report === 'sisa')<span>Sisa <strong class="tabular-nums {{ (float) $sub['sisa'] > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-ink-800 dark:text-ink-100' }}">{{ (float) $sub['sisa'] }}</strong></span>@endif
                    @if ($priced)<span class="font-bold text-ink-800 dark:text-ink-100">{{ $money($subValue($sub)) }}</span>@endif
                </span>
            </button>

            <div x-show="open" x-cloak>
                <div class="overflow-x-auto border-t border-ink-100 dark:border-ink-800">
                    <table class="min-w-full divide-y divide-ink-100 text-sm dark:divide-ink-800">
                        <thead class="bg-ink-50/70 dark:bg-ink-800/70">
                            <tr>
                                <th class="{{ $th }}">Designator</th>
                                <th class="{{ $th }}">Uraian</th>
                                <th class="{{ $th }}">Satuan</th>
                                <th class="{{ $thNum }}">Qty Rencana</th>
                                <th class="{{ $thNum }}">Qty Actual</th>
                                @if ($report === 'sisa')<th class="{{ $thNum }}">Sisa</th>@endif
                                @if ($priced)
                                    <th class="{{ $thNum }}">Harga KHS</th>
                                    <th class="{{ $thNum }}">{{ $report === 'boq' ? 'Total Actual' : 'Nilai Sisa' }}</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-ink-100 dark:divide-ink-800">
                            @foreach ($group['lines'] as $line)
                                <tr>
                                    <td class="{{ $td }} font-medium">{{ $line['designator_code'] }}</td>
                                    <td class="{{ $td }} max-w-xs truncate text-ink-500 dark:text-ink-400">{{ $line['designator_name'] }}</td>
                                    <td class="{{ $td }}">{{ $line['unit'] }}</td>
                                    <td class="{{ $tdNum }}">{{ (float) $line['qty'] }}</td>
                                    <td class="{{ $tdNum }}">{{ $line['qty_actual'] === null ? '—' : (float) $line['qty_actual'] }}</td>
                                    @if ($report === 'sisa')
                                        <td class="{{ $tdNum }} font-semibold">{{ $line['sisa'] === null ? 'belum direkap' : (float) $line['sisa'] }}</td>
                                    @endif
                                    @if ($priced)
                                        <td class="{{ $tdNum }}">
                                            @if ($line['price_missing'])<span class="text-amber-600 dark:text-amber-400">belum diset</span>
                                            @else Rp {{ number_format($line['price'], 0, ',', '.') }}@endif
                                        </td>
                                        <td class="{{ $tdNum }}">{{ $money($report === 'boq' ? $line['total_actual'] : $line['nilai_sisa']) }}</td>
                                    @endif
                                </tr>
                            @endforeach
                            <tr class="bg-ink-50/70 font-bold dark:bg-ink-800/70">
                                <td class="{{ $td }}" colspan="3">Subtotal</td>
                                <td class="{{ $tdNum }}">{{ (float) $sub['qty'] }}</td>
                                <td class="{{ $tdNum }}">{{ (float) $sub['qty_actual'] }}</td>
                                @if ($report === 'sisa')<td class="{{ $tdNum }}">{{ (float) $sub['sisa'] }}</td>@endif
                                @if ($priced)
                                    <td class="{{ $tdNum }}"></td>
                                    <td class="{{ $tdNum }}">{{ $money($subValue($sub)) }}</td>
                                @endif
                            </tr>
                        </tbody>
                    </table>
                </div>
                @if ($priced && $sub['price_missing_count'] > 0)
                    <p class="px-4 py-2 text-xs text-amber-600 dark:text-amber-400">{{ $sub['price_missing_count'] }} designator belum ada harga — tidak dihitung ke subtotal.</p>
                @endif
            </div>
        </div>
    @empty
        <div class="rounded-2xl border border-dashed border-ink-200 px-6 py-16 text-center dark:border-ink-700">
            <p class="text-sm font-bold text-ink-600 dark:text-ink-300">Tidak ada data</p>
            <p class="mt-1 text-xs text-ink-400">Belum ada LOP dengan rekap material yang sudah disubmit untuk filter ini.</p>
        </div>
    @endforelse
</div>

@if ($isPaginator)
    <div class="no-print mt-4">{{ $groups->links() }}</div>
@endif
