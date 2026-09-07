@extends('layouts.app')

@section('title', 'Review Evidence '.$lop->incident)

@section('content')
@php
    $stepEvidence = [
        1 => collect(),
        2 => $lop->evidences->where('category', \App\Enums\EvidenceCategory::MATERIAL_ARRIVAL),
        3 => $lop->evidences->filter(fn ($item) => in_array($item->category, [\App\Enums\EvidenceCategory::PRE, \App\Enums\EvidenceCategory::INSERA, \App\Enums\EvidenceCategory::BEFORE], true)),
        4 => $lop->evidences->where('category', \App\Enums\EvidenceCategory::PROGRESS),
        5 => $lop->evidences->filter(fn ($item) => in_array($item->category, [\App\Enums\EvidenceCategory::AFTER, \App\Enums\EvidenceCategory::SLOT_PORT], true)),
    ];
    $stepLabels = [1 => 'Reservasi & Lokasi', 2 => 'Material Tiba', 3 => 'Evidence Pra', 4 => 'Progress', 5 => 'After'];
    $stepShortLabels = [1 => 'Reservasi', 2 => 'Material', 3 => 'Pra', 4 => 'Progress', 5 => 'After'];
    $currentEvidence = $stepEvidence[$currentStep];
    $currentGroups = $currentEvidence->groupBy(fn ($item) => ($item->category?->value ?? $item->step->value).'-'.($item->designator_id ?? 'global'));
@endphp

<div class="mx-auto max-w-7xl space-y-6">
    <header class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div><a href="{{ route('evidence-approval.index') }}" class="text-xs font-bold text-ink-500 hover:text-brand-600">← Kembali ke daftar approval</a><p class="mt-4 text-xs font-extrabold uppercase tracking-[.16em] text-brand-600">{{ $lop->incident }}</p><h1 class="mt-1 text-2xl font-extrabold text-ink-900 dark:text-white">{{ $lop->nama_lop }}</h1><p class="mt-1 text-sm text-ink-500">{{ $lop->sto ?: 'STO —' }} · {{ $lop->branch ?: 'Branch —' }} · {{ $lop->program_type->label() }}</p></div>
        <div class="min-w-72 rounded-2xl border border-ink-100 bg-white p-4 shadow-sm dark:border-ink-800 dark:bg-ink-900"><div class="flex items-center justify-between text-xs"><span class="font-bold text-ink-500">Progress Approval</span><strong class="text-lg text-ink-900 dark:text-white">{{ $approvalSummary['approval_percentage'] }}%</strong></div><div class="mt-2 h-2 overflow-hidden rounded-full bg-ink-100 dark:bg-ink-800"><div class="h-full rounded-full {{ $approvalSummary['rejected'] ? 'bg-brand-600' : ($approvalSummary['approval_percentage'] === 100 ? 'bg-emerald-500' : 'bg-amber-400') }}" style="width: {{ $approvalSummary['approval_percentage'] }}%"></div></div><div class="mt-3 flex gap-1.5"><x-badge variant="warning">{{ $approvalSummary['pending'] }} pending</x-badge><x-badge variant="success">{{ $approvalSummary['approved'] }} approve</x-badge>@if($approvalSummary['rejected'])<x-badge variant="danger">{{ $approvalSummary['rejected'] }} reject</x-badge>@endif</div></div>
    </header>

    <nav class="rounded-2xl border border-ink-100 bg-white px-2 py-3 shadow-sm dark:border-ink-800 dark:bg-ink-900 sm:p-4">
        <div class="grid grid-cols-5 items-start">
            @foreach ($stepLabels as $number => $label)
                @php
                    $evidenceItems = $stepEvidence[$number];
                    $hasPending = $evidenceItems->contains(fn ($item) => $item->status === \App\Enums\EvidenceStatus::PENDING);
                    $complete = $number === 1 ? ($lop->materialReservation !== null && $lop->survey !== null) : ($evidenceItems->isNotEmpty() && ! $hasPending);
                @endphp
                <a href="{{ route('evidence-approval.lop.review', [$lop, 'step' => $number]) }}" class="relative flex min-w-0 flex-col items-center text-center before:absolute before:left-0 before:right-0 before:top-4 before:h-0.5 sm:before:top-5 {{ $number === 1 ? 'before:left-1/2' : '' }} {{ $number === count($stepLabels) ? 'before:right-1/2' : '' }} before:bg-ink-100 dark:before:bg-ink-700">
                    <span class="relative z-10 grid h-8 w-8 place-items-center rounded-full border-2 text-xs font-extrabold sm:h-10 sm:w-10 sm:text-sm {{ $currentStep === $number ? 'border-brand-600 bg-brand-600 text-white shadow-lg shadow-brand-600/20' : ($complete ? 'border-emerald-500 bg-emerald-500 text-white' : 'border-ink-200 bg-white text-ink-400 dark:border-ink-700 dark:bg-ink-900') }}">{{ $complete && $currentStep !== $number ? '✓' : $number }}</span><span class="mt-2 max-w-full truncate px-1 text-[10px] font-bold sm:hidden {{ $currentStep === $number ? 'text-brand-600 dark:text-brand-400' : 'text-ink-500' }}">{{ $stepShortLabels[$number] }}</span><span class="mt-2 hidden text-xs font-bold sm:block {{ $currentStep === $number ? 'text-brand-600 dark:text-brand-400' : 'text-ink-500' }}">{{ $label }}</span>@if($number > 1)<span class="mt-1 hidden text-[10px] text-ink-400 sm:block">{{ $evidenceItems->count() }} evidence</span>@endif
                </a>
            @endforeach
        </div>
    </nav>

    @if ($currentStep === 1)
        <section class="grid gap-4 lg:grid-cols-2">
            <article class="rounded-2xl border border-ink-100 bg-white p-5 shadow-sm dark:border-ink-800 dark:bg-ink-900"><div class="flex items-center justify-between"><div><p class="text-xs font-bold uppercase tracking-wide text-brand-600">Step 1</p><h2 class="mt-1 text-lg font-extrabold">Reservasi Material</h2></div><x-badge :variant="$lop->materialReservation ? 'success' : 'warning'">{{ $lop->materialReservation ? 'Tersedia' : 'Belum ada' }}</x-badge></div><div class="mt-4 space-y-2">@forelse($lop->materialReservation?->items ?? collect() as $item)
                    @php $sisa = $item->sisa(); @endphp
                    <div class="rounded-xl bg-ink-50 p-3 text-xs dark:bg-ink-800">
                        <div class="flex items-start justify-between gap-3">
                            <div><p class="font-extrabold">{{ $item->designator->code }}</p><p class="mt-1 text-ink-500">{{ $item->designator->item_name }}</p></div>
                            <div class="shrink-0 text-right">
                                <p class="text-ink-400">Reservasi</p><strong>{{ (float) $item->qty }} {{ $item->designator->unit }}</strong>
                            </div>
                        </div>
                        <div class="mt-2 flex flex-wrap gap-1.5">
                            @if ($item->qty_actual !== null)
                                <span class="rounded bg-white px-1.5 py-0.5 font-bold text-ink-700 dark:bg-ink-900 dark:text-ink-200">Terpakai {{ (float) $item->qty_actual }} {{ $item->designator->unit }}</span>
                                @if ($sisa > 0)
                                    <span class="rounded bg-amber-50 px-1.5 py-0.5 font-bold text-amber-700 dark:bg-amber-950/40 dark:text-amber-300">Sisa {{ $sisa }} {{ $item->designator->unit }}</span>
                                @else
                                    <span class="rounded bg-emerald-50 px-1.5 py-0.5 font-bold text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300">Terpakai penuh</span>
                                @endif
                            @else
                                <span class="rounded bg-ink-100 px-1.5 py-0.5 font-bold text-ink-500 dark:bg-ink-700 dark:text-ink-300">Rekap qty belum diisi</span>
                            @endif
                        </div>
                    </div>
                @empty<p class="rounded-xl border border-dashed border-ink-200 p-6 text-center text-xs text-ink-400 dark:border-ink-700">Belum ada material yang direservasi.</p>@endforelse</div></article>
            <article class="rounded-2xl border border-ink-100 bg-white p-5 shadow-sm dark:border-ink-800 dark:bg-ink-900"><div class="flex items-center justify-between"><div><p class="text-xs font-bold uppercase tracking-wide text-brand-600">Lokasi Survey</p><h2 class="mt-1 text-lg font-extrabold">Titik Pekerjaan</h2></div><x-badge :variant="$lop->survey ? 'success' : 'warning'">{{ $lop->survey ? 'Tersimpan' : 'Belum ada' }}</x-badge></div>@if($lop->survey)<div class="mt-4 grid grid-cols-2 gap-3 text-xs"><div class="rounded-xl bg-ink-50 p-3 dark:bg-ink-800"><p class="text-ink-400">Latitude</p><p class="mt-1 font-extrabold">{{ $lop->survey->latitude }}</p></div><div class="rounded-xl bg-ink-50 p-3 dark:bg-ink-800"><p class="text-ink-400">Longitude</p><p class="mt-1 font-extrabold">{{ $lop->survey->longitude }}</p></div></div><a href="https://maps.google.com/?q={{ $lop->survey->latitude }},{{ $lop->survey->longitude }}" target="_blank" class="mt-3 grid min-h-10 place-items-center rounded-xl border border-ink-200 text-xs font-bold dark:border-ink-700">Buka lokasi di peta</a>@else<p class="mt-4 rounded-xl border border-dashed border-ink-200 p-6 text-center text-xs text-ink-400 dark:border-ink-700">Teknisi belum menyimpan titik survey.</p>@endif</article>
        </section>
    @else
        <section>
            <div class="mb-4 flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between"><div><p class="text-xs font-bold uppercase tracking-[.14em] text-brand-600">Step {{ $currentStep }}</p><h2 class="mt-1 text-xl font-extrabold">{{ $stepLabels[$currentStep] }}</h2><p class="mt-1 text-xs text-ink-500">Buka kelompok evidence untuk melihat seluruh foto, lalu approve, reject, atau reset review per foto.</p></div><p class="text-xs font-bold text-ink-500">{{ $currentGroups->count() }} kelompok · {{ $currentEvidence->count() }} file</p></div>
            <div class="space-y-3">
                @forelse($currentGroups as $items)
                    @php
                        $first = $items->first();
                        $groupTitle = $first->category?->label() ?? $first->step->label();
                        if (in_array($first->category, [\App\Enums\EvidenceCategory::BEFORE, \App\Enums\EvidenceCategory::PROGRESS, \App\Enums\EvidenceCategory::AFTER], true)) $groupTitle = 'Evidence '.$groupTitle;
                        if ($first->designator) $groupTitle .= ' · '.$first->designator->code;
                        $groupDescription = $first->designator?->item_name ?? 'Evidence global untuk step ini';
                    @endphp
                    <x-approval-evidence-group :items="$items" :title="$groupTitle" :description="$groupDescription" />
                @empty
                    <div class="rounded-2xl border border-dashed border-ink-200 bg-white px-6 py-12 text-center dark:border-ink-700 dark:bg-ink-900"><p class="text-sm font-bold">Belum ada evidence pada step ini</p><p class="mt-1 text-xs text-ink-400">Evidence akan muncul setelah teknisi menyelesaikan upload.</p></div>
                @endforelse
            </div>
        </section>
    @endif

    <div class="flex justify-between gap-3 border-t border-ink-100 pt-5 dark:border-ink-800">@if($currentStep > 1)<a href="{{ route('evidence-approval.lop.review', [$lop, 'step' => $currentStep - 1]) }}" class="inline-flex min-h-11 items-center rounded-xl border border-ink-200 px-4 text-sm font-bold dark:border-ink-700">← Step sebelumnya</a>@else<span></span>@endif @if($currentStep < 4)<a href="{{ route('evidence-approval.lop.review', [$lop, 'step' => $currentStep + 1]) }}" class="inline-flex min-h-11 items-center rounded-xl bg-brand-600 px-4 text-sm font-bold text-white">Step berikutnya →</a>@else<a href="{{ route('evidence-approval.index') }}" class="inline-flex min-h-11 items-center rounded-xl bg-ink-900 px-4 text-sm font-bold text-white dark:bg-ink-700">Selesai review</a>@endif</div>
</div>
@endsection
