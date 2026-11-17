@php
    $monitoringKinds = ['gamas' => 'SQM GAMAS', 'preman' => 'PREMAN', 'infracare' => 'INFRACARE', 'total' => 'TOTAL'];
    $headBase = 'h-14 border border-ink-200 px-2 py-2 text-center align-middle dark:border-ink-700';
    $headTop = $headBase.' bg-ink-100 font-bold text-ink-800 dark:bg-ink-800 dark:text-ink-100';
    $headKind = $headBase.' bg-ink-50 font-semibold text-ink-700 dark:bg-ink-800/70 dark:text-ink-200';
    $headSub = $headBase.' bg-white font-semibold text-ink-600 dark:bg-ink-900 dark:text-ink-300';
    $rowBase = 'bg-white dark:bg-ink-900';
    $totalRow = 'bg-ink-50 font-bold dark:bg-ink-800';
@endphp

<section aria-labelledby="ticket-monitoring-title" data-monitoring-groups="ticket-groups" x-data="{ ...ticketMonitoringModal(@js(route('incident-monitoring.exclusions.detail'))), openGroups: @js($groupOpenState) }" class="overflow-hidden rounded-md border border-ink-100 bg-white dark:border-ink-800 dark:bg-ink-900">
    <header class="flex flex-wrap items-center justify-between gap-3 p-4 sm:p-5">
        <div>
            <h2 id="ticket-monitoring-title" class="text-base font-semibold text-ink-900 dark:text-white">Tabel Monitoring Tiket</h2>
            <p class="mt-1 text-sm text-ink-600 dark:text-ink-300">Periode {{ \Illuminate\Support\Carbon::parse($filters['date_from'])->format('d/m/Y') }} – {{ \Illuminate\Support\Carbon::parse($filters['date_to'])->format('d/m/Y') }}. Sudah mapping berarti tiket sudah memiliki LOP atau sudah di-exclude.</p>
        </div>
        @if ($isGrouped)
            <button type="button" @click="openGroups = Object.fromEntries(Object.keys(openGroups).map(key => [key, !Object.values(openGroups).every(Boolean)]))" class="inline-flex min-h-9 shrink-0 items-center rounded-md border border-ink-200 px-3 text-xs font-semibold text-ink-700 hover:bg-ink-50 focus-visible:outline-2 focus-visible:outline-brand-500 dark:border-ink-700 dark:text-ink-200 dark:hover:bg-ink-800" title="Buka atau lipat semua kelompok">
                <span x-text="Object.values(openGroups).every(Boolean) ? 'Lipat semua' : 'Buka semua'">Buka semua</span>
            </button>
        @endif
    </header>

    @if ($sourceUnavailable)
        <p role="status" class="border-t border-amber-200 bg-amber-50 p-4 text-sm text-amber-800 dark:border-amber-800 dark:bg-amber-950/30 dark:text-amber-200">Jumlah tiket belum dapat dipastikan karena sumber tiket Dompis belum dapat dimuat.</p>
    @endif

    <div class="space-y-5 p-4 pt-0 sm:p-5 sm:pt-0">
        <section x-data="dashboardRegionCapture(@js($captureName.'-tiket-qe'))" x-ref="captureArea" class="overflow-hidden rounded-md border border-ink-100 dark:border-ink-800">
            <div class="flex flex-wrap items-center justify-between gap-2 bg-ink-50 px-3 py-2 dark:bg-ink-800" data-capture-ignore>
                <h3 class="text-xs font-bold text-ink-700 dark:text-ink-200">Tiket QE dan Pareto</h3>
                <x-dashboard-capture-controls id="ticket-qe-capture-format" label="Tiket QE dan Pareto" />
            </div>
        <div role="region" aria-label="Tabel Tiket QE dan Pareto, dapat digeser horizontal" tabindex="0" class="overflow-x-auto focus-visible:outline-2 focus-visible:outline-brand-500">
            <table class="w-full min-w-7xl table-fixed border-collapse text-center text-xs tabular-nums">
                <colgroup>
                    <col style="width: 15rem">
                    @for ($column = 0; $column < 12; $column++)
                        <col>
                    @endfor
                </colgroup>
                <caption class="sr-only">Jumlah tiket QE dan pareto sudah atau belum mapping menurut {{ $matrixSummaryLabel }} dan jenis tiket.</caption>
                <thead>
                    <tr>
                        <th rowspan="3" scope="col" class="sticky left-0 z-10 border border-ink-200 bg-ink-100 px-3 py-3 text-center font-bold text-ink-800 dark:border-ink-700 dark:bg-ink-800 dark:text-ink-100">{{ $matrixGroupLabel }}</th>
                        <th colspan="4" scope="colgroup" class="{{ $headTop }}">TIKET QE</th>
                        <th colspan="8" scope="colgroup" class="{{ $headTop }}">PARETO TIKET QE</th>
                    </tr>
                    <tr>
                        @foreach ($monitoringKinds as $label)
                            <th rowspan="2" scope="col" class="{{ $headKind }}">{{ $label }}</th>
                        @endforeach
                        @foreach ($monitoringKinds as $label)
                            <th colspan="2" scope="colgroup" class="{{ $headKind }}">{{ $label }}</th>
                        @endforeach
                    </tr>
                    <tr>
                        @foreach ($monitoringKinds as $label)
                            <th scope="col" class="{{ $headSub }}">SDH MAPPING</th>
                            <th scope="col" class="{{ $headSub }}">BLM MAPPING</th>
                        @endforeach
                    </tr>
                </thead>
                @if ($isGrouped)
                    @foreach ($matrixGroups as $groupIndex => $group)
                        <tbody id="monitoring-ticket-group-{{ $groupIndex }}" class="divide-y divide-ink-200 dark:divide-ink-700">
                            <tr class="bg-emerald-50/70 font-bold dark:bg-emerald-950/20">
                                <th scope="rowgroup" class="sticky left-0 z-10 border border-ink-200 bg-emerald-50 px-3 py-2.5 text-left text-emerald-800 dark:border-ink-700 dark:bg-ink-900 dark:text-emerald-300">
                                    <button type="button" @click="openGroups[{{ $groupIndex }}] = !openGroups[{{ $groupIndex }}]" :aria-expanded="!!openGroups[{{ $groupIndex }}]" aria-controls="monitoring-ticket-group-{{ $groupIndex }} monitoring-pareto-group-{{ $groupIndex }} monitoring-create-group-{{ $groupIndex }}"
                                            class="flex w-full items-center gap-2 rounded-md px-1 py-1 text-left focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500/40">
                                        <span class="grid h-6 w-6 shrink-0 place-items-center rounded-md bg-emerald-100 dark:bg-emerald-900/50"><svg class="h-3 w-3 transition-transform" :class="openGroups[{{ $groupIndex }}] ? 'rotate-90' : ''" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="m9 5.25 6.75 6.75L9 18.75"/></svg></span>
                                        <span class="min-w-0 flex-1">
                                            <span class="block {{ $groupByBranch ? 'whitespace-nowrap' : 'wrap-break-word' }}">{{ $group['name'] }}</span>
                                            <span class="mt-0.5 block text-[9px] font-semibold text-emerald-600 dark:text-emerald-400">{{ $group['row_count'] }} {{ $matrixUnit }}</span>
                                        </span>
                                    </button>
                                </th>
                                @include('incident-monitoring._monitoring-cells', ['matrixRow' => $group, 'monitoringMode' => 'ticket', 'showLabel' => false])
                            </tr>
                            @foreach ($group['rows'] as $matrixRow)
                                <tr x-show="openGroups[{{ $groupIndex }}]" x-cloak class="{{ $rowBase }}">
                                    @include('incident-monitoring._monitoring-cells', ['matrixRow' => $matrixRow, 'monitoringMode' => 'ticket'])
                                </tr>
                            @endforeach
                        </tbody>
                    @endforeach
                    <tbody>
                        <tr class="{{ $totalRow }}">@include('incident-monitoring._monitoring-cells', ['matrixRow' => $totals, 'monitoringMode' => 'ticket'])</tr>
                    </tbody>
                @else
                    <tbody class="divide-y divide-ink-200 dark:divide-ink-700">
                        @foreach ([...array_values($matrixRows), $totals] as $matrixRow)
                            <tr class="{{ $matrixRow['key'] === '' ? $totalRow : $rowBase }}">@include('incident-monitoring._monitoring-cells', ['matrixRow' => $matrixRow, 'monitoringMode' => 'ticket'])</tr>
                        @endforeach
                    </tbody>
                @endif
            </table>
        </div>
        </section>

        <section x-data="dashboardRegionCapture(@js($captureName.'-pareto-tiket-qe'))" x-ref="captureArea" class="overflow-hidden rounded-md border border-ink-100 dark:border-ink-800">
            <div class="flex flex-wrap items-center justify-between gap-2 bg-ink-50 px-3 py-2 dark:bg-ink-800" data-capture-ignore>
                <h3 class="text-xs font-bold text-ink-700 dark:text-ink-200">Pareto Tiket QE Sudah Mapping</h3>
                <x-dashboard-capture-controls id="ticket-pareto-capture-format" label="Pareto Tiket QE Sudah Mapping" />
            </div>
        <div role="region" aria-label="Tabel Pareto tiket QE sudah mapping, dapat digeser horizontal" tabindex="0" class="overflow-x-auto focus-visible:outline-2 focus-visible:outline-brand-500">
            <table class="w-full min-w-[1900px] table-fixed border-collapse text-center text-xs tabular-nums">
                <colgroup>
                    <col style="width: 15rem">
                    @for ($column = 0; $column < 20; $column++)
                        <col>
                    @endfor
                </colgroup>
                <caption class="sr-only">Rincian tiket QE yang sudah mapping menurut {{ $matrixSummaryLabel }}, jenis tiket, dan tindak lanjut.</caption>
                <thead>
                    <tr>
                        <th rowspan="3" scope="col" class="sticky left-0 z-10 border border-ink-200 bg-ink-100 px-3 py-3 text-center font-bold text-ink-800 dark:border-ink-700 dark:bg-ink-800 dark:text-ink-100">{{ $matrixGroupLabel }}</th>
                        <th colspan="20" scope="colgroup" class="{{ $headTop }}">PARETO TIKET QE SDH MAPPING</th>
                    </tr>
                    <tr>
                        @foreach ($monitoringKinds as $label)
                            <th colspan="5" scope="colgroup" class="{{ $headKind }}">{{ $label }}</th>
                        @endforeach
                    </tr>
                    <tr>
                        @foreach ($monitoringKinds as $label)
                            <th scope="col" class="{{ $headSub }}">CREATE<br>LOP</th>
                            <th scope="col" class="{{ $headSub }}">TEMPORER<br>GAMAS</th>
                            <th scope="col" class="{{ $headSub }}">TDK ADA<br>MATERIAL</th>
                            <th scope="col" class="{{ $headSub }}">DOUBLE<br>TIKET</th>
                            <th scope="col" class="{{ $headSub }}">MAGU<br>PASIF</th>
                        @endforeach
                    </tr>
                </thead>
                @if ($isGrouped)
                    @foreach ($matrixGroups as $groupIndex => $group)
                        <tbody id="monitoring-pareto-group-{{ $groupIndex }}" class="divide-y divide-ink-200 dark:divide-ink-700">
                            <tr class="bg-emerald-50/70 font-bold dark:bg-emerald-950/20">
                                <th scope="rowgroup" class="sticky left-0 z-10 border border-ink-200 bg-emerald-50 px-3 py-2.5 text-left text-emerald-800 dark:border-ink-700 dark:bg-ink-900 dark:text-emerald-300">
                                    <button type="button" @click="openGroups[{{ $groupIndex }}] = !openGroups[{{ $groupIndex }}]" :aria-expanded="!!openGroups[{{ $groupIndex }}]" aria-controls="monitoring-ticket-group-{{ $groupIndex }} monitoring-pareto-group-{{ $groupIndex }} monitoring-create-group-{{ $groupIndex }}"
                                            class="flex w-full items-center gap-2 rounded-md px-1 py-1 text-left focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500/40">
                                        <span class="grid h-6 w-6 shrink-0 place-items-center rounded-md bg-emerald-100 dark:bg-emerald-900/50"><svg class="h-3 w-3 transition-transform" :class="openGroups[{{ $groupIndex }}] ? 'rotate-90' : ''" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="m9 5.25 6.75 6.75L9 18.75"/></svg></span>
                                        <span class="min-w-0 flex-1">
                                            <span class="block {{ $groupByBranch ? 'whitespace-nowrap' : 'wrap-break-word' }}">{{ $group['name'] }}</span>
                                            <span class="mt-0.5 block text-[9px] font-semibold text-emerald-600 dark:text-emerald-400">{{ $group['row_count'] }} {{ $matrixUnit }}</span>
                                        </span>
                                    </button>
                                </th>
                                @include('incident-monitoring._monitoring-cells', ['matrixRow' => $group, 'monitoringMode' => 'pareto', 'showLabel' => false])
                            </tr>
                            @foreach ($group['rows'] as $matrixRow)
                                <tr x-show="openGroups[{{ $groupIndex }}]" x-cloak class="{{ $rowBase }}">
                                    @include('incident-monitoring._monitoring-cells', ['matrixRow' => $matrixRow, 'monitoringMode' => 'pareto'])
                                </tr>
                            @endforeach
                        </tbody>
                    @endforeach
                    <tbody>
                        <tr class="{{ $totalRow }}">@include('incident-monitoring._monitoring-cells', ['matrixRow' => $totals, 'monitoringMode' => 'pareto'])</tr>
                    </tbody>
                @else
                    <tbody class="divide-y divide-ink-200 dark:divide-ink-700">
                        @foreach ([...array_values($matrixRows), $totals] as $matrixRow)
                            <tr class="{{ $matrixRow['key'] === '' ? $totalRow : $rowBase }}">@include('incident-monitoring._monitoring-cells', ['matrixRow' => $matrixRow, 'monitoringMode' => 'pareto'])</tr>
                        @endforeach
                    </tbody>
                @endif
            </table>
        </div>
        </section>

        <section x-data="dashboardRegionCapture(@js($captureName.'-create-lop'))" x-ref="captureArea" class="overflow-hidden rounded-md border border-ink-100 dark:border-ink-800">
            <div class="flex flex-wrap items-center justify-between gap-2 bg-ink-50 px-3 py-2 dark:bg-ink-800" data-capture-ignore>
                <h3 class="text-xs font-bold text-ink-700 dark:text-ink-200">Monitoring Tiket Create LOP</h3>
                <x-dashboard-capture-controls id="ticket-create-lop-capture-format" label="Monitoring Tiket Create LOP" />
            </div>
        <div role="region" aria-label="Tabel monitoring tiket create LOP, dapat digeser horizontal" tabindex="0" class="overflow-x-auto focus-visible:outline-2 focus-visible:outline-brand-500">
            <table class="w-full min-w-240 table-fixed border-collapse text-center text-xs tabular-nums">
                <colgroup>
                    <col style="width: 15rem">
                    @for ($column = 0; $column < 8; $column++)
                        <col>
                    @endfor
                </colgroup>
                <caption class="sr-only">Jumlah tiket create LOP dari sumber Insera atau input manual menurut {{ $matrixSummaryLabel }} dan jenis tiket. LOP manual tidak memiliki jenis tiket sehingga hanya dihitung pada kolom total.</caption>
                <thead>
                    <tr>
                        <th rowspan="3" scope="col" class="sticky left-0 z-10 border border-ink-200 bg-ink-100 px-3 py-3 text-center font-bold text-ink-800 dark:border-ink-700 dark:bg-ink-800 dark:text-ink-100">{{ $matrixGroupLabel }}</th>
                        <th colspan="8" scope="colgroup" class="{{ $headTop }}">MONITORING TIKET CREATE LOP</th>
                    </tr>
                    <tr>
                        @foreach ($monitoringKinds as $label)
                            <th colspan="2" scope="colgroup" class="{{ $headKind }}">{{ $label }}</th>
                        @endforeach
                    </tr>
                    <tr>
                        @foreach ($monitoringKinds as $label)
                            <th scope="col" class="{{ $headSub }}">TIKET<br>INSERA</th>
                            <th scope="col" class="{{ $headSub }}">TIKET<br>MANUAL</th>
                        @endforeach
                    </tr>
                </thead>
                @if ($isGrouped)
                    @foreach ($matrixGroups as $groupIndex => $group)
                        <tbody id="monitoring-create-group-{{ $groupIndex }}" class="divide-y divide-ink-200 dark:divide-ink-700">
                            <tr class="bg-emerald-50/70 font-bold dark:bg-emerald-950/20">
                                <th scope="rowgroup" class="sticky left-0 z-10 border border-ink-200 bg-emerald-50 px-3 py-2.5 text-left text-emerald-800 dark:border-ink-700 dark:bg-ink-900 dark:text-emerald-300">
                                    <button type="button" @click="openGroups[{{ $groupIndex }}] = !openGroups[{{ $groupIndex }}]" :aria-expanded="!!openGroups[{{ $groupIndex }}]" aria-controls="monitoring-ticket-group-{{ $groupIndex }} monitoring-pareto-group-{{ $groupIndex }} monitoring-create-group-{{ $groupIndex }}"
                                            class="flex w-full items-center gap-2 rounded-md px-1 py-1 text-left focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500/40">
                                        <span class="grid h-6 w-6 shrink-0 place-items-center rounded-md bg-emerald-100 dark:bg-emerald-900/50"><svg class="h-3 w-3 transition-transform" :class="openGroups[{{ $groupIndex }}] ? 'rotate-90' : ''" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="m9 5.25 6.75 6.75L9 18.75"/></svg></span>
                                        <span class="min-w-0 flex-1">
                                            <span class="block {{ $groupByBranch ? 'whitespace-nowrap' : 'wrap-break-word' }}">{{ $group['name'] }}</span>
                                            <span class="mt-0.5 block text-[9px] font-semibold text-emerald-600 dark:text-emerald-400">{{ $group['row_count'] }} {{ $matrixUnit }}</span>
                                        </span>
                                    </button>
                                </th>
                                @include('incident-monitoring._monitoring-cells', ['matrixRow' => $group, 'monitoringMode' => 'create', 'showLabel' => false])
                            </tr>
                            @foreach ($group['rows'] as $matrixRow)
                                <tr x-show="openGroups[{{ $groupIndex }}]" x-cloak class="{{ $rowBase }}">
                                    @include('incident-monitoring._monitoring-cells', ['matrixRow' => $matrixRow, 'monitoringMode' => 'create'])
                                </tr>
                            @endforeach
                        </tbody>
                    @endforeach
                    <tbody>
                        <tr class="{{ $totalRow }}">@include('incident-monitoring._monitoring-cells', ['matrixRow' => $totals, 'monitoringMode' => 'create'])</tr>
                    </tbody>
                @else
                    <tbody class="divide-y divide-ink-200 dark:divide-ink-700">
                        @foreach ([...array_values($matrixRows), $totals] as $matrixRow)
                            <tr class="{{ $matrixRow['key'] === '' ? $totalRow : $rowBase }}">@include('incident-monitoring._monitoring-cells', ['matrixRow' => $matrixRow, 'monitoringMode' => 'create'])</tr>
                        @endforeach
                    </tbody>
                @endif
            </table>
        </div>
        <div class="grid gap-2 border-t border-ink-100 bg-ink-50/70 px-4 py-3 text-xs leading-relaxed text-ink-600 dark:border-ink-800 dark:bg-ink-800/40 dark:text-ink-300 sm:grid-cols-2">
            <p><span class="font-semibold text-ink-800 dark:text-ink-100">Tiket Insera:</span> LOP yang terhubung dengan tiket yang bersumber dari Insera. Jumlahnya ditampilkan menurut jenis tiket, seperti SQM GAMAS, PREMAN, dan INFRACARE.</p>
            <p><span class="font-semibold text-ink-800 dark:text-ink-100">Tiket manual:</span> LOP yang dibuat manual tanpa tiket dari Insera (umumnya incident berawalan INP). Karena tidak memiliki jenis tiket, jumlahnya hanya masuk kolom TOTAL.</p>
        </div>
        </section>
    </div>

    <dialog x-ref="detailDialog" @click.self="closeDetail()" @keydown.escape.prevent.stop="closeDetail()" aria-label="Detail tiket monitoring" class="m-auto max-h-[90vh] w-[min(1120px,calc(100%_-_1.5rem))] overflow-hidden rounded-md border border-ink-200 bg-white p-0 text-ink-900 shadow-2xl backdrop:bg-ink-950/60 dark:border-ink-700 dark:bg-ink-900 dark:text-white">
        <div class="flex items-center justify-between gap-3 border-b border-ink-100 px-4 py-3 dark:border-ink-800 sm:px-5">
            <p class="truncate text-sm font-semibold" x-text="title">Detail tiket</p>
            <button type="button" @click="closeDetail()" aria-label="Tutup detail" title="Tutup detail" class="grid h-9 w-9 shrink-0 place-items-center rounded-md border border-ink-200 text-ink-600 hover:bg-ink-50 focus-visible:outline-2 focus-visible:outline-brand-500 dark:border-ink-700 dark:text-ink-300 dark:hover:bg-ink-800">
                <svg aria-hidden="true" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 6l12 12M18 6 6 18"/></svg>
            </button>
        </div>
        <div x-ref="detailBody" @click="handleDetailClick($event)" class="max-h-[calc(90vh_-_3.5rem)] overflow-y-auto p-4 sm:p-5">
            <div x-show="loading" role="status" class="rounded-md border border-ink-200 bg-white p-8 text-center text-sm text-ink-500 dark:border-ink-700 dark:bg-ink-900 dark:text-ink-400">Memuat detail tiket…</div>
            <div x-show="error" role="alert" class="rounded-md border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700 dark:border-rose-900/50 dark:bg-rose-950/20 dark:text-rose-300">
                <p x-text="error"></p>
                <button type="button" @click="openDetailFromUrl()" class="mt-3 rounded-md border border-rose-300 px-3 py-2 text-xs font-bold dark:border-rose-800">Coba lagi</button>
            </div>
            <div x-show="!loading && !error" x-html="html"></div>
        </div>
    </dialog>
</section>
