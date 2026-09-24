@extends('layouts.app')

@section('title', 'Data LOP')

@section('content')
<div class="mx-auto max-w-7xl space-y-5">
    @if ($errors->any())
        <div class="rounded-2xl border border-red-200 bg-red-50 p-4 text-sm font-semibold text-red-700 dark:border-red-900 dark:bg-red-950/30 dark:text-red-300">{{ $errors->first() }}</div>
    @endif
    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div><p class="text-xs font-bold uppercase tracking-[.16em] text-brand-600">Master Data</p><h1 class="mt-1 text-2xl font-extrabold text-ink-900 dark:text-white">Data LOP</h1><p class="mt-2 text-sm text-ink-500">Seluruh LOP hasil input manual dan Bulk Import dalam lingkup akses Anda.</p></div>
        <a href="{{ route('lop.create') }}" class="inline-flex min-h-11 items-center justify-center rounded-xl bg-brand-600 px-4 text-sm font-extrabold text-white shadow-lg shadow-brand-600/20">Input LOP Baru</a>
    </div>

    <form method="GET" class="rounded-2xl border border-ink-200 bg-white p-4 shadow-sm dark:border-ink-700 dark:bg-ink-900">
        <div class="mb-4 flex items-center justify-between gap-3 border-b border-ink-100 pb-3 dark:border-ink-800">
            <div><p class="text-sm font-extrabold text-ink-900 dark:text-white">Pencarian & Filter</p><p class="mt-0.5 text-xs text-ink-500">Persempit daftar berdasarkan data operasional LOP.</p></div>
            @if (collect($filters)->filter()->isNotEmpty())
                <a href="{{ route('data-lops.index') }}" class="shrink-0 text-xs font-bold text-brand-600 hover:text-brand-700">Reset filter</a>
            @endif
        </div>
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-6">
            <label class="lg:col-span-2"><span class="mb-1.5 block text-[10px] font-extrabold uppercase tracking-wider text-ink-500">Cari LOP</span><input name="q" value="{{ $filters['search'] }}" placeholder="Incident, nama LOP, atau STO" class="min-h-11 w-full rounded-xl border border-ink-200 bg-white px-3 text-sm shadow-sm outline-none transition focus:border-brand-500 focus:ring-2 focus:ring-brand-500/15 dark:border-ink-700 dark:bg-ink-950"></label>
            <label><span class="mb-1.5 block text-[10px] font-extrabold uppercase tracking-wider text-ink-500">Program</span><select name="program" class="min-h-11 w-full rounded-xl border border-ink-200 bg-white px-3 text-sm shadow-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/15 dark:border-ink-700 dark:bg-ink-950"><option value="">Semua program</option>@foreach($programs as $item)<option value="{{ $item->value }}" @selected($filters['program'] === $item->value)>{{ $item->label() }}</option>@endforeach</select></label>
            <label><span class="mb-1.5 block text-[10px] font-extrabold uppercase tracking-wider text-ink-500">Status</span><select name="status" class="min-h-11 w-full rounded-xl border border-ink-200 bg-white px-3 text-sm shadow-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/15 dark:border-ink-700 dark:bg-ink-950"><option value="">Semua status</option>@foreach($statuses as $item)<option value="{{ $item->value }}" @selected($filters['status'] === $item->value)>{{ $item->label() }}</option>@endforeach</select></label>
            <label><span class="mb-1.5 block text-[10px] font-extrabold uppercase tracking-wider text-ink-500">Branch</span><select name="branch" class="min-h-11 w-full rounded-xl border border-ink-200 bg-white px-3 text-sm shadow-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/15 dark:border-ink-700 dark:bg-ink-950"><option value="">Semua branch</option>@foreach($branches as $item)<option value="{{ $item->name }}" @selected($filters['branch'] === $item->name)>{{ $item->name }}</option>@endforeach</select></label>
            <div class="flex items-end"><button class="min-h-11 w-full rounded-xl bg-ink-900 px-4 text-sm font-bold text-white shadow-sm transition hover:bg-ink-800 dark:bg-white dark:text-ink-900 dark:hover:bg-ink-100">Terapkan Filter</button></div>
        </div>
    </form>

    <div class="overflow-hidden rounded-2xl border border-ink-100 bg-white shadow-sm dark:border-ink-800 dark:bg-ink-900">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-ink-100 text-sm dark:divide-ink-800">
                <thead class="bg-ink-50 text-left text-[11px] uppercase tracking-wider text-ink-500 dark:bg-ink-950/50"><tr><th class="px-5 py-3">LOP</th><th class="px-5 py-3">Lokasi</th><th class="px-5 py-3">Program</th><th class="px-5 py-3">Status</th><th class="px-5 py-3">BOQ</th><th class="px-5 py-3 text-right">Aksi</th></tr></thead>
                <tbody class="divide-y divide-ink-100 dark:divide-ink-800">
                    @forelse($lops as $lop)
                        <tr x-data="{ detail: false, remove: false }" class="transition hover:bg-ink-50/70 dark:hover:bg-ink-800/40">
                            <td class="px-5 py-4"><p class="font-bold text-ink-900 dark:text-white">{{ $lop->incident }}</p><p class="mt-1 max-w-sm truncate text-xs text-ink-500">{{ $lop->nama_lop }}</p></td>
                            <td class="px-5 py-4"><p class="font-semibold">{{ $lop->sto ?: '—' }}</p><p class="text-xs text-ink-400">{{ $lop->branch ?: '—' }}</p></td>
                            <td class="px-5 py-4 text-xs">{{ $lop->program_type->label() }}</td>
                            <td class="px-5 py-4"><x-badge :variant="$lop->status_lop->badgeVariant()">{{ $lop->status_lop->label() }}</x-badge></td>
                            <td class="px-5 py-4">@if($lop->boq)<span class="text-xs font-bold text-emerald-600">{{ $lop->boq->item_count }} item</span>@else<span class="text-xs text-ink-400">Belum ada</span>@endif</td>
                            <td class="px-5 py-4"><div class="flex justify-end gap-1.5">
                                <x-table-action label="Lihat detail LOP" @click="detail = true"><svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M2.25 12s3.75-6.75 9.75-6.75S21.75 12 21.75 12 18 18.75 12 18.75 2.25 12 2.25 12Z"/><circle cx="12" cy="12" r="2.25"/></svg></x-table-action>
                                @can('update', $lop)<x-table-action label="Edit data LOP" tone="primary" :href="route('lop.edit', $lop)"><svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="m16.862 4.487 1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931Z"/><path d="M19.5 7.125V18A2.25 2.25 0 0117.25 20.25H6.75A2.25 2.25 0 014.5 18V7.5A2.25 2.25 0 016.75 5.25H12"/></svg></x-table-action>@endcan
                                @can('delete', $lop)<x-table-action label="Hapus data LOP" tone="danger" @click="remove = true"><svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166M19.228 5.79 18.16 19.673A2.25 2.25 0 0115.916 21H8.084a2.25 2.25 0 01-2.244-2.327L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916"/></svg></x-table-action>@endcan
                            </div>

                            <div x-show="detail" x-cloak @keydown.escape.window="detail=false" class="fixed inset-0 z-[90] flex items-end justify-center bg-ink-950/60 p-4 sm:items-center" @click.self="detail=false"><div class="max-h-[90vh] w-full max-w-2xl overflow-y-auto rounded-3xl border border-ink-200 bg-white p-6 shadow-2xl dark:border-ink-700 dark:bg-ink-900"><div class="flex items-start justify-between"><div><p class="text-xs font-bold uppercase text-brand-600">Detail LOP</p><h3 class="mt-1 text-xl font-extrabold">{{ $lop->incident }}</h3></div><button type="button" aria-label="Tutup detail LOP" title="Tutup detail LOP" @click="detail=false" class="grid h-9 w-9 place-items-center rounded-full border border-ink-200 bg-ink-50 dark:border-ink-700 dark:bg-ink-800">×</button></div><dl class="mt-6 grid gap-3 sm:grid-cols-2">@foreach(['Nama LOP'=>$lop->nama_lop,'STO'=>$lop->sto,'Branch'=>$lop->branch,'Area'=>$lop->area,'Program'=>$lop->program_type->label(),'Segmen'=>$lop->segmentLabel(),'ID IHLD'=>$lop->ihld_id ?: '—','Dibuat oleh'=>$lop->creator?->name ?? '—'] as $label=>$value)<div class="rounded-xl border border-ink-100 bg-ink-50 p-3 dark:border-ink-700 dark:bg-ink-800"><dt class="text-[10px] font-bold uppercase text-ink-400">{{ $label }}</dt><dd class="mt-1 text-sm font-semibold">{{ $value }}</dd></div>@endforeach</dl><div class="mt-4 rounded-xl border border-ink-100 bg-ink-50 p-4 dark:border-ink-700 dark:bg-ink-800"><p class="text-[10px] font-bold uppercase text-ink-400">Deskripsi Pekerjaan</p><p class="mt-2 text-sm leading-6">{{ $lop->job_description ?: '—' }}</p></div></div></div>
                            @can('delete', $lop)<x-confirm-modal state="remove" title="Hapus data LOP?" message="LOP draft ini akan dihapus. Tindakan tercatat pada riwayat." :action="route('data-lops.destroy', $lop)" method="DELETE" confirm-label="Hapus LOP" tone="danger" />@endcan
                            </td>
                        </tr>
                    @empty<tr><td colspan="6" class="px-5 py-14 text-center text-ink-400">Data LOP tidak ditemukan.</td></tr>@endforelse
                </tbody>
            </table>
        </div>
    </div>
    {{ $lops->links() }}
</div>
@endsection
