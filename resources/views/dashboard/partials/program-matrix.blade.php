@php
    $matrixColumns = [
        ['total', 'Total LOP', '', 'text-ink-900 dark:text-white'],
        ['assigned', 'Assign', 'assigned', 'text-blue-700 dark:text-blue-300'],
        ['unassigned', 'Unassign', 'unassigned', 'text-ink-600 dark:text-ink-300'],
        ['in_review', 'In Review', 'in_review', 'text-amber-700 dark:text-amber-300'],
        ['complete', 'Complete', 'complete', 'text-emerald-700 dark:text-emerald-300'],
        ['percentage', 'Persentase', 'complete', 'text-emerald-700 dark:text-emerald-300'],
    ];
    // Matrix STO dikelompokkan per name service_areas agar ringkasan wilayah mudah dipindai dan baris STO bisa dilipat.
    $isGrouped = ($programMatrix['dimension'] ?? '') === 'service_area' && $programMatrix['groups'] !== [];
    $groupOpenDefault = count($programMatrix['rows']) <= 10;
    $groupOpenState = collect($programMatrix['groups'])->mapWithKeys(fn (array $group, int $index) => [$index => $groupOpenDefault])->all();
@endphp
<div class="mt-5 overflow-hidden rounded-md border border-ink-200 dark:border-ink-700" @if ($isGrouped) x-data="{ openGroups: @js($groupOpenState) }" @endif>
    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-ink-200 bg-ink-50 px-4 py-3 dark:border-ink-700 dark:bg-ink-800">
        <p class="text-xs font-bold text-ink-700 dark:text-ink-200">{{ $scopeLabel }}</p>
        <span class="text-[10px] text-ink-500 dark:text-ink-400">
            @if ($isGrouped)
                {{ count($programMatrix['groups']) }} kelompok · {{ count($programMatrix['rows']) }} STO · Klik angka untuk melihat LOP
                <button type="button" @click="openGroups = Object.fromEntries(Object.keys(openGroups).map(k => [k, !Object.values(openGroups).every(Boolean)]))"
                        class="ml-2 rounded-md border border-ink-200 px-2 py-1 font-bold text-ink-600 transition hover:border-emerald-300 hover:text-emerald-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500/40 dark:border-ink-700 dark:text-ink-300 dark:hover:border-emerald-800 dark:hover:text-emerald-300">
                    <span x-text="Object.values(openGroups).every(Boolean) ? 'Lipat semua' : 'Buka semua'">Buka semua</span>
                </button>
            @else
                {{ count($programMatrix['rows']) }} {{ $programMatrix['heading'] }} · Klik angka untuk melihat LOP
            @endif
        </span>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full min-w-180 border-separate border-spacing-0 text-xs">
            <thead class="bg-ink-50 dark:bg-ink-800/80">
                <tr>
                    <th class="sticky left-0 z-10 min-w-52 border-b border-r border-ink-200 bg-ink-50 px-4 py-3 text-left text-[10px] font-extrabold uppercase tracking-wider text-ink-500 dark:border-ink-700 dark:bg-ink-800">{{ $programMatrix['heading'] }}</th>
                    @foreach ($matrixColumns as [$key, $label, $metric, $tone])<th class="min-w-24 border-b border-r border-ink-200 px-3 py-3 text-center text-[10px] font-extrabold uppercase tracking-wider text-ink-500 last:border-r-0 dark:border-ink-700">{{ $label }}</th>@endforeach
                </tr>
            </thead>
            @if ($isGrouped)
                @foreach ($programMatrix['groups'] as $groupIndex => $group)
                    <tbody id="matrix-group-{{ $groupIndex }}" class="divide-y divide-ink-100 dark:divide-ink-800">
                        <tr class="bg-emerald-50/70 font-bold dark:bg-emerald-950/20">
                            <td class="sticky left-0 z-10 border-r border-ink-200 bg-emerald-50 px-3 py-2.5 text-emerald-800 dark:border-ink-700 dark:bg-ink-900 dark:text-emerald-300">
                                <button type="button" @click="openGroups[{{ $groupIndex }}] = !openGroups[{{ $groupIndex }}]" :aria-expanded="!!openGroups[{{ $groupIndex }}]" aria-controls="matrix-group-{{ $groupIndex }}"
                                        class="flex w-full items-center gap-2 rounded-md px-1 py-1 text-left focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500/40">
                                    <span class="grid h-6 w-6 shrink-0 place-items-center rounded-md bg-emerald-100 dark:bg-emerald-900/50"><svg class="h-3 w-3 transition-transform" :class="openGroups[{{ $groupIndex }}] ? 'rotate-90' : ''" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="m9 5.25 6.75 6.75L9 18.75"/></svg></span>
                                    <span class="min-w-0 wrap-break-word">{{ $group['name'] }}</span>
                                    <span class="ml-auto shrink-0 text-[9px] font-semibold text-emerald-600 dark:text-emerald-400">{{ $group['count'] }} STO</span>
                                </button>
                            </td>
                            <td class="border-r border-ink-200 px-3 py-3 text-center tabular-nums text-ink-900 dark:border-ink-700 dark:text-white">{{ number_format($group['summary']['total']) }}</td>
                            <td class="border-r border-ink-200 px-3 py-3 text-center tabular-nums text-blue-700 dark:border-ink-700 dark:text-blue-300">{{ number_format($group['summary']['assigned']) }}</td>
                            <td class="border-r border-ink-200 px-3 py-3 text-center tabular-nums dark:border-ink-700">{{ number_format($group['summary']['unassigned']) }}</td>
                            <td class="border-r border-ink-200 px-3 py-3 text-center tabular-nums text-amber-700 dark:border-ink-700 dark:text-amber-300">{{ number_format($group['summary']['in_review']) }}</td>
                            <td class="border-r border-ink-200 px-3 py-3 text-center tabular-nums text-emerald-700 dark:border-ink-700 dark:text-emerald-300">{{ number_format($group['summary']['complete']) }}</td>
                            <td class="px-3 py-3 text-center"><span class="rounded-full px-2.5 py-1 text-[10px] tabular-nums {{ $group['summary']['percentage'] === 100 ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300' : 'bg-ink-50 text-ink-600 dark:bg-ink-800 dark:text-ink-300' }}">{{ $group['summary']['percentage'] }}%</span></td>
                        </tr>
                        @foreach ($group['rows'] as $row)
                            <tr x-show="openGroups[{{ $groupIndex }}]" x-cloak>@include('dashboard.partials.program-matrix-row', ['row' => $row])</tr>
                        @endforeach
                    </tbody>
                @endforeach
            @else
                <tbody class="divide-y divide-ink-100 dark:divide-ink-800">
                    @forelse ($programMatrix['rows'] as $row)
                        <tr class="transition hover:bg-ink-50 dark:hover:bg-ink-800/40">@include('dashboard.partials.program-matrix-row', ['row' => $row])</tr>
                    @empty
                        <tr><td colspan="{{ count($matrixColumns) + 1 }}" class="px-4 py-10 text-center text-xs text-ink-400">Tidak ada data pada kombinasi filter ini.</td></tr>
                    @endforelse
                </tbody>
            @endif
            @if ($isGrouped)
                <tfoot class="border-t border-ink-200 bg-emerald-50 font-bold dark:border-ink-700 dark:bg-emerald-950/30">
                    <tr>
                        <th scope="row" class="sticky left-0 z-10 bg-emerald-50 px-4 py-3 text-left text-emerald-900 dark:bg-ink-900 dark:text-emerald-200">Grand Total</th>
                        @foreach ($matrixColumns as [$key, $label, $metric, $tone])<td class="px-3 py-3 text-center {{ $tone }}"><x-matrix-number :value="$programMatrix['summary'][$key]" :suffix="$key === 'percentage' ? '%' : ''" :title="$label.' — Total Scope'" :filters="array_merge($matrixScopeFilters, $metric ? ['metric' => $metric] : [])"/></td>@endforeach
                    </tr>
                </tfoot>
            @endif
        </table>
    </div>
</div>
