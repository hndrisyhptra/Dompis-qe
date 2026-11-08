@php
    $reportedFrom = \App\Support\TicketReportedDate::floor();
    $reportedFromLabel = \Illuminate\Support\Carbon::createFromFormat('!Y-m-d', $reportedFrom)->format('d/m/Y');
    $filterFormId = 'exclude-filters';
    $filterAction = route('incident-monitoring.exclusions', [], false);
    $filterReset = route('incident-monitoring.exclusions');
@endphp

<section aria-label="Filter Monitoring Incident" class="rounded-md border border-ink-100 bg-white p-4 dark:border-ink-800 dark:bg-ink-900 sm:p-5">
    <button type="button" @click="filtersOpen = !filtersOpen" :aria-expanded="filtersOpen" aria-controls="{{ $filterFormId }}"
            class="mb-3 flex min-h-10 w-full items-center justify-between rounded-md text-sm font-medium focus-visible:outline-2 focus-visible:outline-brand-500 lg:hidden">
        Filter monitoring <span x-text="filtersOpen ? 'Tutup' : 'Buka'" class="text-ink-500 dark:text-ink-400">Buka</span>
    </button>
    <form id="{{ $filterFormId }}" method="GET" action="{{ $filterAction }}"
          @submit="if (!$event.submitter?.hasAttribute('data-ticket-download')) loading = true"
          :class="filtersOpen ? '' : 'hidden lg:block'" class="space-y-4">
        @foreach (['matrix_region', 'detail_kind', 'exclude_state', 'reason'] as $detailFilter)
            <input type="hidden" name="{{ $detailFilter }}" value="{{ $filters[$detailFilter] }}">
        @endforeach
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
            <x-select name="period" label="Periode laporan" x-model="period">
                <option value="range" @selected($filters['period'] === 'range')>Rentang tanggal</option>
                <option value="month" @selected($filters['period'] === 'month')>Bulanan</option>
            </x-select>
            <div x-show="period === 'month'" @if ($filters['period'] !== 'month') x-cloak @endif>
                <x-input name="month" type="month" label="Bulan laporan" :value="$filters['month']" :use-old="false" min="{{ substr($reportedFrom, 0, 7) }}" x-bind:disabled="period !== 'month'" />
            </div>
            <div x-show="period === 'range'" @if ($filters['period'] !== 'range') x-cloak @endif>
                <x-input name="date_from" type="date" label="Tanggal laporan dari" :value="$filters['date_from']" :use-old="false" min="{{ $reportedFrom }}" x-model="dateFrom" x-bind:disabled="period !== 'range'" />
            </div>
            <div x-show="period === 'range'" @if ($filters['period'] !== 'range') x-cloak @endif>
                <x-input name="date_to" type="date" label="Tanggal laporan sampai" :value="$filters['date_to']" :use-old="false" min="{{ $reportedFrom }}" x-bind:min="dateFrom || '{{ $reportedFrom }}'" x-bind:disabled="period !== 'range'" />
            </div>
            <x-input name="q" label="Cari incident / STO" :value="$filters['q']" :use-old="false" placeholder="Nomor incident atau STO…" maxlength="100" />
            <x-select name="kind" label="Jenis tiket" placeholder="Semua jenis tiket">
                <option value="gamas" @selected($filters['kind'] === 'gamas')>GAMAS</option>
                <option value="infracare" @selected($filters['kind'] === 'infracare')>INFRACARE</option>
                <option value="preman" @selected($filters['kind'] === 'preman')>PREMAN</option>
            </x-select>
            <x-select name="region" label="Region" placeholder="Semua region">
                @foreach ($regions as $region)
                    <option value="{{ $region }}" @selected($filters['region'] === $region)>{{ $region }}</option>
                @endforeach
                @if ($filters['region'] !== '' && ! $regions->contains($filters['region']))<option value="{{ $filters['region'] }}" selected>Region di luar pilihan</option>@endif
            </x-select>
            <x-select name="branch" label="Branch" placeholder="Semua branch">
                @foreach ($branches as $branch)
                    <option value="{{ $branch->getKey() }}" @selected((string) $filters['branch'] === (string) $branch->getKey())>{{ $branch->name }}</option>
                @endforeach
                @if ($filters['branch'] !== '' && ! $branches->contains('id_branch', (int) $filters['branch']))<option value="{{ $filters['branch'] }}" selected>Branch di luar pilihan</option>@endif
            </x-select>
            <x-select name="service_area_name" label="Nama Service Area" placeholder="Semua nama Service Area">
                @foreach ($serviceAreaNames as $serviceAreaName)
                    <option value="{{ $serviceAreaName }}" @selected($filters['service_area_name'] === $serviceAreaName)>{{ $serviceAreaName }}</option>
                @endforeach
                @if ($filters['service_area_name'] !== '' && ! $serviceAreaNames->contains($filters['service_area_name']))<option value="{{ $filters['service_area_name'] }}" selected>Service Area di luar pilihan</option>@endif
            </x-select>
            <label>
                <span class="mb-1.5 block text-xs font-extrabold uppercase tracking-wider text-ink-500">Filter beberapa STO</span>
                <x-sto-filter :branches="$branches" :selected="request('sto_filter', [])" />
            </label>
            <div class="flex items-end gap-2">
                <button type="submit" x-bind:disabled="loading" class="flex min-h-11 flex-1 items-center justify-center rounded-md bg-brand-600 px-4 text-sm font-bold text-white transition hover:bg-brand-700 disabled:opacity-60"><span x-text="loading ? 'Memuat…' : 'Terapkan'">Terapkan</span></button>
                <a href="{{ $filterReset }}" @click="loading = true" class="inline-flex min-h-11 items-center rounded-md border border-ink-200 px-3 text-sm font-medium text-ink-700 hover:bg-ink-50 dark:border-ink-700 dark:text-ink-300 dark:hover:bg-ink-800">Reset</a>
            </div>
        </div>
        @foreach ($errors->all() as $error)<p role="alert" class="text-sm text-red-600 dark:text-red-400">{{ $error }}</p>@endforeach
        <p class="text-xs text-ink-500 dark:text-ink-400">Pilih nama Service Area untuk menampilkan seluruh STO yang menggunakan nama tersebut. Periode berdasarkan reported date tiket Dompis, mulai {{ $reportedFromLabel }}; mode bulanan mencakup satu bulan penuh.</p>
    </form>
    @php
        $sharedActiveFilters = array_filter([
            $filters['q'] !== '' ? 'Pencarian: '.$filters['q'] : null,
            $filters['kind'] !== '' ? strtoupper($filters['kind']) : null,
            $filters['region'] !== '' ? $filters['region'] : null,
            $filters['branch'] !== '' ? 'Branch pilihan' : null,
            $filters['service_area_name'] !== '' ? 'Service Area: '.$filters['service_area_name'] : null,
            $filters['service_area'] !== '' ? 'STO: '.($serviceAreas->firstWhere('id_service_area', (int) $filters['service_area'])?->workzone ?? 'di luar scope') : null,
            ! empty($filters['sto_filter']) ? count($filters['sto_filter']).' STO dipilih' : null,
            $filters['period'] === 'month' ? 'Bulan '.$filters['month'] : ($filters['date_from'].' – '.$filters['date_to']),
        ]);
    @endphp
    @if ($sharedActiveFilters)
        <div class="mt-3 flex flex-wrap gap-2 border-t border-ink-100 pt-3 dark:border-ink-800" aria-label="Filter aktif">
            @foreach ($sharedActiveFilters as $activeFilter)
                <span class="inline-flex min-h-8 items-center rounded-full border border-ink-200 bg-ink-50 px-2.5 text-xs text-ink-700 dark:border-ink-700 dark:bg-ink-800 dark:text-ink-200">{{ $activeFilter }}</span>
            @endforeach
        </div>
    @endif
</section>
