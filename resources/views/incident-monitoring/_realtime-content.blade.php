@php
    $baseQuery = array_filter($filters, fn ($value) => $value !== '');
    $detailReset = ['matrix_region' => '', 'detail_kind' => '', 'exclude_state' => 'all', 'reason' => ''];
    $detailUrl = fn (array $changes = []) => route('incident-monitoring.exclusions', array_filter(array_replace($baseQuery, $detailReset, $changes), fn ($value) => $value !== '')).'#ticket-details';
    $matrixGroupLabel = $groupBySto ? 'SERVICE AREA / STO' : ($groupByBranch ? 'REGIONAL / BRANCH' : 'REGIONAL');
    $matrixSummaryLabel = $groupBySto ? 'service area/STO' : ($groupByBranch ? 'regional/branch' : 'regional');
    $matrixUnit = $groupByBranch ? 'Branch' : 'STO';
    $isGrouped = ($groupBySto || $groupByBranch) && $matrixGroups !== [];
    $groupOpenDefault = count($matrixRows) <= 10;
    $groupOpenState = collect($matrixGroups)->mapWithKeys(fn (array $group, int $index) => [$index => $groupOpenDefault])->all();
    $visibleScopeLabel = $scopeLabel !== 'Seluruh wilayah' ? $scopeLabel : null;
    $captureName = implode('-', array_filter([
        'monitoring-tiket',
        $visibleScopeLabel,
        $filters['date_from'],
        $filters['date_to'],
    ], fn ($value) => $value !== null && $value !== ''));
@endphp

<section aria-labelledby="ticket-summary-heading" class="overflow-hidden rounded-md border border-ink-100 bg-white dark:border-ink-800 dark:bg-ink-900">
    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-ink-100 px-4 py-3 dark:border-ink-800" role="status" aria-live="polite">
        <span class="text-xs text-ink-500 dark:text-ink-400" x-text="monitoringRefreshMessage">Pembaruan otomatis setiap 30 detik · terakhir diperbarui {{ $checkedAt?->format('H:i:s') ?? 'belum tersedia' }} WIB</span>
        <button type="button" @click="refreshMonitoring(true)" :disabled="monitoringRefreshing" :aria-busy="monitoringRefreshing" title="Perbarui data sekarang" aria-label="Perbarui data Monitoring Tiket sekarang" class="inline-flex min-h-9 items-center rounded-md border border-ink-200 px-3 text-xs font-semibold text-ink-700 hover:bg-ink-50 focus-visible:outline-2 focus-visible:outline-brand-500 disabled:cursor-wait disabled:opacity-60 dark:border-ink-700 dark:text-ink-200 dark:hover:bg-ink-800">
            <span x-text="monitoringRefreshing ? 'Memperbarui…' : 'Perbarui'">Perbarui</span>
        </button>
    </div>
    <div class="space-y-5 p-4 sm:p-5">
        <h2 id="ticket-summary-heading" class="sr-only">Ringkasan tiket</h2>
        @if ($sourceUnavailable)
            <div role="alert" class="rounded-md border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800 dark:border-amber-800 dark:bg-amber-950/30 dark:text-amber-200">
                <p class="font-semibold">Data tiket Dompis belum dapat dimuat.</p>
                <p class="mt-1">Jumlah tiket belum dapat dipastikan. Data sebelumnya dipertahankan saat pembaruan otomatis gagal.</p>
            </div>
        @endif

        <section aria-label="Ringkasan status tiket" class="grid grid-cols-2 gap-3 lg:grid-cols-4">
            @foreach ([['total', 'Total tiket', 'all'], ['without_lop', 'Belum memiliki LOP', 'not_excluded'], ['with_lop', 'Sudah memiliki LOP', 'included'], ['excluded', 'Tiket Exclude', 'excluded']] as [$key, $label, $state])
                <a href="{{ $detailUrl(['exclude_state' => $state]) }}" class="rounded-md border bg-white p-4 transition hover:border-brand-300 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-500 dark:bg-ink-900 sm:p-5 {{ $filters['exclude_state'] === $state ? 'border-brand-300 dark:border-brand-600' : 'border-ink-100 dark:border-ink-800' }}">
                    <p class="text-sm font-medium text-ink-600 dark:text-ink-300">{{ $label }}</p>
                    <p class="mt-2 text-3xl font-semibold tracking-tight tabular-nums {{ $key === 'excluded' ? 'text-emerald-800 dark:text-emerald-300' : 'text-ink-900 dark:text-white' }}">{{ $stats === null ? '—' : number_format($stats[$key], 0, ',', '.') }}</p>
                    @if ($key === 'total')<p class="mt-1 text-xs text-ink-500 dark:text-ink-400">Klik untuk melihat seluruh tiket</p>@endif
                </a>
            @endforeach
        </section>

        <section aria-labelledby="ticket-chart-heading" class="border-t border-ink-100 pt-5 dark:border-ink-800">
            <div class="flex flex-wrap items-start justify-between gap-2">
                <div>
                    <h2 id="ticket-chart-heading" class="text-base font-semibold text-ink-900 dark:text-white">Jumlah tiket per jenis</h2>
                    <p class="mt-1 text-sm text-ink-500 dark:text-ink-400">Komposisi dari total tiket pada filter periode dan lokasi yang sama.</p>
                </div>
                @if ($filters['detail_kind'] !== '')<a href="{{ $detailUrl(['detail_kind' => '']) }}" class="inline-flex min-h-9 items-center text-sm font-medium text-brand-600 hover:underline dark:text-brand-300">Semua jenis</a>@endif
            </div>
            @if ($chartRows->isEmpty())
                <p class="py-5 text-sm text-ink-500 dark:text-ink-400">Tidak ada tiket yang sesuai filter.</p>
            @else
                <ul class="mt-3 space-y-1" aria-label="Jumlah tiket GAMAS, INFRACARE, dan PREMAN">
                    @foreach ($chartRows as $row)
                        <li><a href="{{ $detailUrl(['detail_kind' => $row['kind']]) }}" class="grid min-h-11 grid-cols-[100px_minmax(0,1fr)_64px] items-center gap-3 rounded-md px-2 py-2 hover:bg-ink-50 focus-visible:outline-2 focus-visible:outline-brand-500 dark:hover:bg-ink-800">
                            <span class="text-sm font-medium">{{ $row['label'] }}</span>
                            <span aria-hidden="true" class="h-3 rounded-sm bg-ink-50 dark:bg-ink-800"><span class="block h-full rounded-sm bg-brand-600 dark:bg-brand-400" style="width: {{ $row['width'] }}%"></span></span>
                            <span class="text-right text-sm font-semibold tabular-nums">{{ number_format($row['count'], 0, ',', '.') }}</span>
                        </a></li>
                    @endforeach
                </ul>
            @endif
        </section>
    </div>
</section>

@include('incident-monitoring._ticket-monitoring-tables')

<section id="ticket-details" aria-labelledby="ticket-details-title" class="scroll-mt-24 overflow-hidden rounded-md border border-ink-100 bg-white dark:border-ink-800 dark:bg-ink-900">
    <header class="flex flex-wrap items-start justify-between gap-3 p-4 sm:p-5">
        <div>
            <h2 id="ticket-details-title" class="text-base font-semibold">Daftar Tiket Dompis <span class="text-ink-500 dark:text-ink-400">({{ $sourceUnavailable ? '—' : number_format($tickets->total(), 0, ',', '.') }})</span></h2>
            <p class="mt-1 text-sm text-ink-600 dark:text-ink-300">{{ $detailLabel }}</p>
        </div>
        @if ($filters['matrix_region'] !== '' || $filters['detail_kind'] !== '' || $filters['exclude_state'] !== 'all' || $filters['reason'] !== '')
            <a href="{{ $detailUrl() }}" class="inline-flex min-h-10 items-center rounded-md border border-ink-200 px-3 text-sm focus-visible:outline-2 focus-visible:outline-brand-500 dark:border-ink-700">Semua detail sesuai periode</a>
        @endif
    </header>
    @if ($sourceUnavailable)
        <p class="px-5 pb-5 text-sm text-ink-500 dark:text-ink-400">Detail belum tersedia karena sumber tiket belum dapat dimuat.</p>
    @elseif ($tickets->isEmpty())
        <p role="status" class="px-5 pb-5 text-sm text-ink-500 dark:text-ink-400">Tidak ada tiket yang sesuai pilihan ini.</p>
    @else
        <div class="divide-y divide-ink-100 dark:divide-ink-800 lg:hidden">
            @foreach ($tickets as $ticket)
                <article class="space-y-3 p-4" data-incident="{{ $ticket['incident'] }}">
                    <div class="flex flex-wrap items-start justify-between gap-2">
                        <div><p class="font-semibold text-ink-900 dark:text-white">{{ $ticket['incident'] }}</p><p class="mt-1 text-xs text-ink-500 dark:text-ink-400">{{ $ticket['reported_date'] ?? 'Tanggal laporan belum terisi' }} · {{ $ticket['ticket_type'] }}</p></div>
                        <span class="rounded-md px-2 py-1 text-xs font-medium {{ $ticket['is_included'] ? 'bg-blue-50 text-blue-800 dark:bg-blue-950/50 dark:text-blue-200' : ($ticket['is_excluded'] ? 'bg-emerald-50 text-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-200' : 'bg-ink-100 text-ink-700 dark:bg-ink-800 dark:text-ink-200') }}">{{ $ticket['is_included'] ? 'Sudah include' : ($ticket['is_excluded'] ? 'Sudah exclude' : 'Belum exclude') }}</span>
                    </div>
                    <p class="text-sm text-ink-600 dark:text-ink-300">{{ $ticket['branch'] ?? 'Branch belum terpetakan' }} / {{ $ticket['workzone'] }}</p>
                    @if ($ticket['lop'])
                        <div class="space-y-1 text-sm"><p class="font-medium">{{ $ticket['lop']->nama_lop }}</p><x-lop-status-badge :status="$ticket['lop']->status_lop" /><div class="text-xs text-ink-500 dark:text-ink-400">ID IHLD: {{ $ticket['lop']->ihld_id ?: 'Belum diisi' }}</div><a href="{{ route('lop.detail', $ticket['lop'], false) }}" class="inline-flex min-h-10 items-center font-medium text-brand-600 hover:underline dark:text-brand-300">Detail LOP →</a></div>
                    @elseif ($ticket['is_included'])<p class="text-xs text-ink-500 dark:text-ink-400">LOP terarsip atau detail di luar scope</p>@endif
                    @if ($ticket['is_excluded'])<p class="text-sm text-ink-600 dark:text-ink-300">Alasan: {{ $ticket['reason_label'] }}</p>@endif
                    @if ($ticket['is_excluded'])<x-unexclude-ticket-button :incident="$ticket['incident']" />@elseif (! $ticket['is_included'])<x-exclude-ticket-form :incident="$ticket['incident']" />@endif
                </article>
            @endforeach
        </div>
        <div role="region" aria-label="Daftar detail tiket, dapat digeser horizontal" tabindex="0" class="hidden overflow-x-auto focus-visible:outline-2 focus-visible:outline-brand-500 lg:block">
            <table class="incident-ticket-table w-full min-w-[1744px] table-fixed text-left text-xs">
                <colgroup><col style="width: 8rem"><col style="width: 7rem"><col style="width: 10rem"><col style="width: 8rem"><col style="width: 18rem"><col style="width: 10rem"><col style="width: 12rem"><col style="width: 12rem"><col style="width: 10rem"><col style="width: 14rem"></colgroup>
                <thead class="bg-ink-50 text-[11px] font-bold uppercase tracking-wide text-ink-500 dark:bg-ink-800 dark:text-ink-300"><tr>
                    <th scope="col" class="px-3 py-3 text-center">Incident</th>
                    <th scope="col" class="px-3 py-3 text-center">Tanggal Laporan</th>
                    <th scope="col" class="px-3 py-3 text-center">Jenis Tiket</th>
                    <th scope="col" class="px-3 py-3 text-center">STO / Branch</th>
                    <th scope="col" class="px-3 py-3 text-center">LOP terkait</th>
                    <th scope="col" class="px-3 py-3 text-center">Status LOP</th>
                    <th scope="col" class="px-3 py-3 text-center">ID IHLD</th>
                    <th scope="col" class="px-3 py-3 text-center">Status exclude</th>
                    <th scope="col" class="px-3 py-3 text-center">Alasan</th>
                    <th scope="col" class="px-3 py-3 text-center">Aksi</th>
                </tr></thead>
                <tbody class="divide-y divide-ink-100 dark:divide-ink-800">
                    @foreach ($tickets as $ticket)
                        <tr class="align-middle transition-colors hover:bg-brand-50/40 dark:hover:bg-ink-800/50" data-incident="{{ $ticket['incident'] }}">
                            <td class="whitespace-nowrap px-3 py-3 font-mono font-semibold text-ink-900 dark:text-white">{{ $ticket['incident'] }}</td>
                            <td class="whitespace-nowrap px-3 py-3">{{ $ticket['reported_date'] ?? '—' }}</td>
                            <td class="break-words px-3 py-3 font-medium">{{ $ticket['ticket_type'] }}</td>
                            <td class="px-3 py-3"><span class="inline-flex rounded-sm bg-ink-100 px-1.5 py-0.5 font-mono font-semibold text-ink-700 dark:bg-ink-800 dark:text-ink-200">{{ $ticket['workzone'] }}</span><span class="mt-1 block text-[11px] leading-4 text-ink-500 dark:text-ink-400">{{ $ticket['branch'] ?? 'Belum terpetakan' }}</span></td>
                            <td class="min-w-0 px-3 py-3">@if ($ticket['lop'])<a href="{{ route('lop.detail', $ticket['lop'], false) }}" title="{{ $ticket['lop']->nama_lop }}" class="block max-w-full truncate font-medium leading-5 text-brand-600 hover:underline dark:text-brand-300">{{ $ticket['lop']->nama_lop }}</a>@elseif($ticket['is_included'])<span class="block truncate text-[11px] leading-4 text-ink-500 dark:text-ink-400" title="LOP terarsip atau detail di luar scope">LOP terarsip atau detail di luar scope</span>@else<span class="text-ink-400 dark:text-ink-500">—</span>@endif</td>
                            <td class="whitespace-nowrap px-3 py-3 text-center">@if($ticket['lop'])<x-lop-status-badge :status="$ticket['lop']->status_lop" />@else<span class="text-ink-400 dark:text-ink-500">—</span>@endif</td>
                            <td class="whitespace-nowrap px-3 py-3"><div class="flex justify-center">@if($ticket['lop'] && filled($ticket['lop']->ihld_id))<x-copyable-value :value="$ticket['lop']->ihld_id" label="ID IHLD" :nowrap="true" class="font-mono" />@else<span class="text-ink-400 dark:text-ink-500">{{ $ticket['lop'] ? 'Belum diisi' : '—' }}</span>@endif</div></td>
                            <td class="px-3 py-3 text-center"><span class="inline-flex min-w-28 justify-center whitespace-nowrap rounded-md px-2 py-1 text-[11px] font-semibold {{ $ticket['is_included'] ? 'bg-blue-50 text-blue-800 dark:bg-blue-950/50 dark:text-blue-200' : ($ticket['is_excluded'] ? 'bg-emerald-50 text-emerald-800 dark:bg-emerald-950/30 dark:text-emerald-200' : 'bg-ink-100 text-ink-700 dark:bg-ink-800 dark:text-ink-200') }}">{{ $ticket['is_included'] ? 'Sudah include' : ($ticket['is_excluded'] ? 'Sudah exclude' : 'Belum exclude') }}</span></td>
                            <td class="px-3 py-3 text-center">@if($ticket['is_excluded'])<span class="inline-flex min-w-24 justify-center whitespace-nowrap rounded-md bg-amber-50 px-2 py-1 text-[11px] font-medium text-amber-800 dark:bg-amber-950/30 dark:text-amber-200">{{ $ticket['reason_label'] }}</span>@else<span class="text-ink-400 dark:text-ink-500">—</span>@endif</td>
                            <td class="whitespace-nowrap px-3 py-3"><div class="flex justify-center">@if ($ticket['is_excluded'])<x-unexclude-ticket-button :incident="$ticket['incident']" :compact="true" />@elseif (! $ticket['is_included'])<x-exclude-ticket-form :incident="$ticket['incident']" :compact="true" />@else<span class="text-ink-400 dark:text-ink-500">—</span>@endif</div></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="border-t border-ink-100 p-4 dark:border-ink-800">{{ $tickets->links() }}</div>
    @endif
</section>
