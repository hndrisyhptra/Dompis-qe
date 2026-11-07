@php
    $branchStatusGroupOpenDefault = count($serviceAreaStatusGroups) <= 10;
    $branchStatusGroupOpenState = collect($serviceAreaStatusGroups)
        ->mapWithKeys(fn (array $group, int $index) => [$index => $branchStatusGroupOpenDefault])
        ->all();
@endphp

<div x-data="dashboardRegionCapture(@js($scopeLabel))" x-ref="captureArea" class="overflow-hidden rounded-md border border-blue-200 bg-white shadow-sm dark:border-blue-900 dark:bg-ink-900">
    <div class="flex flex-wrap items-center justify-between gap-3 p-4">
        <div>
            <p data-capture-full-label class="truncate text-sm font-extrabold text-ink-900 dark:text-white">{{ $scopeLabel }}</p>
            <p class="mt-1 text-xs text-ink-500 dark:text-ink-400">Ringkasan status LOP berdasarkan nama Service Area.</p>
        </div>
        <x-dashboard-capture-controls id="branch-service-area-image-format" :label="$scopeLabel" />
    </div>
    <div class="border-t border-ink-100 dark:border-ink-800" @if ($serviceAreaStatusGroups !== []) x-data="{ openGroups: @js($branchStatusGroupOpenState) }" @endif>
    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-blue-100 bg-blue-50/70 px-4 py-3 dark:border-blue-900/60 dark:bg-blue-950/20">
        <p class="text-xs font-bold text-blue-800 dark:text-blue-200">Pengelompokan berdasarkan nama Service Area</p>
        <span class="text-[10px] text-ink-500 dark:text-ink-400">{{ count($serviceAreaStatusGroups) }} kelompok · klik angka status pada STO untuk melihat LOP</span>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full min-w-294 border-separate border-spacing-0 text-xs">
            <thead class="bg-ink-50 dark:bg-ink-800/80">
                <tr>
                    <th class="sticky left-0 z-10 min-w-52 border-b border-r border-ink-200 bg-ink-50 px-4 py-3 text-left text-[10px] font-extrabold uppercase tracking-wider text-ink-500 dark:border-ink-700 dark:bg-ink-800">Service Area / STO</th>
                    <th class="w-24 min-w-24 border-b border-r border-ink-200 bg-amber-50 px-2 py-3 text-center text-[10px] font-extrabold uppercase tracking-wider text-amber-800 dark:border-ink-700 dark:bg-amber-950/30 dark:text-amber-200">Belum Exclude</th>
                    @foreach ($pipelineStatuses as $status)
                        <th class="w-24 min-w-24 border-b border-r border-ink-200 px-2 py-3 text-center text-[10px] font-extrabold uppercase tracking-wider text-ink-500 dark:border-ink-700">{{ $status['label'] }}</th>
                    @endforeach
                    <th class="w-24 min-w-24 border-b border-ink-200 bg-ink-100 px-2 py-3 text-center text-[10px] font-extrabold uppercase tracking-wider text-ink-700 dark:border-ink-700 dark:bg-ink-700 dark:text-ink-200">Total</th>
                    <th class="w-24 min-w-24 border-b border-ink-200 bg-emerald-50 px-2 py-3 text-center text-[10px] font-extrabold uppercase tracking-wider text-emerald-800 dark:border-ink-700 dark:bg-emerald-950/40 dark:text-emerald-200">Persentase</th>
                </tr>
            </thead>
            @forelse ($serviceAreaStatusGroups as $groupIndex => $group)
                <tbody class="divide-y divide-ink-100 dark:divide-ink-800">
                    <tr class="bg-blue-50/70 font-bold dark:bg-blue-950/20">
                        <td class="sticky left-0 z-10 border-r border-ink-200 bg-blue-50 px-3 py-2.5 text-blue-800 dark:border-ink-700 dark:bg-ink-900 dark:text-blue-300">
                            <button type="button" @click="openGroups[{{ $groupIndex }}] = !openGroups[{{ $groupIndex }}]" :aria-expanded="!!openGroups[{{ $groupIndex }}]" class="flex w-full items-center gap-2 rounded-md px-1 py-1 text-left focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500/40">
                                <span class="grid h-6 w-6 shrink-0 place-items-center rounded-md bg-blue-100 dark:bg-blue-900/50"><svg class="h-3 w-3 transition-transform" :class="openGroups[{{ $groupIndex }}] ? 'rotate-90' : ''" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="m9 5.25 6.75 6.75L9 18.75"/></svg></span>
                                <span class="min-w-0 wrap-break-word">{{ $group['name'] }}</span>
                                <span class="ml-auto shrink-0 text-[9px] font-semibold text-blue-500">
                                    @if ($group['count'] > 0)
                                        {{ $group['count'] }} STO
                                    @else
                                        {{ number_format($group['unmapped_lops']) }} LOP belum termapping
                                    @endif
                                </span>
                            </button>
                        </td>
                        <td class="w-24 min-w-24 border-r border-ink-200 bg-amber-50/70 px-3 py-3 text-center font-extrabold text-amber-800 dark:border-ink-700 dark:bg-amber-950/20 dark:text-amber-200">
                            @if ($incidentCountsAvailable)
                                {{ number_format($group['without_exclude']) }}
                            @else
                                <span title="Data incident tidak tersedia">—</span>
                            @endif
                        </td>
                        @foreach ($pipelineStatuses as $status)
                            <td class="w-24 min-w-24 border-r border-ink-200 px-3 py-3 text-center font-extrabold dark:border-ink-700">{{ number_format($group['summary']['pipeline'][$status['value']]) }}</td>
                        @endforeach
                        <td class="w-24 min-w-24 bg-blue-100/60 px-3 py-3 text-center font-extrabold text-blue-900 dark:bg-blue-950/40 dark:text-blue-200">{{ number_format($group['summary']['total']) }}</td>
                        <td class="w-24 min-w-24 bg-emerald-50 px-3 py-3 text-center font-extrabold text-emerald-800 dark:bg-emerald-950/30 dark:text-emerald-200">{{ $group['summary']['percentage'] }}%</td>
                    </tr>
                    @foreach ($group['rows'] as $row)
                        <tr x-show="openGroups[{{ $groupIndex }}]" x-cloak data-capture-expand class="transition hover:bg-ink-50 dark:hover:bg-ink-800/40">
                            <td class="sticky left-0 z-10 whitespace-nowrap border-r border-ink-100 bg-white px-5 py-3 font-semibold text-ink-600 dark:border-ink-800 dark:bg-ink-900 dark:text-ink-300"><span class="mr-2 text-ink-300 dark:text-ink-700">•</span>{{ $row['label'] }}</td>
                            <td class="w-24 min-w-24 border-r border-ink-100 bg-amber-50/50 px-3 py-3 text-center font-semibold text-amber-800 dark:border-ink-800 dark:bg-amber-950/10 dark:text-amber-200">
                                @if ($incidentCountsAvailable && ! empty($row['filters']['service_area_id']))
                                    <a href="{{ route('incident-monitoring.tickets', ['branch' => $branchAdminId, 'service_area' => $row['filters']['service_area_id'], 'usage' => 'without_lop']) }}" title="Buka incident belum memiliki LOP dan belum di-exclude — {{ $row['label'] }}" class="tabular-nums hover:underline focus:outline-none focus-visible:rounded focus-visible:ring-2 focus-visible:ring-amber-500/40">{{ number_format($row['without_exclude']) }}</a>
                                @elseif ($incidentCountsAvailable)
                                    {{ number_format($row['without_exclude']) }}
                                @else
                                    <span title="Data incident tidak tersedia">—</span>
                                @endif
                            </td>
                            @foreach ($pipelineStatuses as $status)
                                <td class="w-24 min-w-24 border-r border-ink-100 px-3 py-3 text-center font-medium text-ink-600 dark:border-ink-800 dark:text-ink-300"><x-matrix-number :value="$row['summary']['pipeline'][$status['value']]" :title="$status['label'].' '.$row['label']" :filters="array_merge($matrixScopeFilters, $row['filters'], ['status' => $status['value']])"/></td>
                            @endforeach
                            <td class="w-24 min-w-24 bg-ink-50 px-3 py-3 text-center font-extrabold text-ink-900 dark:bg-ink-800/60 dark:text-white"><x-matrix-number :value="$row['summary']['total']" :title="'Total LOP — '.$row['label']" :filters="array_merge($matrixScopeFilters, $row['filters'])"/></td>
                            <td class="w-24 min-w-24 bg-emerald-50/60 px-3 py-3 text-center font-extrabold text-emerald-800 dark:bg-emerald-950/20 dark:text-emerald-200"><x-matrix-number :value="$row['summary']['percentage']" suffix="%" :title="'Completion — '.$row['label']" :filters="array_merge($matrixScopeFilters, $row['filters'], ['metric' => 'complete'])"/></td>
                        </tr>
                    @endforeach
                </tbody>
            @empty
                <tbody>
                    <tr><td colspan="{{ count($pipelineStatuses) + 4 }}" class="px-4 py-10 text-center text-xs text-ink-400">Belum ada data Service Area untuk ditampilkan.</td></tr>
                </tbody>
            @endforelse
        </table>
    </div>
</div>
</div>
