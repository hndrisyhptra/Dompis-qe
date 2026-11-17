@props(['items', 'lop', 'title', 'description' => null])

@php
    $pending = $items->where('status', \App\Enums\EvidenceStatus::PENDING)->count();
    $approved = $items->where('status', \App\Enums\EvidenceStatus::APPROVED)->count();
    $rejected = $items->where('status', \App\Enums\EvidenceStatus::REJECTED)->count();
    $pendingItems = $items->where('status', \App\Enums\EvidenceStatus::PENDING);
    $bulkModalId = 'bulk-approve-group-'.$items->first()->id_evidence;
    $statusTone = $rejected ? 'bg-brand-700' : ($pending ? 'bg-amber-500' : 'bg-emerald-600');
@endphp

<section x-data="{ open: false }" class="overflow-hidden rounded-lg border border-ink-200 bg-white dark:border-ink-700 dark:bg-ink-900">
    <button type="button" @click="open = !open" :aria-expanded="open" class="flex w-full items-center gap-3 px-4 py-3.5 text-left transition-colors hover:bg-ink-50 dark:hover:bg-ink-800/50">
        <span class="h-2.5 w-2.5 shrink-0 rounded-full {{ $statusTone }}" aria-hidden="true"></span>
        <span class="min-w-0 flex-1"><span class="block truncate text-sm font-semibold text-ink-900 dark:text-white">{{ $title }}</span>@if($description)<span class="mt-1 block truncate text-xs text-ink-500">{{ $description }}</span>@endif<span class="mt-2 flex flex-wrap gap-x-3 gap-y-1 text-[11px] font-medium text-ink-600 dark:text-ink-300">@if($pending)<span>{{ $pending }} perlu review</span>@endif @if($approved)<span>{{ $approved }} disetujui</span>@endif @if($rejected)<span class="text-brand-700 dark:text-brand-300">{{ $rejected }} ditolak</span>@endif</span></span>
        <span class="flex shrink-0 items-center gap-2"><span class="text-xs font-medium tabular-nums text-ink-500">{{ $items->count() }} file</span><svg class="h-4 w-4 text-ink-400 transition-transform" :class="open && 'rotate-180'" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25L12 15.75 4.5 8.25"/></svg></span>
    </button>

    <div x-show="open" x-cloak x-transition.opacity class="border-t border-ink-200 bg-ink-50/60 p-3 dark:border-ink-700 dark:bg-ink-950/30 sm:p-4">
        <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
            <div><p class="text-[10px] font-bold uppercase tracking-wider text-ink-400">Daftar foto evidence</p><p class="mt-0.5 text-[10px] text-ink-400">Klik foto untuk preview</p></div>
            @if ($pending > 0)
                <button type="button" onclick="document.getElementById('{{ $bulkModalId }}').showModal()" class="inline-flex min-h-9 items-center gap-1.5 rounded-lg bg-emerald-600 px-3 text-[11px] font-extrabold text-white shadow-sm transition hover:bg-emerald-700">
                    <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="m5 12.5 4.25 4.25L19 7"/></svg>
                    Approve {{ $pending }} pending
                </button>
            @endif
        </div>
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5">
        @foreach ($items as $evidence)
            <x-approval-evidence-item :evidence="$evidence" />
        @endforeach
        </div>
    </div>
</section>

@if ($pending > 0)
    <x-modal :id="$bulkModalId" title="Approve Evidence Sekaligus">
        <div class="flex items-start gap-3 rounded-xl border border-emerald-200 bg-emerald-50 p-4 dark:border-emerald-900/60 dark:bg-emerald-950/30">
            <span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-emerald-100 text-emerald-600 dark:bg-emerald-900/40 dark:text-emerald-300">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="m5 12.5 4.25 4.25L19 7"/></svg>
            </span>
            <div class="min-w-0"><p class="text-sm font-extrabold text-emerald-900 dark:text-emerald-100">Setujui {{ $pending }} evidence pending?</p><p class="mt-1 text-xs leading-5 text-emerald-800/80 dark:text-emerald-200/80">Semua evidence pending pada kelompok ini akan langsung berstatus approved. Evidence yang sudah direview tidak ikut berubah.</p></div>
        </div>
        <form method="POST" action="{{ route('evidence-approval.lop.approve-group', $lop) }}" class="mt-5 flex gap-2">
            @csrf
            @foreach ($pendingItems as $evidence)
                <input type="hidden" name="evidence_ids[]" value="{{ $evidence->id_evidence }}">
            @endforeach
            <button type="button" onclick="document.getElementById('{{ $bulkModalId }}').close()" class="min-h-11 flex-1 rounded-xl border border-ink-200 text-sm font-bold dark:border-ink-700">Batal</button>
            <button class="min-h-11 flex-1 rounded-xl bg-emerald-600 text-sm font-extrabold text-white shadow-lg shadow-emerald-600/20 hover:bg-emerald-700">Ya, approve semua</button>
        </form>
    </x-modal>
@endif
