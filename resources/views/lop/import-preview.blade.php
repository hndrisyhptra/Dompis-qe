@extends('layouts.app')

@section('title', 'Preview Import LOP')

@section('content')
<div class="mx-auto max-w-6xl"
     x-data="{
        program_type: @js(old('program_type', $preview['lop']['program_type'])),
        area: @js(old('area', $preview['lop']['area'])),
        branch: @js(old('branch', $preview['lop']['branch'])),
        sto: @js(old('sto', $preview['lop']['sto'])),
        areas: @js($areas->map(fn($a) => ['code' => $a->code, 'name' => $a->name])),
        branches: @js($branches->map(fn($b) => ['name' => $b->name, 'area_code' => $b->regionRef?->area?->code])),
        serviceAreas: @js($serviceAreas->map(fn($sa) => ['workzone' => $sa->workzone, 'name' => $sa->name, 'branch' => $sa->branch?->name])),
        get filteredBranches() { if (!this.area) return []; return this.branches.filter(b => String(b.area_code ?? '') === String(this.area)); },
        get filteredServiceAreas() { if (!this.branch) return []; return this.serviceAreas.filter(sa => String(sa.branch ?? '') === String(this.branch)); },
        onAreaChange() { if (!this.filteredBranches.some(b => b.name === this.branch)) { this.branch = ''; this.sto = ''; } },
        onBranchChange() { if (!this.filteredServiceAreas.some(sa => sa.workzone === this.sto)) { this.sto = ''; } },
     }">
    <div class="mb-6">
        <a href="{{ route('lop.import.form') }}" class="text-sm font-medium text-ink-600 dark:text-ink-300 hover:text-ink-900 dark:hover:text-ink-50">
            &larr; Upload ulang
        </a>
        <h1 class="text-xl font-bold text-ink-900 dark:text-ink-50 mt-2">Preview Import — {{ $fileName }}</h1>
        <p class="text-sm text-ink-500 dark:text-ink-400 mt-1">Periksa hasil pembacaan file. VOL kosong = tidak dipakai ({{ $preview['totals']['skipped_count'] }} baris).</p>
    </div>

    @if ($preview['errors'])
        <div class="mb-4 rounded-lg bg-brand-50 dark:bg-brand-900/30 border border-brand-200 dark:border-brand-800 px-4 py-3">
            <p class="text-sm text-brand-700 dark:text-brand-300 font-medium mb-1">Belum bisa diimpor — perbaiki dulu:</p>
            <ul class="text-sm text-brand-700 dark:text-brand-300 list-disc list-inside space-y-0.5 max-h-48 overflow-y-auto">
                @foreach ($preview['errors'] as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        </div>
    @endif

    @if ($preview['warnings'])
        <div class="mb-4 rounded-lg bg-amber-50 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-800 px-4 py-3">
            <p class="text-sm text-amber-700 dark:text-amber-300 font-medium mb-1">Perhatian:</p>
            <ul class="text-sm text-amber-700 dark:text-amber-300 list-disc list-inside space-y-0.5">
                @foreach ($preview['warnings'] as $warning)<li>{{ $warning }}</li>@endforeach
            </ul>
        </div>
    @endif

    @if ($errors->any())
        <div class="mb-4 rounded-lg bg-brand-50 dark:bg-brand-900/30 border border-brand-200 dark:border-brand-800 px-4 py-3">
            <ul class="text-sm text-brand-700 dark:text-brand-300 list-disc list-inside space-y-0.5">
                @foreach ($errors->all() as $message)<li>{{ $message }}</li>@endforeach
            </ul>
        </div>
    @endif

    {{-- Ringkasan total --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-6">
        <div class="rounded-xl border border-ink-100 bg-white p-4 dark:border-ink-800 dark:bg-ink-900">
            <p class="text-xs text-ink-400">Material</p>
            <p class="text-lg font-extrabold text-ink-900 dark:text-white">{{ $preview['totals']['material_count'] }} item</p>
            <p class="text-sm font-bold text-ink-700 dark:text-ink-200">Rp {{ number_format($preview['totals']['material_total'], 0, ',', '.') }}</p>
        </div>
        <div class="rounded-xl border border-ink-100 bg-white p-4 dark:border-ink-800 dark:bg-ink-900">
            <p class="text-xs text-ink-400">Jasa</p>
            <p class="text-lg font-extrabold text-ink-900 dark:text-white">{{ $preview['totals']['jasa_count'] }} item</p>
            <p class="text-sm font-bold text-ink-700 dark:text-ink-200">Rp {{ number_format($preview['totals']['jasa_total'], 0, ',', '.') }}</p>
        </div>
        <div class="rounded-xl border border-brand-200 bg-brand-50/50 p-4 dark:border-brand-800 dark:bg-brand-950/20 col-span-2">
            <p class="text-xs text-ink-400">Grand Total (M + J)</p>
            <p class="text-lg font-extrabold text-brand-700 dark:text-brand-300">Rp {{ number_format($preview['totals']['grand_total'], 0, ',', '.') }}</p>
            <p class="text-xs text-ink-500 dark:text-ink-400 mt-1">
                {{ $preview['totals']['used_count'] }} dipakai
                · Paket Excel: {{ $preview['package']['detected'] ?? '—' }}
                @if (!$preview['package']['exists']) (belum ada di DB — harga dari file)@endif
            </p>
        </div>
    </div>

    <form method="POST" action="{{ route('lop.import.store') }}" class="space-y-5">
        @csrf

        <x-card>
            <h2 class="text-sm font-bold text-ink-900 dark:text-white mb-4">Data LOP</h2>
            <div class="grid gap-5 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <x-input name="nama_lop" label="Nama LOP (Project)" :value="old('nama_lop', $preview['lop']['nama_lop'])" />
                </div>
                <div>
                    <x-input name="incident" label="Incident (kosongkan = generate INP otomatis)" placeholder="INP..." :value="old('incident', $preview['lop']['incident'])" />
                    <p class="mt-1.5 text-xs text-ink-400">Format INP + angka, atau kosongkan.</p>
                </div>
                <div>
                    <x-input name="job_description" label="Deskripsi Pekerjaan" :value="old('job_description', $preview['lop']['job_description'])" />
                </div>
                <div>
                    <x-select name="area" label="Area" placeholder="Pilih area" x-model="area" @change="onAreaChange()">
                        @foreach ($areas as $area)<option value="{{ $area->code }}">{{ $area->name }}</option>@endforeach
                    </x-select>
                </div>
                <div>
                    <label for="branch" class="block text-sm font-medium text-ink-700 dark:text-ink-300 mb-1.5">Branch</label>
                    <select id="branch" name="branch" x-model="branch" @change="onBranchChange()" :disabled="!area"
                        class="w-full rounded-lg border border-ink-100 dark:border-ink-700 bg-white dark:bg-ink-800 px-3.5 py-2.5 text-sm text-ink-900 dark:text-ink-50 shadow-sm focus:outline-none focus:ring-2 focus:ring-brand-500/30 focus:border-brand-500 transition disabled:bg-ink-50 disabled:cursor-not-allowed">
                        <option value="">Pilih branch</option>
                        <template x-for="b in filteredBranches" :key="b.name"><option :value="b.name" x-text="b.name"></option></template>
                    </select>
                    @error('branch')<p class="mt-1.5 text-sm text-brand-600 dark:text-brand-400">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="sto" class="block text-sm font-medium text-ink-700 dark:text-ink-300 mb-1.5">STO / Service Area</label>
                    <select id="sto" name="sto" x-model="sto" :disabled="!branch"
                        class="w-full rounded-lg border border-ink-100 dark:border-ink-700 bg-white dark:bg-ink-800 px-3.5 py-2.5 text-sm text-ink-900 dark:text-ink-50 shadow-sm focus:outline-none focus:ring-2 focus:ring-brand-500/30 focus:border-brand-500 transition disabled:bg-ink-50 disabled:cursor-not-allowed">
                        <option value="">Pilih STO</option>
                        <template x-for="sa in filteredServiceAreas" :key="sa.workzone"><option :value="sa.workzone" x-text="`${sa.workzone} — ${sa.name}`"></option></template>
                    </select>
                    @error('sto')<p class="mt-1.5 text-sm text-brand-600 dark:text-brand-400">{{ $message }}</p>@enderror
                </div>
                <div>
                    <x-select name="program_type" label="Program" placeholder="Pilih program" x-model="program_type">
                        @foreach ($programTypes as $type)<option value="{{ $type->value }}">{{ $type->label() }}</option>@endforeach
                    </x-select>
                </div>
                <div x-show="program_type !== 'relok_utilitas'">
                    <x-select name="segment" label="Segmen" placeholder="Pilih segmen" x-bind:disabled="program_type === 'relok_utilitas'">
                        @foreach ($segments as $segment)<option value="{{ $segment->value }}">{{ $segment->label() }}</option>@endforeach
                    </x-select>
                </div>
                <div x-show="program_type === 'relok_utilitas'" x-cloak>
                    <span class="block text-sm font-medium text-ink-700 dark:text-ink-300 mb-1.5">Segmen (maks 3)</span>
                    <div class="flex flex-wrap gap-2">
                        @foreach ($segments as $segment)
                            <label class="inline-flex items-center gap-1.5 rounded-full border border-ink-200 px-3 py-1.5 text-xs font-semibold cursor-pointer dark:border-ink-700">
                                <input type="checkbox" name="segment[]" value="{{ $segment->value }}" x-bind:disabled="program_type !== 'relok_utilitas'" class="h-3.5 w-3.5 rounded border-ink-300 text-brand-600 focus:ring-brand-500/30">
                                {{ $segment->label() }}
                            </label>
                        @endforeach
                    </div>
                    @error('segment')<p class="mt-1.5 text-sm text-brand-600 dark:text-brand-400">{{ $message }}</p>@enderror
                </div>
                <div x-show="program_type === 'relok_utilitas'" x-cloak>
                    <span class="block text-sm font-medium text-ink-700 dark:text-ink-300 mb-1.5">Jenis Anggaran</span>
                    <div class="flex gap-4">
                        @foreach ($budgetTypes as $budgetType)
                            <label class="inline-flex items-center gap-1.5 text-sm cursor-pointer">
                                <input type="radio" name="budget_type" value="{{ $budgetType->value }}" class="h-4 w-4 text-brand-600 focus:ring-brand-500/30">
                                {{ $budgetType->label() }}
                            </label>
                        @endforeach
                    </div>
                    @error('budget_type')<p class="mt-1.5 text-sm text-brand-600 dark:text-brand-400">{{ $message }}</p>@enderror
                </div>
                <div>
                    <x-select name="package_code" label="Paket Harga">
                        @foreach ($packages as $package)<option value="{{ $package->code }}" @selected(old('package_code', $preview['package']['chosen']) == $package->code)>Paket {{ $package->code }} — {{ $package->name }}</option>@endforeach
                    </x-select>
                    @error('package_code')<p class="mt-1.5 text-sm text-brand-600 dark:text-brand-400">{{ $message }}</p>@enderror
                </div>
            </div>
        </x-card>

        <x-card>
            <h2 class="text-sm font-bold text-ink-900 dark:text-white mb-1">Baris Designator</h2>
            <p class="text-xs text-ink-400 mb-4">Abu-abu = VOL kosong (tidak dipakai). Kuning = designator baru (akan dibuat otomatis).</p>
            <div class="max-h-[480px] overflow-y-auto rounded-xl border border-ink-100 dark:border-ink-800">
                <table class="w-full min-w-[720px] text-left text-xs">
                    <thead class="sticky top-0 bg-ink-50 dark:bg-ink-800">
                        <tr>
                            <th class="px-3 py-2.5 font-bold text-ink-600 dark:text-ink-300">Baris</th>
                            <th class="px-3 py-2.5 font-bold text-ink-600 dark:text-ink-300">Designator</th>
                            <th class="px-3 py-2.5 font-bold text-ink-600 dark:text-ink-300">Uraian</th>
                            <th class="px-3 py-2.5 text-right font-bold text-ink-600 dark:text-ink-300">Harga</th>
                            <th class="px-3 py-2.5 text-right font-bold text-ink-600 dark:text-ink-300">VOL</th>
                            <th class="px-3 py-2.5 text-right font-bold text-ink-600 dark:text-ink-300">Total</th>
                            <th class="px-3 py-2.5 font-bold text-ink-600 dark:text-ink-300">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-ink-100 dark:divide-ink-800">
                        @foreach ($preview['rows'] as $row)
                            <tr class="{{ $row['status'] === 'skipped' ? 'bg-ink-50/60 dark:bg-ink-800/30 text-ink-400' : '' }} {{ $row['status'] === 'error' ? 'bg-brand-50/50 dark:bg-brand-950/20' : '' }}">
                                <td class="px-3 py-2 font-mono">{{ $row['line'] }}</td>
                                <td class="px-3 py-2 font-mono font-bold">
                                    {{ $row['designator'] }}
                                    <span class="ml-1 rounded px-1.5 py-0.5 text-[10px] font-bold {{ $row['type'] === 'MATERIAL' ? 'bg-blue-50 text-blue-700 dark:bg-blue-950/40 dark:text-blue-300' : 'bg-amber-50 text-amber-700 dark:bg-amber-950/40 dark:text-amber-300' }}">{{ $row['type'] === 'MATERIAL' ? 'M' : 'J' }}</span>
                                    @unless ($row['exists'])<span class="ml-1 rounded bg-emerald-50 px-1.5 py-0.5 text-[10px] font-bold text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300">baru</span>@endunless
                                </td>
                                <td class="px-3 py-2 max-w-xs truncate">{{ $row['uraian'] }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ number_format($row['harga'], 0, ',', '.') }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ $row['vol'] === null ? '—' : rtrim(rtrim(number_format($row['vol'], 3, ',', '.'), '0'), ',') }}</td>
                                <td class="px-3 py-2 text-right tabular-nums font-bold">{{ number_format($row['total'], 0, ',', '.') }}</td>
                                <td class="px-3 py-2">
                                    @if ($row['status'] === 'ok')<span class="text-emerald-600 dark:text-emerald-400 font-bold">OK</span>
                                    @elseif ($row['status'] === 'skipped')<span class="text-ink-400">skip</span>
                                    @else<span class="text-brand-600 dark:text-brand-400 font-bold">{{ implode(', ', $row['messages']) }}</span>@endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-card>

        <div class="flex items-center gap-3">
            @if (empty($preview['errors']))
                <x-button>Konfirmasi &amp; Simpan LOP</x-button>
            @else
                <x-button type="button" class="opacity-50 pointer-events-none">Konfirmasi &amp; Simpan LOP</x-button>
            @endif
            <a href="{{ route('lop.import.form') }}" class="text-sm text-ink-600 dark:text-ink-300 hover:text-ink-900 dark:hover:text-ink-50">Kembali</a>
        </div>
    </form>
</div>
@endsection
