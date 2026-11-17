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
    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div><p class="text-xs font-semibold text-brand-700 dark:text-brand-300">Master Data</p><h1 class="mt-1 text-2xl font-semibold tracking-tight text-ink-900 dark:text-white">Data LOP</h1><p class="mt-2 text-sm text-ink-500 dark:text-ink-400">Seluruh LOP hasil input manual dan Bulk Import dalam lingkup akses Anda.</p></div>
        <div class="flex flex-wrap gap-2">
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
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-6">
            <label class="lg:col-span-2"><span class="mb-1.5 block text-xs font-medium text-ink-600 dark:text-ink-300">Cari LOP</span><input name="q" value="{{ $filters['search'] }}" placeholder="Incident, nama LOP, atau STO" class="min-h-10 w-full rounded-md border border-ink-300 bg-white px-3 text-sm outline-none transition focus:border-brand-500 focus:ring-2 focus:ring-brand-500/15 dark:border-ink-700 dark:bg-ink-950"></label>
            <label><span class="mb-1.5 block text-xs font-medium text-ink-600 dark:text-ink-300">Program</span><select name="program" class="min-h-10 w-full rounded-md border border-ink-300 bg-white px-3 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/15 dark:border-ink-700 dark:bg-ink-950"><option value="">Semua program</option>@foreach($programs as $item)<option value="{{ $item->value }}" @selected($filters['program'] === $item->value)>{{ $item->label() }}</option>@endforeach</select></label>
            <label><span class="mb-1.5 block text-xs font-medium text-ink-600 dark:text-ink-300">Status</span><select name="status" class="min-h-10 w-full rounded-md border border-ink-300 bg-white px-3 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/15 dark:border-ink-700 dark:bg-ink-950"><option value="">Semua status</option>@foreach($statuses as $item)<option value="{{ $item->value }}" @selected($filters['status'] === $item->value)>{{ $item->label() }}</option>@endforeach</select></label>
            <label>
                <span class="mb-1.5 block text-xs font-medium text-ink-600 dark:text-ink-300">STO / Service Area</span>
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
            <table class="data-lop-table min-w-[1160px] w-full table-fixed divide-y divide-ink-100 text-sm dark:divide-ink-800">
                <colgroup><col style="width: 17rem"><col style="width: 10rem"><col style="width: 11rem"><col style="width: 10rem"><col style="width: 9rem"><col style="width: 8rem"><col style="width: 10rem"></colgroup>
                <thead class="bg-ink-50 text-center text-xs font-semibold text-ink-600 dark:bg-ink-800 dark:text-ink-300"><tr><th scope="col" class="px-4 py-3 text-center">LOP</th><th scope="col" class="px-4 py-3 text-center">Lokasi</th><th scope="col" class="px-4 py-3 text-center">Program</th><th scope="col" class="px-4 py-3 text-center">Status Dompis</th><th scope="col" class="px-4 py-3 text-center">Status AISS</th><th scope="col" class="px-4 py-3 text-center">BOQ</th><th scope="col" class="px-4 py-3 text-center">Aksi</th></tr></thead>
                <tbody class="divide-y divide-ink-100 dark:divide-ink-800">
                    @forelse($lops as $lop)
                        @php
                            $baDocument = \App\Support\BaBongkarRules::document($lop);
                            $inseraCount = $lop->evidences->filter(fn ($evidence) => $evidence->category === \App\Enums\EvidenceCategory::INSERA)->count();
                            $statusDot = match ($lop->status_lop->badgeVariant()) {
                                'success' => 'bg-emerald-500',
                                'danger' => 'bg-red-500',
                                'warning' => 'bg-amber-500',
                                'info' => 'bg-blue-500',
                                default => 'bg-ink-400',
                            };
                        @endphp
                        <tr x-data="{ detail: false, remove: false, actionsOpen: false, actionsPosition: { top: 8, left: 8 }, toggleActions(button) { if (this.actionsOpen) { this.actionsOpen = false; return; } const rect = button.getBoundingClientRect(); const width = 240; const height = 300; this.actionsPosition = { top: Math.max(8, Math.min(rect.bottom + 6, window.innerHeight - height - 8)), left: Math.max(8, Math.min(rect.right - width, window.innerWidth - width - 8)) }; this.actionsOpen = true; } }" @click.window="if (actionsOpen && !$event.target.closest('[data-lop-action-menu], [data-lop-action-toggle]')) actionsOpen = false" @keydown.escape.window="actionsOpen = false" @scroll.window="actionsOpen = false" @resize.window="actionsOpen = false" class="align-middle transition hover:bg-brand-50/40 dark:hover:bg-ink-800/40">
                            <td class="px-4 py-3.5"><p class="font-semibold text-ink-900 dark:text-white">{{ $lop->incident }}</p><p class="mt-1 truncate text-xs text-ink-600 dark:text-ink-300" title="{{ $lop->nama_lop }}">{{ $lop->nama_lop }}</p><p class="mt-1 text-[11px] tabular-nums text-ink-400">Tanggal laporan {{ $lop->reported_date ?: '—' }}</p></td>
                            <td class="px-4 py-3.5"><p class="font-medium text-ink-800 dark:text-ink-100">{{ $lop->branch ?: 'Belum terpetakan' }}</p><p class="mt-1 text-xs text-ink-500 dark:text-ink-400">STO {{ $lop->sto ?: '—' }}</p></td>
                            <td class="px-4 py-3.5 text-sm text-ink-700 dark:text-ink-200">{{ $lop->program_type->label() }}</td>
                            <td class="px-4 py-3.5 text-left"><span class="inline-flex items-center justify-start gap-2 text-sm font-medium text-ink-700 dark:text-ink-200"><span aria-hidden="true" class="h-2 w-2 shrink-0 rounded-full {{ $statusDot }}"></span>{{ $lop->status_lop->label() }}</span></td>
                            <td class="px-4 py-3.5"><x-aiss-status :status="$lop->status_aiss" /></td>
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
                                        <x-table-action label="Aksi lainnya untuk LOP {{ $lop->incident }}" data-lop-action-toggle aria-controls="lop-actions-{{ $lop->id_qe_lops }}" @click="toggleActions($el)" ::aria-expanded="actionsOpen"><svg aria-hidden="true" class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor"><circle cx="5" cy="12" r="1.6"/><circle cx="12" cy="12" r="1.6"/><circle cx="19" cy="12" r="1.6"/></svg></x-table-action>
                                    </div>
                                    <template x-teleport="body">
                                    <div id="lop-actions-{{ $lop->id_qe_lops }}" x-show="actionsOpen" x-cloak data-lop-action-menu @click="if ($event.target.closest('button')) actionsOpen = false" :style="`top:${actionsPosition.top}px;left:${actionsPosition.left}px`" x-transition:enter="transition ease-out duration-150" x-transition:enter-start="translate-y-1 scale-95 opacity-0" x-transition:enter-end="translate-y-0 scale-100 opacity-100" x-transition:leave="transition ease-in duration-100" x-transition:leave-start="translate-y-0 scale-100 opacity-100" x-transition:leave-end="translate-y-1 scale-95 opacity-0" class="fixed z-[120] max-h-[calc(100vh-1rem)] w-60 origin-top-right overflow-y-auto rounded-md border border-ink-200 bg-white p-1 shadow-xl dark:border-ink-700 dark:bg-ink-900">
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

                            <div x-show="detail" x-cloak @keydown.escape.window="detail=false" class="fixed inset-0 z-[90] flex items-end justify-center bg-ink-950/60 p-4 sm:items-center" @click.self="detail=false"><section role="dialog" aria-modal="true" aria-labelledby="lop-detail-title-{{ $lop->id_qe_lops }}" class="max-h-[90vh] w-full max-w-2xl overflow-y-auto rounded-md border border-ink-200 bg-white p-5 shadow-2xl dark:border-ink-700 dark:bg-ink-900 sm:p-6"><header class="flex items-center justify-between gap-3 border-b border-ink-100 pb-3 dark:border-ink-800"><div class="min-w-0"><p class="text-xs font-semibold text-ink-500 dark:text-ink-400">Detail LOP</p><h3 id="lop-detail-title-{{ $lop->id_qe_lops }}" class="mt-1 truncate text-base font-semibold">{{ $lop->incident }}</h3></div><button type="button" aria-label="Tutup detail LOP" title="Tutup detail LOP" @click="detail=false" class="grid h-9 w-9 shrink-0 place-items-center rounded-md border border-ink-200 text-ink-600 hover:bg-ink-50 dark:border-ink-700 dark:text-ink-300 dark:hover:bg-ink-800">×</button></header><dl class="mt-4 grid gap-x-6 gap-y-4 sm:grid-cols-2">@foreach(['Nama LOP'=>$lop->nama_lop,'STO'=>$lop->sto,'Branch'=>$lop->branch,'Area'=>$lop->area,'Program'=>$lop->program_type->label(),'Segmen'=>$lop->segmentLabel(),'ID IHLD'=>$lop->ihld_id ?: '—','Dibuat oleh'=>$lop->creator?->name ?? '—'] as $label=>$value)<div><dt class="text-xs text-ink-500 dark:text-ink-400">{{ $label }}</dt><dd class="mt-1 text-sm font-medium text-ink-900 dark:text-white">{{ $value ?: '—' }}</dd></div>@endforeach</dl><div class="mt-5 border-t border-ink-100 pt-4 dark:border-ink-800"><h4 class="text-sm font-semibold">Deskripsi Pekerjaan</h4><p class="mt-2 whitespace-pre-line text-sm leading-6 text-ink-700 dark:text-ink-200">{{ $lop->job_description ?: '—' }}</p></div></section></div>
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
