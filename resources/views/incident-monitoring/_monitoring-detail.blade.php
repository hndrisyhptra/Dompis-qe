{{-- Fragment detail untuk angka tabel Monitoring Tiket. Dimuat ke dalam dialog lewat fetch. --}}
<div class="space-y-4">
    <header class="flex flex-wrap items-start justify-between gap-3">
        <div class="min-w-0">
            <h2 class="text-base font-semibold text-ink-900 dark:text-white">{{ $cellLabel }}</h2>
            <p class="mt-1 text-sm text-ink-600 dark:text-ink-300">{{ $detailLabel }}</p>
        </div>
        <p class="text-sm font-semibold tabular-nums text-ink-700 dark:text-ink-200">
            {{ number_format($isManual ? $manualLops->total() : $tickets->total(), 0, ',', '.') }} data
        </p>
    </header>

    @if ($sourceUnavailable)
        <p role="status" class="rounded-md border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800 dark:border-amber-800 dark:bg-amber-950/30 dark:text-amber-200">Detail belum tersedia karena sumber tiket Dompis belum dapat dimuat.</p>
    @elseif ($isManual)
        @if ($manualLops->isEmpty())
            <p role="status" class="rounded-md border border-dashed border-ink-200 p-6 text-center text-sm text-ink-500 dark:border-ink-700 dark:text-ink-400">Tidak ada LOP manual pada pilihan ini.</p>
        @else
            @php
                $rows = $manualLops->getCollection()->map(fn ($lop) => [
                    'incident' => $lop->incident,
                    'nama_lop' => $lop->nama_lop ?: 'Tanpa nama',
                    'program' => $lop->program_type?->label() ?? '—',
                    'branch' => $lop->branchRef?->name ?? ($lop->branch ?: 'Belum terpetakan'),
                    'workzone' => $lop->serviceArea?->workzone ?? ($lop->sto ?: '—'),
                    'reported_date' => $lop->reported_date ? \Illuminate\Support\Carbon::parse($lop->reported_date)->format('d/m/Y') : '—',
                    'status_label' => $lop->status_lop->label(),
                    'status_variant' => $lop->status_lop->badgeVariant(),
                    'detail_url' => route('lop.detail', $lop, false),
                ]);
            @endphp
            @include('incident-monitoring._monitoring-lop-table', ['rows' => $rows])
            <div data-monitoring-pagination class="border-t border-ink-100 pt-3 dark:border-ink-800">{{ $manualLops->links() }}</div>
        @endif
    @elseif ($isLopList)
        @if ($tickets->isEmpty())
            <p role="status" class="rounded-md border border-dashed border-ink-200 p-6 text-center text-sm text-ink-500 dark:border-ink-700 dark:text-ink-400">Tidak ada tiket yang sudah memiliki LOP pada pilihan ini.</p>
        @else
            @php
                $rows = $tickets->getCollection()->map(function (array $ticket): array {
                    $lop = $ticket['lop'];
                    $name = $lop?->nama_lop;

                    return [
                        'incident' => $ticket['incident'],
                        'nama_lop' => filled($name) ? $name : ($ticket['is_included'] ? 'LOP terarsip atau di luar scope' : '—'),
                        'program' => $lop?->program_type?->label() ?? '—',
                        'branch' => $ticket['branch'] ?? 'Belum terpetakan',
                        'workzone' => $ticket['workzone'],
                        'reported_date' => $ticket['reported_date'] ?? '—',
                        'status_label' => $lop?->status_lop?->label(),
                        'status_variant' => $lop?->status_lop?->badgeVariant(),
                        'detail_url' => $lop ? route('lop.detail', $lop, false) : null,
                    ];
                });
            @endphp
            @include('incident-monitoring._monitoring-lop-table', ['rows' => $rows])
            <div data-monitoring-pagination class="border-t border-ink-100 pt-3 dark:border-ink-800">{{ $tickets->links() }}</div>
        @endif
    @else
        @if ($tickets->isEmpty())
            <p role="status" class="rounded-md border border-dashed border-ink-200 p-6 text-center text-sm text-ink-500 dark:border-ink-700 dark:text-ink-400">Tidak ada tiket yang sesuai pilihan ini.</p>
        @else
            <div role="region" aria-label="Daftar detail tiket" tabindex="0" class="overflow-x-auto rounded-md border border-ink-100 focus-visible:outline-2 focus-visible:outline-brand-500 dark:border-ink-800">
                <table class="incident-ticket-table w-full min-w-[100rem] table-fixed text-left text-xs">
                    <colgroup><col style="width: 9rem"><col style="width: 8rem"><col style="width: 10rem"><col style="width: 13rem"><col style="width: 16rem"><col style="width: 9rem"><col style="width: 10rem"><col style="width: 11rem"><col style="width: 17rem"></colgroup>
                    <thead class="bg-ink-50 text-[11px] font-bold uppercase tracking-wide text-ink-500 dark:bg-ink-800 dark:text-ink-300">
                        <tr>
                            <th scope="col" class="px-3 py-3 text-center">Incident</th>
                            <th scope="col" class="px-3 py-3 text-center">Tanggal Laporan</th>
                            <th scope="col" class="px-3 py-3 text-center">Jenis Tiket</th>
                            <th scope="col" class="px-3 py-3 text-center">STO / Branch</th>
                            <th scope="col" class="px-3 py-3 text-center">LOP terkait</th>
                            <th scope="col" class="px-3 py-3 text-center">Status LOP</th>
                            <th scope="col" class="px-3 py-3 text-center">Status exclude</th>
                            <th scope="col" class="px-3 py-3 text-center">Alasan</th>
                            <th scope="col" class="px-3 py-3 text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-ink-100 dark:divide-ink-800">
                        @foreach ($tickets as $ticket)
                            <tr class="align-middle hover:bg-brand-50/40 dark:hover:bg-ink-800/50">
                                <td class="whitespace-nowrap px-3 py-3 font-mono font-semibold text-ink-900 dark:text-white">{{ $ticket['incident'] }}</td>
                                <td class="whitespace-nowrap px-3 py-3 text-center">{{ $ticket['reported_date'] ?? '—' }}</td>
                                <td class="px-3 py-3 font-medium">{{ $ticket['ticket_type'] }}</td>
                                <td class="px-3 py-3">
                                    <span class="inline-flex rounded-sm bg-ink-100 px-1.5 py-0.5 font-mono font-semibold text-ink-700 dark:bg-ink-800 dark:text-ink-200">{{ $ticket['workzone'] }}</span>
                                    <span class="mt-1 block text-[11px] leading-4 text-ink-500 dark:text-ink-400">{{ $ticket['branch'] ?? 'Belum terpetakan' }}</span>
                                </td>
                                <td class="px-3 py-3">@if ($ticket['lop'])<a href="{{ route('lop.detail', $ticket['lop'], false) }}" title="{{ $ticket['lop']->nama_lop }}" class="block max-w-full truncate font-medium text-brand-600 hover:underline dark:text-brand-300">{{ $ticket['lop']->nama_lop }}</a>@elseif ($ticket['is_included'])<span class="text-[11px] text-ink-500 dark:text-ink-400">LOP terarsip atau detail di luar scope</span>@else<span class="text-ink-400 dark:text-ink-500">—</span>@endif</td>
                                <td class="whitespace-nowrap px-3 py-3 text-center">@if ($ticket['lop'])<x-badge :variant="$ticket['lop']->status_lop->badgeVariant()">{{ $ticket['lop']->status_lop->label() }}</x-badge>@else<span class="text-ink-400 dark:text-ink-500">—</span>@endif</td>
                                <td class="px-3 py-3 text-center"><span class="inline-flex min-w-28 justify-center whitespace-nowrap rounded-md px-2 py-1 text-[11px] font-semibold {{ $ticket['is_included'] ? 'bg-blue-50 text-blue-800 dark:bg-blue-950/50 dark:text-blue-200' : ($ticket['is_excluded'] ? 'bg-emerald-50 text-emerald-800 dark:bg-emerald-950/30 dark:text-emerald-200' : 'bg-ink-100 text-ink-700 dark:bg-ink-800 dark:text-ink-200') }}">{{ $ticket['is_included'] ? 'Sudah include' : ($ticket['is_excluded'] ? 'Sudah exclude' : 'Belum exclude') }}</span></td>
                                <td class="px-3 py-3 text-center">@if ($ticket['is_excluded'])<span class="inline-flex min-w-24 justify-center whitespace-nowrap rounded-md bg-amber-50 px-2 py-1 text-[11px] font-medium text-amber-800 dark:bg-amber-950/30 dark:text-amber-200">{{ $ticket['reason_label'] }}</span>@else<span class="text-ink-400 dark:text-ink-500">—</span>@endif</td>
                                <td class="px-3 py-3">
                                    <div class="flex min-w-64 flex-nowrap items-center justify-center gap-2">
                                        @if ($ticket['lop'])
                                            <a href="{{ route('lop.detail', $ticket['lop'], false) }}" aria-label="Buka LOP {{ $ticket['incident'] }}" title="Buka LOP" class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-md border border-brand-200 bg-brand-50 text-brand-700 hover:bg-brand-100 focus-visible:outline-2 focus-visible:outline-brand-500 dark:border-brand-900 dark:bg-brand-950/30 dark:text-brand-300"><svg aria-hidden="true" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M14.25 3.75H6.75A2.25 2.25 0 0 0 4.5 6v12a2.25 2.25 0 0 0 2.25 2.25h10.5A2.25 2.25 0 0 0 19.5 18V9m-5.25-5.25L19.5 9m-5.25-5.25V9h5.25M8.25 13.5h7.5m-7.5 3h7.5"/></svg></a>
                                        @elseif ($ticket['is_included'])
                                            <span class="text-[11px] text-ink-500 dark:text-ink-400">LOP tidak tersedia</span>
                                        @elseif ($ticket['is_excluded'])
                                            <x-unexclude-ticket-button :incident="$ticket['incident']" :compact="true" />
                                        @else
                                            @can('create', \App\Models\QeLop::class)
                                                <a href="{{ route('lop.create', ['incident' => $ticket['incident']]) }}" aria-label="Buat LOP untuk {{ $ticket['incident'] }}" title="Buat LOP" class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-md bg-brand-600 text-white hover:bg-brand-700 focus-visible:outline-2 focus-visible:outline-brand-500"><svg aria-hidden="true" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 5.25v13.5m6.75-6.75H5.25"/></svg></a>
                                            @endcan
                                            <x-exclude-ticket-form :incident="$ticket['incident']" :compact="true" />
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div data-monitoring-pagination class="border-t border-ink-100 pt-3 dark:border-ink-800">{{ $tickets->links() }}</div>
        @endif
    @endif
</div>
