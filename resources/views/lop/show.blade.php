@extends('layouts.app')

@section('title', $lop->kode_lop)

@section('content')
<div class="max-w-3xl mx-auto space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
            <a href="{{ route('lop.index') }}" class="text-sm font-medium text-ink-600 dark:text-ink-300 hover:text-ink-900 dark:hover:text-ink-50">
                &larr; Kembali
            </a>
            <h1 class="text-xl font-bold text-ink-900 dark:text-ink-50 mt-2">{{ $lop->kode_lop }} — {{ $lop->nama_lop }}</h1>
            <p class="text-sm text-ink-500 dark:text-ink-400 mt-1">
                <x-badge variant="neutral">{{ $lop->wbs_type->label() }}</x-badge>
                <x-badge :variant="$lop->status_lop->badgeVariant()">{{ $lop->status_lop->label() }}</x-badge>
                Teknisi aktif: {{ $lop->activeAssignment?->technician?->name ?? '—' }}
            </p>
        </div>

        @can('update', $lop)
            <a href="{{ route('lop.edit', $lop) }}">
                <x-button type="button" class="!w-auto px-4">Edit</x-button>
            </a>
        @endcan
    </div>

    @can('assign', $lop)
        <x-card>
            <h2 class="text-sm font-semibold text-ink-900 dark:text-ink-50 mb-1">Assign Teknisi</h2>
            <p class="text-sm text-ink-500 dark:text-ink-400 mb-4">Pilih teknisi aktif untuk ditugaskan ke LOP ini.</p>

            <button type="button"
                    onclick="document.getElementById('assign-modal').showModal()"
                    class="rounded-lg border border-ink-100 dark:border-ink-700 bg-white dark:bg-ink-800 px-4 py-2.5 text-sm font-medium text-ink-700 dark:text-ink-200 hover:bg-ink-50 dark:hover:bg-ink-700 transition">
                Pilih Teknisi&hellip;
            </button>

            <x-modal id="assign-modal" title="Pilih Teknisi">
                <form method="POST" action="{{ route('lop.assign', $lop) }}">
                    @csrf
                    <input type="hidden" name="technician_id" value="">

                    <input
                        type="search"
                        placeholder="Cari nama atau NIK..."
                        oninput="filterTeknisi(this.value)"
                        class="w-full rounded-lg border border-ink-100 dark:border-ink-700 bg-white dark:bg-ink-800 px-3.5 py-2.5 text-sm text-ink-900 dark:text-ink-50 placeholder:text-ink-400 dark:placeholder:text-ink-500 shadow-sm focus:outline-none focus:ring-2 focus:ring-brand-500/30 focus:border-brand-500 transition mb-3"
                    >

                    <ul class="max-h-64 overflow-y-auto divide-y divide-ink-100 dark:divide-ink-700 border border-ink-100 dark:border-ink-700 rounded-lg" id="teknisi-list">
                        @forelse ($technicians as $technician)
                            <li class="teknisi-row" data-search="{{ strtolower($technician->name.' '.$technician->nik.' '.$technician->username) }}">
                                <button type="submit"
                                        onclick="this.form.technician_id.value='{{ $technician->id_user }}'"
                                        class="w-full text-left px-3.5 py-2.5 text-sm hover:bg-ink-50 dark:hover:bg-ink-800 transition">
                                    <span class="block text-ink-900 dark:text-ink-50 font-medium">{{ $technician->name }}</span>
                                    <span class="block text-ink-500 dark:text-ink-400 text-xs mt-0.5">{{ $technician->username }}@if($technician->nik) · {{ $technician->nik }}@endif</span>
                                </button>
                            </li>
                        @empty
                            <li class="px-3.5 py-6 text-center text-sm text-ink-400 dark:text-ink-500">Belum ada teknisi aktif.</li>
                        @endforelse
                    </ul>

                    <p id="teknisi-empty" class="hidden px-3.5 py-4 text-center text-sm text-ink-400 dark:text-ink-500">
                        Tidak ada teknisi yang cocok.
                    </p>
                </form>
            </x-modal>
        </x-card>
    @endcan

    @can('transitionStatus', $lop)
        <x-card>
            <h2 class="text-sm font-semibold text-ink-900 dark:text-ink-50 mb-4">Ubah Status</h2>

            <form method="POST" action="{{ route('lop.transition', $lop) }}" class="space-y-4">
                @csrf

                <x-select name="status" label="Status Baru" placeholder="Pilih status">
                    @foreach (\App\Enums\LopStatus::cases() as $status)
                        @if ($lop->status_lop->canTransitionTo($status))
                            <option value="{{ $status->value }}">{{ $status->label() }}</option>
                        @endif
                    @endforeach
                </x-select>

                <x-input name="note" label="Catatan (opsional)" />

                <x-button>Ubah Status</x-button>
            </form>
        </x-card>
    @endcan

    <x-card>
        <h2 class="text-sm font-semibold text-ink-900 dark:text-ink-50 mb-4">Riwayat</h2>

        @forelse ($lop->histories as $history)
            <div class="flex items-start gap-3 py-3 border-b border-ink-100 dark:border-ink-700 last:border-0">
                <div class="w-2 h-2 rounded-full bg-brand-500 mt-1.5 shrink-0"></div>
                <div>
                    <p class="text-sm text-ink-900 dark:text-ink-50">
                        @php
                            $before = \App\Enums\LopStatus::tryFrom($history->status_before ?? '')?->label() ?? $history->status_before;
                            $after = \App\Enums\LopStatus::tryFrom($history->status_after)?->label() ?? $history->status_after;
                        @endphp
                        {{ $before ?? 'Dibuat' }} @if($history->status_before) &rarr; {{ $after }} @else ({{ $after }}) @endif
                        <span class="text-ink-400 dark:text-ink-500">— oleh {{ $history->user?->name ?? 'Sistem' }}</span>
                    </p>
                    @if ($history->note)
                        <p class="text-sm text-ink-600 dark:text-ink-300 mt-0.5">{{ $history->note }}</p>
                    @endif
                    <p class="text-xs text-ink-400 dark:text-ink-500 mt-0.5">{{ $history->created_at->format('d M Y H:i') }}</p>
                </div>
            </div>
        @empty
            <p class="text-sm text-ink-400 dark:text-ink-500">Belum ada riwayat.</p>
        @endforelse
    </x-card>
</div>

<script>
    function filterTeknisi(query) {
        query = query.toLowerCase();
        var rows = document.querySelectorAll('#teknisi-list .teknisi-row');
        var visibleCount = 0;

        rows.forEach(function (row) {
            var match = row.dataset.search.includes(query);
            row.style.display = match ? '' : 'none';
            if (match) visibleCount++;
        });

        document.getElementById('teknisi-empty').classList.toggle('hidden', visibleCount > 0 || rows.length === 0);
    }
</script>
@endsection
