@extends('layouts.app')

@section('title', 'Monitoring Incident')

@section('content')
@php
    $filterQuery = array_filter($filters, fn ($value) => $value !== '' && $value !== false);
    $filterUrl = fn (array $changes = []) => route('incident-monitoring.index', array_filter(array_replace($filterQuery, $changes), fn ($value) => $value !== '' && $value !== false));
@endphp

<div class="mx-auto max-w-7xl space-y-5" x-data="{ loading: false, filtersOpen: {{ count($filterLabels) ? 'true' : 'false' }} }" :aria-busy="loading" @pageshow.window="loading = false">
    <header class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight text-ink-900 dark:text-white">Monitoring Incident</h1>
            <p class="mt-1.5 text-sm text-ink-600 dark:text-ink-300">{{ $scopeLabel }}</p>
            <p class="mt-1 text-sm text-ink-500 dark:text-ink-400">Incident yang digunakan LOP dan status pekerjaan terkini.</p>
        </div>
        <p x-show="loading" x-cloak role="status" class="text-sm text-ink-600 dark:text-ink-300">Memuat monitoring…</p>
    </header>

    @include('incident-monitoring._tabs')

    @if ($scopeWarning)
        <div role="status" class="rounded-md border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800 dark:border-amber-800 dark:bg-amber-950/30 dark:text-amber-200">
            Scope wilayah akun belum diatur. Hubungi Super Admin untuk melengkapi akses.
        </div>
    @endif

    <section aria-label="Filter monitoring" class="rounded-md border border-ink-100 bg-white p-4 dark:border-ink-800 dark:bg-ink-900 sm:p-5">
        <button type="button" @click="filtersOpen = !filtersOpen" :aria-expanded="filtersOpen" aria-controls="incident-filters"
                class="mb-3 flex min-h-10 w-full items-center justify-between rounded-md text-sm font-medium focus-visible:outline-2 focus-visible:outline-brand-500 lg:hidden">
            Filter monitoring
            <span x-text="filtersOpen ? 'Tutup' : 'Buka'" class="text-ink-500 dark:text-ink-400">Buka</span>
        </button>
        <form id="incident-filters" method="GET" action="{{ route('incident-monitoring.index') }}" @submit="loading = true"
              :class="filtersOpen ? '' : 'hidden lg:block'">
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                <x-input name="q" id="incident-search" label="Cari incident, LOP, IHLD" :value="$filters['q']" :use-old="false" placeholder="Nomor atau nama…" maxlength="100" class="rounded-md! border-ink-200! dark:border-ink-700!" />
                <x-select name="program" label="Program" placeholder="Semua program" class="rounded-md! border-ink-200! dark:border-ink-700!">
                    @foreach (\App\Enums\ProgramType::cases() as $program)
                        <option value="{{ $program->value }}" @selected($filters['program'] === $program->value)>{{ $program->label() }}</option>
                    @endforeach
                </x-select>
                <x-select name="ticket_type" label="Jenis tiket" placeholder="Semua jenis tiket" class="rounded-md! border-ink-200! dark:border-ink-700!">
                    @foreach ($typeOptions as $type)
                        <option value="{{ $type['key'] }}" @selected($filters['ticket_type'] === $type['key'])>{{ $type['label'] }}</option>
                    @endforeach
                    @if ($filters['ticket_type'] !== '' && ! $typeOptions->contains('key', $filters['ticket_type']))
                        <option value="{{ $filters['ticket_type'] }}" selected>Jenis pilihan tidak tersedia</option>
                    @endif
                </x-select>
                <x-select name="status" label="Status LOP" placeholder="Semua status" class="rounded-md! border-ink-200! dark:border-ink-700!">
                    @foreach (\App\Enums\LopStatus::cases() as $status)
                        <option value="{{ $status->value }}" @selected($filters['status'] === $status->value)>{{ $status->label() }}</option>
                    @endforeach
                </x-select>
                <x-select name="region" label="Region" placeholder="Semua region" class="rounded-md! border-ink-200! dark:border-ink-700!">
                    @foreach ($regions as $region)
                        <option value="{{ $region }}" @selected($filters['region'] === $region)>{{ $region }}</option>
                    @endforeach
                    @if ($filters['region'] !== '' && ! $regions->contains($filters['region']))
                        <option value="{{ $filters['region'] }}" selected>Region di luar pilihan</option>
                    @endif
                </x-select>
                <x-select name="branch" label="Branch" placeholder="Semua branch" class="rounded-md! border-ink-200! dark:border-ink-700!">
                    @foreach ($branches as $branch)
                        <option value="{{ $branch->getKey() }}" @selected((string) $filters['branch'] === (string) $branch->getKey())>{{ $branch->name }}</option>
                    @endforeach
                    @if ($filters['branch'] !== '' && ! $branches->contains('id_branch', (int) $filters['branch']))
                        <option value="{{ $filters['branch'] }}" selected>Branch di luar pilihan</option>
                    @endif
                </x-select>
                <x-select name="service_area" label="Service Area / STO" placeholder="Semua STO" class="rounded-md! border-ink-200! dark:border-ink-700!">
                    @foreach ($serviceAreas as $serviceArea)
                        <option value="{{ $serviceArea->getKey() }}" @selected((string) $filters['service_area'] === (string) $serviceArea->getKey())>{{ $serviceArea->workzone }}</option>
                    @endforeach
                    @if ($filters['service_area'] !== '' && ! $serviceAreas->contains('id_service_area', (int) $filters['service_area']))
                        <option value="{{ $filters['service_area'] }}" selected>STO di luar pilihan</option>
                    @endif
                </x-select>
                <div class="flex items-end gap-2">
                    <x-button x-bind:disabled="loading" class="min-h-11! w-auto! flex-1 rounded-md! shadow-none! disabled:opacity-60"><span x-text="loading ? 'Memuat…' : 'Terapkan'">Terapkan</span></x-button>
                    <a href="{{ route('incident-monitoring.index') }}" @click="loading = true" class="inline-flex min-h-11 items-center rounded-md border border-ink-200 px-3 text-sm font-medium text-ink-600 hover:bg-ink-50 focus-visible:outline-2 focus-visible:outline-brand-500 dark:border-ink-700 dark:text-ink-300 dark:hover:bg-ink-800">Reset</a>
                </div>
            </div>
            <label class="mt-4 inline-flex min-h-9 cursor-pointer items-center gap-2 text-sm text-ink-600 dark:text-ink-300">
                <input type="checkbox" name="missing_ihld" value="1" @checked($filters['missing_ihld']) class="h-4 w-4 accent-brand-600">
                Hanya IHLD yang belum diisi
            </label>
        </form>
        @if ($filterLabels)
            <div class="mt-3 flex flex-wrap gap-2 border-t border-ink-100 pt-3 dark:border-ink-800" aria-label="Filter aktif">
                @foreach ($filterLabels as $key => $label)
                    <a href="{{ $filterUrl([$key => '']) }}" @click="loading = true" title="Hapus filter {{ $label }}" class="inline-flex min-h-8 max-w-full items-center gap-2 rounded-md border border-ink-200 px-2.5 py-1 text-xs text-ink-700 hover:border-brand-300 focus-visible:outline-2 focus-visible:outline-brand-500 dark:border-ink-700 dark:text-ink-200">
                        <span class="break-all">{{ $label }}</span><span aria-hidden="true">×</span><span class="sr-only">Hapus filter</span>
                    </a>
                @endforeach
            </div>
        @endif
    </section>

    @if ($sourceUnavailable)
        <div role="status" class="flex flex-col gap-3 rounded-md border border-amber-200 bg-amber-50 p-4 text-sm dark:border-amber-800 dark:bg-amber-950/30 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="font-medium text-amber-900 dark:text-amber-200">Sumber tiket sedang tidak tersedia.</p>
                <p class="mt-1 text-amber-800 dark:text-amber-300">Jenis yang belum dapat diperiksa ditandai Belum terverifikasi. Data dan status LOP tetap tersedia.</p>
            </div>
            <a href="{{ $filterUrl(['retry' => 1]) }}" @click="loading = true" class="inline-flex min-h-10 shrink-0 items-center justify-center rounded-md border border-amber-300 px-3 font-medium text-amber-900 hover:bg-amber-100 focus-visible:outline-2 focus-visible:outline-brand-500 dark:border-amber-700 dark:text-amber-200 dark:hover:bg-amber-900/30">Coba kembali</a>
        </div>
    @endif

    <section aria-label="Ringkasan sesuai filter" class="grid grid-cols-2 gap-3 lg:grid-cols-4">
        @foreach ([['total', 'Total LOP', 'Seluruh hasil filter'], ['completed', 'Completed', 'Pekerjaan selesai'], ['waiting_approval', 'Waiting Approval', 'Menunggu approval'], ['missing_ihld', 'Belum Ada IHLD', 'ID belum dilengkapi']] as [$key, $label, $description])
            <div class="rounded-md border border-ink-100 bg-white p-4 dark:border-ink-800 dark:bg-ink-900 sm:p-5">
                <p class="text-sm font-medium text-ink-600 dark:text-ink-300">{{ $label }}</p>
                <p class="mt-2 text-3xl font-semibold tracking-tight tabular-nums text-ink-900 dark:text-white" data-metric="{{ $key }}">{{ number_format($stats[$key], 0, ',', '.') }}</p>
                <p class="mt-1.5 text-xs text-ink-500 dark:text-ink-400">{{ $description }}</p>
            </div>
        @endforeach
    </section>

    <section aria-labelledby="incident-chart-heading" class="rounded-md border border-ink-100 bg-white p-4 dark:border-ink-800 dark:bg-ink-900 sm:p-5">
        <div class="flex flex-wrap items-start justify-between gap-2">
            <div>
                <h2 id="incident-chart-heading" class="text-base font-semibold text-ink-900 dark:text-white">Jumlah LOP per jenis tiket</h2>
                <p class="mt-1 text-sm text-ink-500 dark:text-ink-400">Sesuai filter aktif. Pilih batang untuk melihat rincian jenis tiket.</p>
            </div>
            @if ($filters['ticket_type'] !== '')
                <a href="{{ $filterUrl(['ticket_type' => '']) }}" @click="loading = true" class="inline-flex min-h-9 items-center text-sm font-medium text-brand-600 hover:underline focus-visible:outline-2 focus-visible:outline-brand-500 dark:text-brand-300">Semua jenis tiket</a>
            @endif
        </div>
        @if ($chartRows->isEmpty())
            <p class="py-8 text-center text-sm text-ink-500 dark:text-ink-400">Tidak ada LOP yang sesuai filter.</p>
        @else
            <ul class="mt-5 space-y-1" aria-label="Jumlah LOP menurut jenis tiket">
                @foreach ($chartRows as $row)
                    <li>
                        <a href="{{ $filterUrl(['ticket_type' => $row['key']]) }}" @click="loading = true" aria-label="{{ $row['label'] }}: {{ $row['count'] }} LOP. Filter jenis tiket."
                           class="grid min-h-12 grid-cols-[minmax(0,1fr)_auto] items-center gap-x-3 gap-y-1 rounded-md px-2 py-2 hover:bg-ink-50 focus-visible:outline-2 focus-visible:outline-brand-500 dark:hover:bg-ink-800 sm:grid-cols-[minmax(140px,220px)_minmax(0,1fr)_64px]">
                            <span class="wrap-break-word text-sm font-medium">{{ $row['label'] }}</span>
                            <span aria-hidden="true" class="order-3 col-span-2 h-3 rounded-sm bg-ink-50 dark:bg-ink-800 sm:order-0 sm:col-span-1 sm:h-4"><span class="block h-full rounded-sm bg-brand-600 dark:bg-brand-400" style="width: {{ $row['width'] }}%"></span></span>
                            <span class="text-right text-sm font-semibold tabular-nums">{{ number_format($row['count'], 0, ',', '.') }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
        <div class="mt-4 flex flex-wrap justify-between gap-2 border-t border-ink-100 pt-3 text-xs text-ink-500 dark:border-ink-800 dark:text-ink-400">
            <p>Tiket Manual = nomor INP yang dibuat di Dompis QE.</p>
            @if ($checkedAt)
                <p>Pengecekan jenis tiket: {{ $checkedAt->format('d/m/Y H:i') }} WIB · Cache maksimal 10 menit.</p>
            @endif
        </div>
    </section>

    <section aria-labelledby="incident-detail-heading" class="overflow-hidden rounded-md border border-ink-100 bg-white dark:border-ink-800 dark:bg-ink-900">
        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-ink-100 p-4 dark:border-ink-800 sm:px-5">
            <h2 id="incident-detail-heading" class="text-base font-semibold text-ink-900 dark:text-white">Detail penggunaan incident</h2>
            <span class="text-sm tabular-nums text-ink-500 dark:text-ink-400">{{ number_format($lops->total(), 0, ',', '.') }} LOP</span>
        </div>
        @if ($lops->isEmpty())
            <div class="px-5 py-10 text-center">
                <p class="text-sm font-medium">{{ $lops->total() > 0 ? 'Halaman ini tidak memiliki data.' : 'Tidak ada LOP yang sesuai filter.' }}</p>
                <a href="{{ $lops->total() > 0 ? $filterUrl() : route('incident-monitoring.index') }}" class="mt-3 inline-flex min-h-10 items-center text-sm font-medium text-brand-600 hover:underline dark:text-brand-300">{{ $lops->total() > 0 ? 'Kembali ke halaman pertama' : 'Reset filter' }}</a>
            </div>
        @else
            <div class="divide-y divide-ink-100 dark:divide-ink-800 lg:hidden">
                @foreach ($lops as $lop)
                    <article class="space-y-3 p-4">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <x-copyable-value :value="$lop->incident" label="Incident" class="text-sm" />
                            <x-badge :variant="$lop->status_lop->badgeVariant()">{{ $lop->status_lop->label() }}</x-badge>
                        </div>
                        <p class="wrap-break-word text-sm font-medium">{{ $lop->nama_lop }}</p>
                        <div class="flex flex-wrap items-center gap-2">
                            <x-badge variant="neutral">{{ $typesByLop[$lop->getKey()] }}</x-badge>
                            <span class="text-xs text-ink-500 dark:text-ink-400">{{ $lop->program_type->label() }}</span>
                        </div>
                        <dl class="grid grid-cols-2 gap-3 text-sm">
                            <div><dt class="text-xs text-ink-500 dark:text-ink-400">ID IHLD</dt><dd class="mt-1">@if (filled($lop->ihld_id))<x-copyable-value :value="$lop->ihld_id" label="ID IHLD" />@else<span class="text-ink-500 dark:text-ink-400">Belum diisi</span>@endif</dd></div>
                            <div><dt class="text-xs text-ink-500 dark:text-ink-400">Branch / STO</dt><dd class="mt-1 wrap-break-word">{{ $lop->locationBranchName() }}<span class="mt-1 block text-xs text-ink-500 dark:text-ink-400">{{ $lop->locationServiceAreaName() }}</span></dd></div>
                        </dl>
                        <a href="{{ route('lop.show', $lop) }}" class="inline-flex min-h-10 items-center text-sm font-medium text-brand-600 hover:underline focus-visible:outline-2 focus-visible:outline-brand-500 dark:text-brand-300" aria-label="Detail LOP {{ $lop->incident }}">Detail LOP <span aria-hidden="true" class="ml-1">→</span></a>
                    </article>
                @endforeach
            </div>
            <div class="hidden overflow-x-auto lg:block">
                <table class="w-full table-fixed text-left text-sm">
                    <caption class="sr-only">Incident, jenis tiket, LOP, ID IHLD, lokasi, dan status pekerjaan terkini.</caption>
                    <thead class="border-b border-ink-100 bg-ink-50 text-xs font-medium text-ink-600 dark:border-ink-800 dark:bg-ink-800/50 dark:text-ink-300">
                        <tr><th scope="col" class="w-[16%] px-4 py-3">Incident</th><th scope="col" class="w-[13%] px-3 py-3">Jenis tiket</th><th scope="col" class="w-[24%] px-3 py-3">Nama LOP</th><th scope="col" class="w-[15%] px-3 py-3">ID IHLD</th><th scope="col" class="w-[14%] px-3 py-3">Branch / STO</th><th scope="col" class="w-[12%] px-3 py-3">Status LOP</th><th scope="col" class="w-[6%] px-3 py-3">Aksi</th></tr>
                    </thead>
                    <tbody class="divide-y divide-ink-100 dark:divide-ink-800">
                        @foreach ($lops as $lop)
                            <tr class="align-top hover:bg-ink-50/70 dark:hover:bg-ink-800/30">
                                <td class="px-4 py-3"><x-copyable-value :value="$lop->incident" label="Incident" /></td>
                                <td class="wrap-break-word px-3 py-4">{{ $typesByLop[$lop->getKey()] }}</td>
                                <td class="px-3 py-4"><p class="line-clamp-2 wrap-break-word font-medium" title="{{ $lop->nama_lop }}">{{ $lop->nama_lop }}</p><p class="mt-1 text-xs text-ink-500 dark:text-ink-400">{{ $lop->program_type->label() }}</p></td>
                                <td class="px-3 py-3">@if (filled($lop->ihld_id))<x-copyable-value :value="$lop->ihld_id" label="ID IHLD" />@else<span class="inline-block py-1.5 text-ink-500 dark:text-ink-400">Belum diisi</span>@endif</td>
                                <td class="wrap-break-word px-3 py-4"><p>{{ $lop->locationBranchName() }}</p><p class="mt-1 text-xs text-ink-500 dark:text-ink-400">{{ $lop->locationServiceAreaName() }}</p></td>
                                <td class="px-3 py-4"><x-badge :variant="$lop->status_lop->badgeVariant()">{{ $lop->status_lop->label() }}</x-badge></td>
                                <td class="px-3 py-4"><a href="{{ route('lop.show', $lop) }}" class="inline-flex min-h-8 items-center font-medium text-brand-600 hover:underline focus-visible:outline-2 focus-visible:outline-brand-500 dark:text-brand-300" aria-label="Detail LOP {{ $lop->incident }}">Detail</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
        @if ($lops->hasPages())
            <div class="border-t border-ink-100 px-4 py-4 dark:border-ink-800 sm:px-5">{{ $lops->links() }}</div>
        @endif
    </section>
</div>
@endsection
