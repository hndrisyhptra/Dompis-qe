@props(['items', 'lop', 'title', 'description' => null])

@php
    $pending = $items->where('status', \App\Enums\EvidenceStatus::PENDING)->count();
    $approved = $items->where('status', \App\Enums\EvidenceStatus::APPROVED)->count();
    $rejected = $items->where('status', \App\Enums\EvidenceStatus::REJECTED)->count();
    $pendingItems = $items->where('status', \App\Enums\EvidenceStatus::PENDING);
    $bulkModalId = 'bulk-approve-group-'.$items->first()->id_evidence;
    $border = $rejected ? 'border-brand-200 dark:border-brand-800' : ($pending ? 'border-amber-200 dark:border-amber-800' : 'border-emerald-200 dark:border-emerald-800');
@endphp

<section x-data="{ open: false }" class="overflow-hidden rounded-2xl border {{ $border }} bg-white shadow-sm dark:bg-ink-900">
    <button type="button" @click="open = !open" :aria-expanded="open" class="flex w-full items-center gap-3 p-4 text-left transition hover:bg-ink-50 dark:hover:bg-ink-800/50">
        <span class="grid h-11 w-11 shrink-0 place-items-center rounded-xl {{ $rejected ? 'bg-brand-50 text-brand-600 dark:bg-brand-900/20 dark:text-brand-300' : ($pending ? 'bg-amber-50 text-amber-600 dark:bg-amber-900/20 dark:text-amber-300' : 'bg-emerald-50 text-emerald-600 dark:bg-emerald-900/20 dark:text-emerald-300') }}">
            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 015.186 7.23c-.38.054-.757.112-1.134.175C2.88 7.6 2 8.61 2 9.8V18a2.25 2.25 0 002.25 2.25h15.5A2.25 2.25 0 0022 18V9.8c0-1.19-.88-2.2-2.052-2.395a48.776 48.776 0 00-1.134-.175 2.31 2.31 0 01-1.64-1.055l-.822-1.316a2.192 2.192 0 00-1.736-1.039 48.774 48.774 0 00-5.232 0 2.192 2.192 0 00-1.736 1.039l-.821 1.316z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 13.5a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
        </span>
        <span class="min-w-0 flex-1"><span class="block text-sm font-extrabold text-ink-900 dark:text-white">{{ $title }}</span>@if($description)<span class="mt-1 block truncate text-xs text-ink-500">{{ $description }}</span>@endif<span class="mt-2 flex flex-wrap gap-1.5">@if($pending)<x-badge variant="warning">{{ $pending }} pending</x-badge>@endif @if($approved)<x-badge variant="success">{{ $approved }} approve</x-badge>@endif @if($rejected)<x-badge variant="danger">{{ $rejected }} reject</x-badge>@endif</span></span>
        <span class="flex shrink-0 items-center gap-2"><span class="rounded-full bg-ink-100 px-2.5 py-1 text-[10px] font-bold text-ink-600 dark:bg-ink-800 dark:text-ink-300">{{ $items->count() }} file</span><svg class="h-5 w-5 text-ink-400 transition-transform" :class="open && 'rotate-180'" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25L12 15.75 4.5 8.25"/></svg></span>
    </button>

    <div x-show="open" x-cloak x-transition.opacity class="border-t border-ink-100 bg-ink-50/50 p-3 dark:border-ink-800 dark:bg-ink-950/30 sm:p-4">
        <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
            <div><p class="text-[10px] font-bold uppercase tracking-wider text-ink-400">Daftar foto evidence</p><p class="mt-0.5 text-[10px] text-ink-400">Klik foto untuk preview</p></div>
            @if ($pending > 0)
                <button type="button" onclick="document.getElementById('{{ $bulkModalId }}').showModal()" class="inline-flex min-h-9 items-center gap-1.5 rounded-lg bg-emerald-600 px-3 text-[11px] font-extrabold text-white shadow-sm transition hover:bg-emerald-700">
                    <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="m5 12.5 4.25 4.25L19 7"/></svg>
                    Approve {{ $pending }} pending
                </button>
            @endif
        </div>
        <div class="grid grid-cols-3 gap-2 sm:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6">
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
