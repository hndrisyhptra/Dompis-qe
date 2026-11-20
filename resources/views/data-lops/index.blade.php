@extends('layouts.app')

@section('title', 'Data LOP')

@section('content')
<style>
    .data-lop-table { border-collapse: separate; border-spacing: 0; }
    .data-lop-table th:not(:last-child),
    .data-lop-table td:not(:last-child) { border-right: 1px solid rgb(226 232 240); }
    .dark .data-lop-table th:not(:last-child),
    .dark .data-lop-table td:not(:last-child) { border-right-color: rgb(55 65 81); }
</style>
<div class="mx-auto max-w-7xl space-y-5">
    @if ($errors->any())
        <div class="rounded-2xl border border-red-200 bg-red-50 p-4 text-sm font-semibold text-red-700 dark:border-red-900 dark:bg-red-950/30 dark:text-red-300">{{ $errors->first() }}</div>
    @endif
    <div class="flex flex-col gap-4 border-b border-ink-200 pb-5 dark:border-ink-800 sm:flex-row sm:items-end sm:justify-between">
        <div><p class="text-xs font-bold uppercase tracking-wider text-red-700 dark:text-red-300">Master Data <span class="px-1 text-red-300 dark:text-red-700">/</span> LOP</p><h1 class="mt-1 text-2xl font-bold tracking-tight text-ink-900 dark:text-white">Data LOP</h1><p class="mt-1.5 max-w-2xl text-sm text-ink-500 dark:text-ink-400">Seluruh LOP hasil input manual dan Bulk Import dalam lingkup akses Anda.</p></div>
        <div class="flex w-full flex-wrap items-center gap-2 sm:w-auto sm:justify-end">
            <button type="submit" form="data-lop-filters" formaction="{{ route('data-lops.export', [], false) }}" formmethod="GET" title="Download seluruh Data LOP sesuai filter dan scope akses" class="inline-flex min-h-10 items-center justify-center gap-2 rounded-md border border-ink-200 bg-white px-3 text-sm font-medium text-ink-700 transition hover:bg-ink-50 focus-visible:outline-2 focus-visible:outline-brand-500 dark:border-ink-700 dark:bg-ink-900 dark:text-ink-200 dark:hover:bg-ink-800">
                <svg aria-hidden="true" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v12m-4-4 4 4 4-4M4 16v3a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-3"/></svg>
                Download Excel
            </button>
            <a href="{{ route('lop.create') }}" class="relative z-10 inline-flex min-h-11 shrink-0 items-center justify-center gap-2 whitespace-nowrap rounded-md bg-brand-600 px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-500">
                <svg aria-hidden="true" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M12 5v14M5 12h14"/></svg>
                Input LOP Baru
            </a>
        </div>
    </div>

    <form id="data-lop-filters" action="{{ route('data-lops.index', [], false) }}" method="GET" class="rounded-md border border-ink-200 bg-white p-4 dark:border-ink-700 dark:bg-ink-900">
        <div class="mb-4 flex items-center justify-between gap-3 border-b border-ink-100 pb-3 dark:border-ink-800">
            <div><p class="text-sm font-semibold text-ink-900 dark:text-white">Pencarian & Filter</p><p class="mt-0.5 text-xs text-ink-500 dark:text-ink-400">Persempit daftar berdasarkan data operasional LOP.</p></div>
            @if (collect($filters)->filter()->isNotEmpty())
                <a href="{{ route('data-lops.index') }}" class="shrink-0 text-xs font-bold text-brand-600 hover:text-brand-700">Reset filter</a>
            @endif
        </div>
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-7">
            <label class="lg:col-span-2"><span class="mb-1.5 block text-xs font-medium text-ink-600 dark:text-ink-300">Cari LOP</span><input name="q" value="{{ $filters['search'] }}" placeholder="Incident, nama LOP, atau STO" class="min-h-10 w-full rounded-md border border-ink-300 bg-white px-3 text-sm outline-none transition focus:border-brand-500 focus:ring-2 focus:ring-brand-500/15 dark:border-ink-700 dark:bg-ink-950"></label>
            <label><span class="mb-1.5 block text-xs font-medium text-ink-600 dark:text-ink-300">Program</span><select name="program" class="min-h-10 w-full rounded-md border border-ink-300 bg-white px-3 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/15 dark:border-ink-700 dark:bg-ink-950"><option value="">Semua program</option>@foreach($programs as $item)<option value="{{ $item->value }}" @selected($filters['program'] === $item->value)>{{ $item->label() }}</option>@endforeach</select></label>
            <label><span class="mb-1.5 block text-xs font-medium text-ink-600 dark:text-ink-300">Status</span><select name="status" class="min-h-10 w-full rounded-md border border-ink-300 bg-white px-3 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/15 dark:border-ink-700 dark:bg-ink-950"><option value="">Semua status</option>@foreach($statuses as $item)<option value="{{ $item->value }}" @selected($filters['status'] === $item->value)>{{ $item->label() }}</option>@endforeach</select></label>
            <label class="relative block" x-data="{ open: false, query: '', selected: @js($filters['service_area_name']) }" @click.outside="open = false" @keydown.escape.stop="open = false">
                <span class="mb-1.5 block text-xs font-medium text-ink-600 dark:text-ink-300">Service Area</span>
                <input type="hidden" name="service_area_name" :value="selected">
                <button type="button" @click="open = !open; if (open) $nextTick(() => $refs.serviceAreaSearch.focus())" :aria-expanded="open" aria-haspopup="listbox" aria-controls="service-area-name-options" class="flex min-h-10 w-full items-center justify-between gap-2 rounded-md border border-ink-300 bg-white px-3 text-left text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/15 dark:border-ink-700 dark:bg-ink-950">
                    <span class="truncate" :class="selected ? 'text-ink-900 dark:text-white' : 'text-ink-500 dark:text-ink-400'" x-text="selected || 'Semua Service Area'"></span>
                    <svg aria-hidden="true" class="h-4 w-4 shrink-0 text-ink-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6"/></svg>
                </button>
                <div x-show="open" x-cloak class="absolute right-0 z-50 mt-1 w-64 max-w-[calc(100vw-2rem)] rounded-md border border-ink-200 bg-white p-2 shadow-lg dark:border-ink-700 dark:bg-ink-900">
                    <input x-ref="serviceAreaSearch" type="search" x-model="query" placeholder="Cari nama Service Area..." aria-label="Cari nama Service Area" class="min-h-9 w-full rounded-md border border-ink-300 bg-white px-2.5 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/15 dark:border-ink-700 dark:bg-ink-950">
                    <div id="service-area-name-options" role="listbox" aria-label="Pilihan nama Service Area" class="mt-1 max-h-52 overflow-y-auto">
                        <button type="button" role="option" :aria-selected="selected === ''" @click="selected = ''; open = false; query = ''" class="block min-h-9 w-full rounded px-2.5 text-left text-sm text-ink-700 hover:bg-ink-50 focus:bg-ink-50 focus:outline-none dark:text-ink-200 dark:hover:bg-ink-800 dark:focus:bg-ink-800">Semua Service Area</button>
                        @foreach($serviceAreaNames as $name)
                            <button type="button" role="option" :aria-selected="selected === @js($name)" x-show="$el.textContent.toLocaleLowerCase().includes(query.trim().toLocaleLowerCase())" @click="selected = @js($name); open = false; query = ''" class="block min-h-9 w-full rounded px-2.5 text-left text-sm text-ink-700 hover:bg-ink-50 focus:bg-ink-50 focus:outline-none dark:text-ink-200 dark:hover:bg-ink-800 dark:focus:bg-ink-800">{{ $name }}</button>
                        @endforeach
                        <p x-show="query && !Array.from($el.parentElement.querySelectorAll('[role=option]')).slice(1).some(option => option.textContent.toLocaleLowerCase().includes(query.trim().toLocaleLowerCase()))" class="px-2.5 py-3 text-xs text-ink-500 dark:text-ink-400">Tidak ada Service Area yang cocok.</p>
                    </div>
                </div>
            </label>
            <label>
                <span class="mb-1.5 block text-xs font-medium text-ink-600 dark:text-ink-300">STO</span>
                <x-sto-filter :branches="$branches" :selected="request('sto_filter', [])" />
            </label>
            <div class="flex items-end"><button class="min-h-10 w-full rounded-md bg-ink-900 px-4 text-sm font-semibold text-white transition hover:bg-ink-800 dark:bg-white dark:text-ink-900 dark:hover:bg-ink-100">Terapkan Filter</button></div>
        </div>
        <p class="mt-3 text-xs text-ink-500">Download Excel mengikuti isian filter terkini dan mencakup seluruh hasil dalam scope akses Anda, bukan hanya halaman ini.</p>
    </form>

    <div class="overflow-hidden rounded-md border border-ink-200 bg-white dark:border-ink-700 dark:bg-ink-900">
        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-ink-100 px-4 py-3 text-xs dark:border-ink-800">
            <p class="font-medium text-ink-700 dark:text-ink-200"><span class="tabular-nums">{{ number_format($lops->total(), 0, ',', '.') }}</span> LOP ditemukan</p>
            <p class="text-ink-500 dark:text-ink-400">Menampilkan {{ $lops->firstItem() ?? 0 }}–{{ $lops->lastItem() ?? 0 }} dari {{ number_format($lops->total(), 0, ',', '.') }}</p>
        </div>
        <div class="overflow-x-auto">
            <table class="data-lop-table min-w-290 w-full table-fixed divide-y divide-ink-100 text-sm dark:divide-ink-800">
                <colgroup><col style="width: 17rem"><col style="width: 10rem"><col style="width: 11rem"><col style="width: 10rem"><col style="width: 9rem"><col style="width: 8rem"><col style="width: 10rem"></colgroup>
                <thead class="bg-ink-50 text-center text-xs font-semibold text-ink-600 dark:bg-ink-800 dark:text-ink-300"><tr><th scope="col" class="px-4 py-3 text-center">LOP</th><th scope="col" class="px-4 py-3 text-center">Lokasi</th><th scope="col" class="px-4 py-3 text-center">Program</th><th scope="col" class="px-4 py-3 text-center">Status Dompis</th><th scope="col" class="px-4 py-3 text-center">Status AISS</th><th scope="col" class="px-4 py-3 text-center">BOQ</th><th scope="col" class="px-4 py-3 text-center">Aksi</th></tr></thead>
                <tbody class="divide-y divide-ink-100 dark:divide-ink-800">
                    @forelse($lops as $lop)
                        @php
                            $baDocument = \App\Support\BaBongkarRules::document($lop);
                            $inseraCount = $lop->evidences->filter(fn ($evidence) => $evidence->category === \App\Enums\EvidenceCategory::INSERA)->count();
                        @endphp
                        <tr x-data="{ detail: false, remove: false, actionsOpen: false, actionsPosition: { top: 8, left: 8 }, toggleActions(button) { if (this.actionsOpen) { this.actionsOpen = false; return; } this.actionsOpen = true; this.$nextTick(() => { const anchor = button.getBoundingClientRect(); const menu = document.getElementById('lop-actions-{{ $lop->id_qe_lops }}'); if (!menu) return; const size = menu.getBoundingClientRect(); const gap = 6; const below = anchor.bottom + size.height + gap <= window.innerHeight - 8; this.actionsPosition = { top: below ? anchor.bottom + gap : Math.max(8, anchor.top - size.height - gap), left: Math.max(8, Math.min(anchor.right - size.width, window.innerWidth - size.width - 8)) }; }); } }" @click.window="if (actionsOpen && !$event.target.closest('#lop-action-toggle-{{ $lop->id_qe_lops }}, #lop-actions-{{ $lop->id_qe_lops }}')) actionsOpen = false" @keydown.escape.window="actionsOpen = false" @scroll.window="actionsOpen = false" @resize.window="actionsOpen = false" class="align-middle transition hover:bg-brand-50/40 dark:hover:bg-ink-800/40">
                            <td class="px-4 py-3.5"><p class="font-semibold text-red-800 dark:text-red-300">{{ $lop->incident }}</p><p class="mt-1 truncate text-xs font-medium text-ink-700 dark:text-ink-200" title="{{ $lop->nama_lop }}">{{ $lop->nama_lop }}</p><p class="mt-1 text-[11px] tabular-nums text-ink-400">Reported Date {{ $lop->reported_date ?: 'kosong' }}</p></td>
                            <td class="px-4 py-3.5"><p class="font-medium text-ink-800 dark:text-ink-100">{{ $lop->branch ?: 'Belum terpetakan' }}</p><p class="mt-1 text-xs text-ink-500 dark:text-ink-400">STO {{ $lop->sto ?: '—' }}</p></td>
                            <td class="px-4 py-3.5 text-sm text-ink-700 dark:text-ink-200">{{ $lop->program_type->label() }}</td>
                            <td class="px-4 py-3.5 text-center"><x-lop-status-badge :status="$lop->status_lop" /></td>
                            <td class="px-4 py-3.5 text-center"><x-aiss-status :status="$lop->status_aiss" /></td>
                            <td class="px-4 py-3.5">
                                @if($lop->boq)
                                    <span class="text-sm font-semibold text-ink-800 dark:text-ink-100">{{ $lop->boq->item_count }} item</span>
                                    <span class="mt-0.5 block text-xs text-ink-500 dark:text-ink-400">BOQ Plan</span>
                                @elseif($lop->materialReservation?->items->isNotEmpty())
                                    <span class="text-sm font-semibold text-ink-800 dark:text-ink-100">{{ $lop->materialReservation->items->count() }} item</span>
                                    <span class="mt-0.5 block text-xs text-ink-500 dark:text-ink-400">Reservasi Teknisi</span>
                                @else
                                    <span class="text-xs text-ink-400">Belum ada</span>
                                @endif
                            </td>
                            <td class="px-4 py-3.5 text-center">
                                <div class="flex flex-col items-center gap-2">
                                    <div class="flex items-center gap-2">
                                        <x-table-action label="Lihat detail LOP {{ $lop->incident }}" @click="detail = true"><svg aria-hidden="true" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12s3.75-6.75 9.75-6.75S21.75 12 21.75 12 18.75 18 12 18.75 2.25 12 2.25 12Z"/><circle cx="12" cy="12" r="2.25"/></svg></x-table-action>
                                        @can('update', $lop)<x-table-action label="Edit data LOP {{ $lop->incident }}" tone="primary" :href="route('lop.edit', [$lop, 'return_url' => request()->fullUrl()])"><svg aria-hidden="true" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 7.125V18A2.25 2.25 0 0 1 17.25 20.25H6.75A2.25 2.25 0 0 1 4.5 18V7.5A2.25 2.25 0 0 1 6.75 5.25H12"/></svg></x-table-action>@endcan
                                        <x-table-action label="Aksi lainnya untuk LOP {{ $lop->incident }}" id="lop-action-toggle-{{ $lop->id_qe_lops }}" data-lop-action-toggle aria-controls="lop-actions-{{ $lop->id_qe_lops }}" @click="toggleActions($el, 'lop-actions-{{ $lop->id_qe_lops }}')" ::aria-expanded="actionsOpen"><svg aria-hidden="true" class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor"><circle cx="5" cy="12" r="1.6"/><circle cx="12" cy="12" r="1.6"/><circle cx="19" cy="12" r="1.6"/></svg></x-table-action>
                                    </div>
                                    <template x-teleport="body">
                                    <div id="lop-actions-{{ $lop->id_qe_lops }}" x-show="actionsOpen" x-cloak data-lop-action-menu @click="if ($event.target.closest('button')) actionsOpen = false" :style="`top:${actionsPosition.top}px;left:${actionsPosition.left}px`" x-transition:enter="transition ease-out duration-150" x-transition:enter-start="translate-y-1 scale-95 opacity-0" x-transition:enter-end="translate-y-0 scale-100 opacity-100" x-transition:leave="transition ease-in duration-100" x-transition:leave-start="translate-y-0 scale-100 opacity-100" x-transition:leave-end="translate-y-1 scale-95 opacity-0" class="fixed z-120 max-h-[calc(100vh-1rem)] w-60 origin-top-right overflow-y-auto rounded-md border border-ink-200 bg-white p-1 shadow-xl dark:border-ink-700 dark:bg-ink-900">
                                @if($baDocument)
                                    <x-table-action label="Download BA Bongkar" tone="info" :href="route('lop.ba-bongkar.download', $lop)"><svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5A3.375 3.375 0 0 0 10.125 2.25h-4.5A1.125 1.125 0 0 0 4.5 3.375v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125v-6.375Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M12 11.25v6m-3-3 3 3 3-3"/></svg></x-table-action>
                                @endif
                                @if ($lop->status_lop === \App\Enums\LopStatus::COMPLETED)
                                    <x-table-action label="Download BOQ AISS (.xlsx)" tone="primary" :href="route('data-lops.aiss-boq', $lop)">
                                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M14.25 2.25H6.75A2.25 2.25 0 0 0 4.5 4.5v15a2.25 2.25 0 0 0 2.25 2.25h10.5a2.25 2.25 0 0 0 2.25-2.25V7.5m-5.25-5.25V7.5h5.25m-5.25-5.25 5.25 5.25M12 11.25v6m-3-3 3 3 3-3" /></svg>
                                    </x-table-action>
                                    <x-table-action label="Download evidence (.zip)" tone="success" :href="route('lop.evidence-archive', $lop)">
                                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" /></svg>
                                    </x-table-action>
                                @endif
                                @if ($lop->status_lop !== \App\Enums\LopStatus::COMPLETED) @can('uploadEvidence', $lop)<x-table-action :label="$inseraCount > 0 ? 'Kelola capture Insera ('.$inseraCount.')' : 'Upload capture Insera'" tone="info" onclick="document.getElementById('insera-{{ $lop->id_qe_lops }}').showModal()"><svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 0 1 5.186 7.23c-.38.054-.757.112-1.134.175C2.88 7.6 2 8.61 2 9.8V18a2.25 2.25 0 0 0 2.25 2.25h15.5A2.25 2.25 0 0 0 22 18V9.8c0-1.19-.88-2.2-2.052-2.395a48.776 48.776 0 0 0-1.134-.175 2.31 2.31 0 0 1-1.64-1.055l-.822-1.316a2.192 2.192 0 0 0-1.736-1.039 48.774 48.774 0 0 0-5.232 0 2.192 2.192 0 0 0-1.736 1.039l-.821 1.316Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 13.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/></svg></x-table-action>@endcan @endif
                                @if($lop->program_type->usesProjectStatus()) @can('uploadEvidence', $lop)<x-table-action label="Kelola Surat Permintaan" tone="info" onclick="document.getElementById('request-letter-{{ $lop->id_qe_lops }}').showModal()"><svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5A3.375 3.375 0 0 0 10.125 2.25h-4.5A1.125 1.125 0 0 0 4.5 3.375v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125v-6.375Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M12 18.75h6.75m-3.375-3.375v6.75"/></svg></x-table-action>@endcan @endif
                                @can('delete', $lop)<x-table-action label="Hapus data LOP" tone="danger" @click="remove = true"><svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166M19.228 5.79 18.16 19.673A2.25 2.25 0 0115.916 21H8.084a2.25 2.25 0 01-2.244-2.327L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916"/></svg></x-table-action>@endcan
                                    </div>
                                    </template>
                                </div>

                            <div x-show="detail" x-cloak @keydown.escape.window="detail=false" class="fixed inset-0 z-90 flex items-end justify-center bg-ink-950/60 p-2 sm:items-center sm:p-4" @click.self="detail=false">
                                @php
                                    $detailBoqCount = $lop->boq?->item_count ?? $lop->materialReservation?->items->count() ?? 0;
                                    $detailBoqTotal = $lop->boq?->grand_total;
                                @endphp
                                <section role="dialog" aria-modal="true" aria-labelledby="lop-detail-title-{{ $lop->id_qe_lops }}" class="flex max-h-[94vh] w-full max-w-4xl flex-col overflow-hidden rounded-xl border border-ink-200 bg-white shadow-2xl dark:border-ink-700 dark:bg-ink-900">
                                    <header class="relative overflow-hidden border-b border-ink-100 bg-ink-50 px-12 py-5 text-center dark:border-ink-800 dark:bg-ink-950/50 sm:px-16 sm:py-6">
                                        <button type="button" aria-label="Tutup detail LOP" title="Tutup detail LOP" @click="detail=false" class="absolute right-4 top-4 grid h-10 w-10 place-items-center rounded-lg border border-ink-200 bg-white text-ink-600 transition hover:bg-ink-100 focus-visible:outline-2 focus-visible:outline-brand-500 dark:border-ink-700 dark:bg-ink-900 dark:text-ink-300 dark:hover:bg-ink-800">×</button>
                                        <p class="text-[11px] font-bold uppercase tracking-[.16em] text-brand-600 dark:text-brand-400">Detail pekerjaan</p>
                                        <h3 id="lop-detail-title-{{ $lop->id_qe_lops }}" class="mt-2 wrap-break-word text-lg font-extrabold leading-snug text-ink-900 dark:text-white sm:text-xl">{{ $lop->nama_lop }}</h3>
                                        <p class="mt-1.5 font-mono text-sm font-semibold text-ink-600 dark:text-ink-300">{{ $lop->incident }}</p>
                                        <div class="mt-4 flex flex-wrap items-center justify-center gap-2">
                                            <span class="rounded-full bg-white px-3 py-1 text-xs font-semibold text-ink-700 shadow-sm dark:bg-ink-800 dark:text-ink-200">{{ $lop->program_type->label() }}</span>
                                            <span class="rounded-full bg-white px-3 py-1 text-xs font-semibold text-ink-700 shadow-sm dark:bg-ink-800 dark:text-ink-200">Anggaran {{ $lop->budget_type?->label() ?? 'Belum ditentukan' }}</span>
                                            <x-lop-status-badge :status="$lop->status_lop" />
                                        </div>
                                    </header>

                                    <div class="overflow-y-auto px-5 py-5 sm:px-7 sm:py-6">
                                        <div class="grid gap-6 lg:grid-cols-[1.15fr_.85fr]">
                                            <div class="space-y-6">
                                                <section>
                                                    <div class="mb-3 flex items-center justify-between gap-3"><h4 class="text-sm font-bold text-ink-900 dark:text-white">Lokasi pekerjaan</h4><span class="text-xs text-ink-500 dark:text-ink-400">{{ $lop->segmentLabel() }}</span></div>
                                                    <dl class="divide-y divide-ink-100 rounded-lg border border-ink-100 dark:divide-ink-800 dark:border-ink-800">
                                                        @foreach(['Branch'=>$lop->branch ?: 'Belum diisi','Area'=>$lop->area ?: 'Belum diisi','STO'=>$lop->sto ?: 'Belum diisi','ID IHLD'=>$lop->ihld_id ?: 'Belum tersedia','Reported Date'=>$lop->reported_date ? \Illuminate\Support\Carbon::parse($lop->reported_date)->format('d M Y') : 'Belum tersedia'] as $label=>$value)
                                                            <div class="flex items-center justify-between gap-4 px-3.5 py-2.5"><dt class="text-xs text-ink-500 dark:text-ink-400">{{ $label }}</dt><dd class="wrap-break-word text-right text-sm font-semibold text-ink-800 dark:text-ink-100">{{ $value }}</dd></div>
                                                        @endforeach
                                                    </dl>
                                                </section>

                                                <section>
                                                    <h4 class="mb-3 text-sm font-bold text-ink-900 dark:text-white">Penugasan</h4>
                                                    <div class="flex items-center gap-3 rounded-lg border border-ink-100 p-3.5 text-left dark:border-ink-800">
                                                        <span class="grid h-11 w-11 shrink-0 place-items-center rounded-full bg-brand-100 text-sm font-bold text-brand-700 dark:bg-brand-950/50 dark:text-brand-300">{{ $lop->activeAssignment?->technician?->name ? mb_strtoupper(mb_substr($lop->activeAssignment->technician->name, 0, 1)) : '?' }}</span>
                                                        <div class="min-w-0 flex-1 text-left"><p class="text-[11px] text-ink-500 dark:text-ink-400">Teknisi aktif</p><p class="mt-0.5 truncate text-sm font-bold text-ink-900 dark:text-white">{{ $lop->activeAssignment?->technician?->name ?? 'Belum ditugaskan' }}</p>@if($lop->activeAssignment?->assigned_at)<p class="mt-0.5 text-xs text-ink-500 dark:text-ink-400">Ditugaskan {{ $lop->activeAssignment->assigned_at->format('d M Y, H:i') }}</p>@endif</div>
                                                    </div>
                                                </section>

                                                <section>
                                                    <h4 class="mb-3 text-sm font-bold text-ink-900 dark:text-white">Deskripsi pekerjaan</h4>
                                                    <p class="whitespace-pre-line rounded-lg bg-ink-50 px-4 py-3.5 text-sm leading-6 text-ink-700 dark:bg-ink-950/50 dark:text-ink-200">{{ $lop->job_description ?: 'Belum ada deskripsi pekerjaan.' }}</p>
                                                </section>
                                            </div>

                                            <aside class="space-y-5">
                                                <section class="rounded-lg border border-ink-100 p-4 dark:border-ink-800">
                                                    <h4 class="text-sm font-bold text-ink-900 dark:text-white">Status</h4>
                                                    <dl class="mt-3 space-y-3">
                                                        <div class="flex items-center justify-between gap-3"><dt class="text-xs text-ink-500 dark:text-ink-400">AISS</dt><dd><x-aiss-status :status="$lop->status_aiss" /></dd></div>
                                                        @if($lop->status_project)<div class="flex items-center justify-between gap-3"><dt class="text-xs text-ink-500 dark:text-ink-400">Proyek</dt><dd class="text-sm font-semibold text-ink-800 dark:text-ink-100">{{ $lop->status_project->label() }}</dd></div>@endif
                                                    </dl>
                                                </section>

                                                <section class="rounded-lg border border-ink-100 bg-ink-50/70 p-4 dark:border-ink-800 dark:bg-ink-950/30">
                                                    <h4 class="text-sm font-bold text-ink-900 dark:text-white">BOQ</h4>
                                                    <p class="mt-3 text-2xl font-extrabold tabular-nums text-ink-900 dark:text-white">{{ number_format((int) $detailBoqCount, 0, ',', '.') }} <span class="text-sm font-semibold text-ink-500 dark:text-ink-400">item</span></p>
                                                    <p class="mt-1 text-xs text-ink-500 dark:text-ink-400">{{ $lop->boq ? 'BOQ Plan' : ($detailBoqCount > 0 ? 'Reservasi Teknisi' : 'Belum ada BOQ atau reservasi') }}</p>
                                                    @if($detailBoqTotal !== null)<p class="mt-3 border-t border-ink-200 pt-3 text-sm font-bold text-ink-800 dark:border-ink-700 dark:text-ink-100">Total Rp {{ number_format((float) $detailBoqTotal, 0, ',', '.') }}</p>@endif
                                                </section>

                                                <section class="rounded-lg border border-ink-100 p-4 dark:border-ink-800">
                                                    <h4 class="text-sm font-bold text-ink-900 dark:text-white">Dibuat oleh</h4>
                                                    <p class="mt-2 text-sm font-semibold text-ink-800 dark:text-ink-100">{{ $lop->creator?->name ?? 'Tidak diketahui' }}</p>
                                                    <p class="mt-0.5 text-xs text-ink-500 dark:text-ink-400">{{ $lop->created_at?->format('d M Y, H:i') ?? 'Waktu tidak tersedia' }}</p>
                                                    <p class="mt-3 border-t border-ink-100 pt-3 text-[11px] text-ink-500 dark:border-ink-800 dark:text-ink-400">Terakhir diperbarui {{ $lop->updated_at?->format('d M Y, H:i') ?? 'belum tersedia' }}</p>
                                                </section>
                                            </aside>
                                        </div>

                                        @if(filled($lop->ticket_summary))
                                            <section class="mt-6 border-t border-ink-100 pt-5 dark:border-ink-800">
                                                <h4 class="text-sm font-bold text-ink-900 dark:text-white">Ringkasan tiket</h4>
                                                <pre class="mt-2 whitespace-pre-wrap wrap-break-word rounded-lg bg-ink-50 p-4 font-mono text-xs leading-5 text-ink-700 dark:bg-ink-950/50 dark:text-ink-200">{{ $lop->ticket_summary }}</pre>
                                            </section>
                                        @endif
                                    </div>
                                </section>
                            </div>
                            @can('delete', $lop)<x-lop-delete-modal :$lop />@endcan
                            @if ($lop->status_lop !== \App\Enums\LopStatus::COMPLETED) @can('uploadEvidence', $lop)<x-admin-insera-modal :id="'insera-'.$lop->id_qe_lops" :$lop />@endcan @endif
                            @if($lop->program_type->usesProjectStatus()) @can('uploadEvidence', $lop)<x-request-letter-modal :id="'request-letter-'.$lop->id_qe_lops" :$lop />@endcan @endif
                            </td>
                        </tr>
                    @empty<tr><td colspan="7" class="px-5 py-14 text-center text-ink-400">Data LOP tidak ditemukan.</td></tr>@endforelse
                </tbody>
            </table>
        </div>
    </div>
    {{ $lops->links() }}
</div>
@endsection
