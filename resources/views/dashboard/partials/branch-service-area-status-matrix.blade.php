@php
    $branchStatusGroupOpenDefault = count($serviceAreaStatusGroups) <= 10;
    $branchStatusGroupOpenState = collect($serviceAreaStatusGroups)
        ->mapWithKeys(fn (array $group, int $index) => [$index => $branchStatusGroupOpenDefault])
        ->all();
@endphp

<div x-data="dashboardRegionCapture(@js($scopeLabel))" x-ref="captureArea" class="overflow-hidden rounded-md border border-ink-200 bg-white shadow-sm dark:border-ink-700 dark:bg-ink-900">
    <div class="flex flex-wrap items-center justify-between gap-3 p-4">
        <div>
            <p data-capture-full-label class="truncate text-sm font-extrabold text-ink-900 dark:text-white">{{ $scopeLabel }}</p>
            <p class="mt-1 text-xs text-ink-500 dark:text-ink-400">Ringkasan status LOP berdasarkan nama Service Area.</p>
        </div>
        <x-dashboard-capture-controls id="branch-service-area-image-format" :label="$scopeLabel" />
    </div>
    <div class="border-t border-ink-100 dark:border-ink-800" @if ($serviceAreaStatusGroups !== []) x-data="{ openGroups: @js($branchStatusGroupOpenState) }" @endif>
    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-ink-100 bg-ink-50/70 px-4 py-3 dark:border-ink-800 dark:bg-ink-800/60">
        <p class="text-xs font-bold text-ink-700 dark:text-ink-200">Pengelompokan berdasarkan nama Service Area</p>
        <span class="text-[10px] text-ink-500 dark:text-ink-400">{{ count($serviceAreaStatusGroups) }} kelompok · klik angka status pada STO untuk melihat LOP</span>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full min-w-294 border-separate border-spacing-0 text-xs">
            <thead class="bg-ink-50 dark:bg-ink-800/80">
                <tr>
                    <th rowspan="2" class="sticky left-0 z-10 min-w-52 border-b border-r-2 border-ink-200 bg-ink-50 px-4 py-3 text-left text-[10px] font-extrabold uppercase tracking-wider text-ink-500 dark:border-ink-700 dark:bg-ink-800">Service Area / STO</th>
                    <th rowspan="2" class="w-24 min-w-24 border-b border-r-2 border-ink-200 bg-amber-50 px-2 py-3 text-center text-[10px] font-extrabold uppercase tracking-wider text-amber-800 dark:border-ink-700 dark:bg-amber-950/30 dark:text-amber-200" title="Tiket incident yang belum termapping atau belum memiliki LOP">BLM MAPPING</th>
                    <th colspan="2" class="border-b border-r-2 border-ink-200 bg-sky-50 px-2 py-2 text-center text-[10px] font-extrabold uppercase tracking-wider text-sky-800 dark:border-ink-700 dark:bg-sky-950/30 dark:text-sky-200">SDH MAPPING</th>
                    <th colspan="{{ count($blmCloseStatuses) }}" class="border-b border-r-2 border-ink-200 bg-amber-100 px-2 py-2 text-center text-[10px] font-extrabold uppercase tracking-wider text-amber-900 dark:border-ink-700 dark:bg-amber-950/40 dark:text-amber-200">BLM CLOSE</th>
                    <th class="border-b border-r-2 border-ink-200 bg-emerald-100 px-2 py-2 text-center text-[10px] font-extrabold uppercase tracking-wider text-emerald-900 dark:border-ink-700 dark:bg-emerald-950/40 dark:text-emerald-200">CLOSE</th>
                    <th colspan="2" class="border-b border-ink-200 bg-ink-100 px-2 py-2 text-center text-[10px] font-extrabold uppercase tracking-wider text-ink-700 dark:border-ink-700 dark:bg-ink-700 dark:text-ink-200">TOTAL</th>
                </tr>
                <tr>
                    <th class="w-24 min-w-24 border-b border-r border-ink-200 bg-emerald-50 px-2 py-2 text-center text-[10px] font-extrabold uppercase tracking-wider text-emerald-800 dark:border-ink-700 dark:bg-emerald-950/30 dark:text-emerald-200">SDH CLOSE</th>
                    <th class="w-24 min-w-24 border-b border-r border-ink-200 bg-amber-50 px-2 py-2 text-center text-[10px] font-extrabold uppercase tracking-wider text-amber-800 dark:border-ink-700 dark:bg-amber-950/30 dark:text-amber-200">BLM CLOSE</th>
                    @foreach ($blmCloseStatuses as $status)
                        <th class="w-24 min-w-24 border-b {{ $loop->last ? 'border-r-2' : 'border-r' }} border-ink-200 bg-amber-50/50 px-2 py-3 text-center text-[10px] font-extrabold uppercase tracking-wider text-amber-800 dark:border-ink-700 dark:bg-amber-950/20 dark:text-amber-100">{{ $status['label'] }}</th>
                    @endforeach
                    <th class="w-24 min-w-24 border-b border-r border-ink-200 bg-emerald-50 px-2 py-3 text-center text-[10px] font-extrabold uppercase tracking-wider text-emerald-800 dark:border-ink-700 dark:bg-emerald-950/30 dark:text-emerald-200">{{ $closeStatus['label'] }}</th>
                    <th class="w-24 min-w-24 border-b border-r border-ink-200 bg-ink-100 px-2 py-3 text-center text-[10px] font-extrabold uppercase tracking-wider text-ink-700 dark:border-ink-700 dark:bg-ink-700 dark:text-ink-200">Total</th>
                    <th class="w-24 min-w-24 border-b border-ink-200 bg-ink-50 px-2 py-3 text-center text-[10px] font-extrabold uppercase tracking-wider text-ink-700 dark:border-ink-700 dark:bg-ink-700 dark:text-ink-200">Persentase</th>
                </tr>
            </thead>
            @forelse ($serviceAreaStatusGroups as $groupIndex => $group)
                <tbody class="divide-y divide-ink-100 dark:divide-ink-800">
                    <tr class="bg-ink-50 font-bold dark:bg-ink-800/60">
                        <td class="sticky left-0 z-10 border-r border-ink-200 bg-ink-50 px-3 py-2.5 text-ink-800 dark:border-ink-700 dark:bg-ink-800 dark:text-ink-100">
                            <button type="button" @click="openGroups[{{ $groupIndex }}] = !openGroups[{{ $groupIndex }}]" :aria-expanded="!!openGroups[{{ $groupIndex }}]" class="flex w-full items-center gap-2 rounded-md px-1 py-1 text-left focus:outline-none focus-visible:ring-2 focus-visible:ring-ink-400/50">
                                <span class="grid h-6 w-6 shrink-0 place-items-center rounded-md bg-ink-200 text-ink-600 dark:bg-ink-700 dark:text-ink-200"><svg class="h-3 w-3 transition-transform" :class="openGroups[{{ $groupIndex }}] ? 'rotate-90' : ''" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="m9 5.25 6.75 6.75L9 18.75"/></svg></span>
                                <span class="min-w-0 wrap-break-word">{{ $group['name'] }}</span>
                                <span class="ml-auto shrink-0 text-[9px] font-semibold text-ink-500 dark:text-ink-400">
                                    @if ($group['count'] > 0)
                                        {{ $group['count'] }} STO
                                    @else
                                        {{ number_format($group['unmapped_lops']) }} LOP belum termapping
                                    @endif
                                </span>
                            </button>
                        </td>
                        <td class="w-24 min-w-24 border-r border-amber-200 bg-amber-50/30 px-3 py-3 text-center font-extrabold tabular-nums text-amber-800 dark:border-ink-700 dark:bg-amber-950/20 dark:text-amber-200">
                            @if ($incidentCountsAvailable)
                                {{ number_format($group['without_exclude']) }}
                            @else
                                <span title="Data incident tidak tersedia">—</span>
                            @endif
                        </td>
                        <td class="w-24 min-w-24 border-r border-ink-200 bg-emerald-50/40 px-3 py-3 text-center font-extrabold tabular-nums text-emerald-800 dark:border-ink-700 dark:bg-emerald-950/10 dark:text-emerald-200">{{ number_format($group['summary']['sdh_close']) }}</td>
                        <td class="w-24 min-w-24 border-r border-ink-200 bg-amber-50/40 px-3 py-3 text-center font-extrabold tabular-nums text-amber-800 dark:border-ink-700 dark:bg-amber-950/10 dark:text-amber-200">{{ number_format($group['summary']['blm_close']) }}</td>
                        @foreach ($blmCloseStatuses as $status)
                            <td class="w-24 min-w-24 border-r border-ink-200 bg-amber-50/30 px-3 py-3 text-center font-extrabold tabular-nums text-amber-800 dark:border-ink-700 dark:bg-amber-950/10 dark:text-amber-200">{{ number_format($group['summary']['pipeline'][$status['value']]) }}</td>
                        @endforeach
                        <td class="w-24 min-w-24 border-r border-ink-200 bg-emerald-50/40 px-3 py-3 text-center font-extrabold tabular-nums text-emerald-800 dark:border-ink-700 dark:bg-emerald-950/10 dark:text-emerald-200">{{ number_format($group['summary']['pipeline'][$closeStatus['value']]) }}</td>
                        <td class="w-24 min-w-24 border-r border-ink-200 bg-ink-50 px-3 py-3 text-center font-extrabold tabular-nums text-ink-900 dark:border-ink-700 dark:bg-ink-800/60 dark:text-white">{{ number_format($group['summary']['total']) }}</td>
                        <td class="w-24 min-w-24 bg-ink-50 px-3 py-3 text-center font-extrabold tabular-nums text-ink-700 dark:bg-ink-800/60 dark:text-ink-200">{{ $group['summary']['percentage'] }}%</td>
                    </tr>
                    @foreach ($group['rows'] as $row)
                        <tr x-show="openGroups[{{ $groupIndex }}]" x-cloak data-capture-expand class="transition hover:bg-ink-50 dark:hover:bg-ink-800/40">
                            <td class="sticky left-0 z-10 whitespace-nowrap border-r border-ink-100 bg-white px-5 py-3 font-semibold text-ink-600 dark:border-ink-800 dark:bg-ink-900 dark:text-ink-300"><span class="mr-2 text-ink-300 dark:text-ink-700">•</span>{{ $row['label'] }}</td>
                            <td class="w-24 min-w-24 border-r border-amber-200 bg-amber-50/15 px-3 py-3 text-center font-semibold tabular-nums dark:border-ink-700 dark:bg-amber-950/5 {{ $row['without_exclude'] === 0 ? 'text-ink-300 dark:text-ink-600' : 'text-amber-800 dark:text-amber-200' }}">
                                @if ($incidentCountsAvailable && ! empty($row['filters']['service_area_id']))
                                    <a href="{{ route('incident-monitoring.tickets', ['branch' => $branchAdminId, 'service_area' => $row['filters']['service_area_id'], 'usage' => 'without_lop']) }}" title="Buka incident belum memiliki LOP dan belum di-exclude — {{ $row['label'] }}" class="tabular-nums hover:underline focus:outline-none focus-visible:rounded focus-visible:ring-2 focus-visible:ring-amber-500/40">{{ number_format($row['without_exclude']) }}</a>
                                @elseif ($incidentCountsAvailable)
                                    {{ number_format($row['without_exclude']) }}
                                @else
                                    <span title="Data incident tidak tersedia">—</span>
                                @endif
                            </td>
                            <td class="w-24 min-w-24 border-r border-ink-100 bg-emerald-50/40 px-3 py-3 text-center font-medium text-emerald-800 dark:border-ink-800 dark:bg-emerald-950/10 dark:text-emerald-200"><x-matrix-number :value="$row['summary']['sdh_close']" :title="'SDH Close '.$row['label']" :filters="array_merge($matrixScopeFilters, $row['filters'], ['metric' => 'complete'])" mute-zero/></td>
                            <td class="w-24 min-w-24 border-r border-ink-100 bg-amber-50/40 px-3 py-3 text-center font-medium text-amber-800 dark:border-ink-800 dark:bg-amber-950/10 dark:text-amber-200"><x-matrix-number :value="$row['summary']['blm_close']" :title="'BLM Close '.$row['label']" :filters="array_merge($matrixScopeFilters, $row['filters'], ['metric' => 'not_complete'])" mute-zero/></td>
                            @foreach ($blmCloseStatuses as $status)
                                <td class="w-24 min-w-24 border-r border-ink-100 bg-amber-50/20 px-3 py-3 text-center font-medium text-amber-800 dark:border-ink-800 dark:bg-amber-950/10 dark:text-amber-200"><x-matrix-number :value="$row['summary']['pipeline'][$status['value']]" :title="$status['label'].' '.$row['label']" :filters="array_merge($matrixScopeFilters, $row['filters'], ['status' => $status['value']])" mute-zero/></td>
                            @endforeach
                            <td class="w-24 min-w-24 border-r border-ink-100 bg-emerald-50/40 px-3 py-3 text-center font-medium text-emerald-800 dark:border-ink-800 dark:bg-emerald-950/10 dark:text-emerald-200"><x-matrix-number :value="$row['summary']['pipeline'][$closeStatus['value']]" :title="$closeStatus['label'].' '.$row['label']" :filters="array_merge($matrixScopeFilters, $row['filters'], ['status' => $closeStatus['value']])" mute-zero/></td>
                            <td class="w-24 min-w-24 bg-ink-50 px-3 py-3 text-center font-extrabold text-ink-900 dark:bg-ink-800/60 dark:text-white"><x-matrix-number :value="$row['summary']['total']" :title="'Total LOP — '.$row['label']" :filters="array_merge($matrixScopeFilters, $row['filters'])" mute-zero/></td>
                            <td class="w-24 min-w-24 bg-ink-50 px-3 py-3 text-center font-extrabold text-ink-700 dark:bg-ink-800/60 dark:text-ink-200"><x-matrix-number :value="$row['summary']['percentage']" suffix="%" :title="'Completion — '.$row['label']" :filters="array_merge($matrixScopeFilters, $row['filters'], ['metric' => 'complete'])" mute-zero/></td>
                        </tr>
                    @endforeach
                </tbody>
            @empty
                <tbody>
                    <tr><td colspan="{{ count($pipelineStatuses) + 6 }}" class="px-4 py-10 text-center text-xs text-ink-400">Belum ada data Service Area untuk ditampilkan.</td></tr>
                </tbody>
            @endforelse
        </table>
    </div>
</div>
</div>
