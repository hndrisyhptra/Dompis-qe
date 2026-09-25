@extends('layouts.app')

@section('title', 'Hasil Import BOQ')

@section('content')
@php
    $metadata = $batch->metadata ?? [];
    $variant = match($batch->status) { 'completed' => 'success', 'partial' => 'warning', 'failed' => 'danger', 'processing' => 'info', default => 'neutral' };
@endphp
<div class="mx-auto max-w-6xl">
    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <a href="{{ route('bulk-import.boq.index') }}" class="text-sm font-medium text-ink-600 hover:text-ink-900 dark:text-ink-300 dark:hover:text-white">&larr; Upload ulang</a>
            <h1 class="mt-2 break-words text-xl font-bold text-ink-900 [overflow-wrap:anywhere] dark:text-ink-50">Hasil Import BOQ — {{ $batch->original_name }}</h1>
            <p class="mt-1 text-sm text-ink-500 dark:text-ink-400">VOL kosong atau 0 tidak dipakai ({{ (int) data_get($metadata, 'skipped_count', 0) }} baris).</p>
        </div>
        <x-badge :variant="$variant">{{ str_replace('_', ' ', ucfirst($batch->status)) }}</x-badge>
    </div>

    <div class="mb-4">@include('imports._live-progress')</div>

    @if($batch->error_message)
        <div class="mb-4 rounded-lg border border-brand-200 bg-brand-50 px-4 py-3 dark:border-brand-800 dark:bg-brand-900/30">
            <p class="text-sm font-medium text-brand-700 dark:text-brand-300">Import gagal, tidak ada BOQ yang disimpan:</p>
            <p class="mt-1 text-sm text-brand-700 dark:text-brand-300">{{ $batch->error_message }}</p>
        </div>
    @endif

    @if(data_get($metadata, 'project_name'))
        <div class="mb-4 grid gap-3 rounded-xl border border-ink-100 bg-white p-4 dark:border-ink-800 dark:bg-ink-900 sm:grid-cols-[1fr_auto_auto] sm:items-center">
            <div class="min-w-0"><p class="text-xs text-ink-400">Project / Nama LOP</p><p class="mt-1 truncate font-bold text-ink-900 dark:text-white">{{ data_get($metadata, 'project_name') }}</p><p class="mt-1 text-xs text-ink-500">{{ data_get($metadata, 'lop_incident', 'LOP belum berhasil dicocokkan') }}</p></div>
            <div class="rounded-lg bg-ink-50 px-4 py-2.5 dark:bg-ink-800"><p class="text-xs text-ink-400">Paket Excel</p><p class="mt-0.5 text-sm font-bold text-ink-800 dark:text-white">{{ data_get($metadata, 'package_detected', '—') }}</p></div>
            <div class="rounded-lg bg-ink-50 px-4 py-2.5 dark:bg-ink-800"><p class="text-xs text-ink-400">Paket Master</p><p class="mt-0.5 text-sm font-bold text-ink-800 dark:text-white">{{ data_get($metadata, 'package_code', '—') }}</p></div>
        </div>
    @endif

    <div class="mb-6 grid grid-cols-2 gap-3 sm:grid-cols-4">
        <div class="rounded-xl border border-ink-100 bg-white p-4 dark:border-ink-800 dark:bg-ink-900">
            <p class="text-xs text-ink-400">Material</p>
            <p class="text-lg font-extrabold text-ink-900 dark:text-white">{{ (int) data_get($metadata, 'material_count', 0) }} item</p>
            <p class="text-sm font-bold text-ink-700 dark:text-ink-200">Rp {{ number_format((float) data_get($metadata, 'material_total', 0), 0, ',', '.') }}</p>
        </div>
        <div class="rounded-xl border border-ink-100 bg-white p-4 dark:border-ink-800 dark:bg-ink-900">
            <p class="text-xs text-ink-400">Jasa</p>
            <p class="text-lg font-extrabold text-ink-900 dark:text-white">{{ (int) data_get($metadata, 'service_count', 0) }} item</p>
            <p class="text-sm font-bold text-ink-700 dark:text-ink-200">Rp {{ number_format((float) data_get($metadata, 'service_total', 0), 0, ',', '.') }}</p>
        </div>
        <div class="col-span-2 rounded-xl border border-brand-200 bg-brand-50/50 p-4 dark:border-brand-800 dark:bg-brand-950/20">
            <p class="text-xs text-ink-400">Grand Total (Material + Jasa)</p>
            <p class="text-lg font-extrabold text-brand-700 dark:text-brand-300">Rp {{ number_format((float) data_get($metadata, 'grand_total', 0), 0, ',', '.') }}</p>
            <p class="mt-1 text-xs text-ink-500 dark:text-ink-400">{{ (int) data_get($metadata, 'used_count', $batch->success_rows) }} designator dipakai</p>
        </div>
    </div>

    <div class="rounded-2xl border border-ink-100 bg-white p-5 shadow-sm dark:border-ink-800 dark:bg-ink-900">
        <h2 class="text-sm font-bold text-ink-900 dark:text-white">Baris Designator</h2>
        <p class="mb-4 mt-1 text-xs text-ink-400">Hanya designator dengan VOL terisi yang ditampilkan. Kuning = designator baru yang dibuat otomatis dari file.</p>
        <div class="overflow-x-auto rounded-xl border border-ink-100 dark:border-ink-800">
            <table class="w-full min-w-[820px] text-left text-xs">
                <thead class="bg-ink-50 dark:bg-ink-800">
                    <tr><th class="px-3 py-2.5 font-bold text-ink-600 dark:text-ink-300">Baris</th><th class="px-3 py-2.5 font-bold text-ink-600 dark:text-ink-300">Designator</th><th class="px-3 py-2.5 font-bold text-ink-600 dark:text-ink-300">Uraian</th><th class="px-3 py-2.5 text-right font-bold text-ink-600 dark:text-ink-300">Harga</th><th class="px-3 py-2.5 text-right font-bold text-ink-600 dark:text-ink-300">VOL</th><th class="px-3 py-2.5 text-right font-bold text-ink-600 dark:text-ink-300">Total</th><th class="px-3 py-2.5 font-bold text-ink-600 dark:text-ink-300">Status</th></tr>
                </thead>
                <tbody class="divide-y divide-ink-100 dark:divide-ink-800">
                    @forelse($rows as $row)
                        @php($item = $row->payload ?? [])
                        <tr class="{{ $row->status === 'failed' ? 'bg-brand-50/50 dark:bg-brand-950/20' : '' }}">
                            <td class="px-3 py-2 font-mono">{{ $row->row_number }}</td>
                            <td class="px-3 py-2 font-mono font-bold">
                                {{ $row->reference ?: '—' }}
                                @if(data_get($item, 'type'))<span class="ml-1 rounded px-1.5 py-0.5 text-[10px] font-bold {{ data_get($item, 'type') === 'MATERIAL' ? 'bg-blue-50 text-blue-700 dark:bg-blue-950/40 dark:text-blue-300' : 'bg-amber-50 text-amber-700 dark:bg-amber-950/40 dark:text-amber-300' }}">{{ data_get($item, 'type') === 'MATERIAL' ? 'M' : 'J' }}</span>@endif
                                @if(data_get($item, 'exists') === false)<span class="ml-1 rounded bg-amber-50 px-1.5 py-0.5 text-[10px] font-bold text-amber-700 dark:bg-amber-950/40 dark:text-amber-300">baru</span>@endif
                            </td>
                            <td class="max-w-xs truncate px-3 py-2" title="{{ data_get($item, 'item_name') }}">{{ data_get($item, 'item_name', '—') }}</td>
                            <td class="px-3 py-2 text-right tabular-nums">{{ number_format((float) data_get($item, 'unit_price', 0), 0, ',', '.') }}</td>
                            <td class="px-3 py-2 text-right tabular-nums">{{ data_get($item, 'qty') === null ? '—' : rtrim(rtrim(number_format((float) data_get($item, 'qty'), 3, ',', '.'), '0'), ',') }}</td>
                            <td class="px-3 py-2 text-right font-bold tabular-nums">{{ number_format((float) data_get($item, 'total_price', 0), 0, ',', '.') }}</td>
                            <td class="px-3 py-2">
                                @if($row->status === 'success')<span class="font-bold text-emerald-600 dark:text-emerald-400">OK</span>
                                @else<span class="font-bold text-brand-600 dark:text-brand-400" title="{{ $row->message }}">gagal</span>@endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-4 py-12 text-center text-sm text-ink-400">Detail baris belum tersedia.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $rows->links() }}</div>
    </div>
</div>
@endsection
