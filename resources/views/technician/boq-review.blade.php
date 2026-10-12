@extends('layouts.technician')

@section('title', 'Review BOQ — '.$lop->incident)
@section('header', 'Review BOQ')

@section('content')
@php
    $showPlan = $lop->program_type->usesProjectStatus();
    $planByCode = collect($boqPlan['lines'] ?? [])->keyBy(fn ($line) => mb_strtoupper(trim($line['designator_code'])));
    $money = fn ($value) => $value === null ? '—' : 'Rp '.number_format((float) $value, 0, ',', '.');
    $hasActualValue = collect($boqActual['lines'])->contains(fn ($line) => $line['total_actual'] !== null);
    $partialActual = $boqActual['grand']['price_missing_count'] > 0 || $boqActual['grand']['not_recapped_count'] > 0;
@endphp
<div>
    <a id="technician-boq-review-back" href="{{ route('technician.projects.show', [$lop, 'step' => $returnStep]) }}"
       class="inline-flex min-h-11 items-center gap-2 rounded-xl bg-ink-100 px-3 text-xs font-bold text-ink-600 transition hover:bg-ink-200 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-600 dark:bg-ink-800 dark:text-ink-300 dark:hover:bg-ink-700">
        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
        Kembali ke pekerjaan · Step {{ $returnStep }}
    </a>
</div>

<section class="mt-4 rounded-3xl bg-ink-900 p-4 text-white shadow-xl shadow-ink-900/10">
    <p class="text-[10px] font-bold uppercase tracking-[.12em] text-brand-300">{{ $lop->incident }}</p>
    <h1 class="mt-1 text-sm font-extrabold leading-5">{{ $showPlan ? 'Review BOQ Plan vs BOQ Actual' : 'Review BOQ Actual' }}</h1>
    <p class="mt-2 wrap-break-word text-xs font-semibold leading-5 text-ink-300">{{ $lop->nama_lop }}</p>
    <div class="mt-3 flex flex-wrap gap-x-4 gap-y-2 rounded-xl bg-white/8 px-3 py-2 text-xs text-ink-300"><span>Branch: <span class="font-bold text-white">{{ $lop->locationBranchName() }}</span></span><span>STO: <span class="font-bold text-white">{{ $lop->locationServiceAreaName() }}</span></span></div>
    <div class="mt-3 border-t border-white/10 pt-3 text-[10px] font-semibold text-ink-300">{{ count($boqActual['lines']) }} item material / jasa · {{ $boqActual['package_label'] }}</div>
</section>

<section aria-label="Tabel review BOQ" class="mt-5 space-y-4">
        @if ($showPlan && ! $boqPlan['has_plan'])
            <p class="rounded-2xl border border-amber-100 bg-amber-50 p-4 text-xs leading-5 text-amber-800 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-300">BOQ Plan belum diimport; tanda — bukan berarti nilai nol.</p>
        @endif
        @if ($boqActual['grand']['not_recapped_count'] > 0)
            <p class="rounded-2xl border border-amber-100 bg-amber-50 p-4 text-xs leading-5 text-amber-800 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-300">{{ $boqActual['grand']['not_recapped_count'] }} item belum direkap. Nilai Actual belum lengkap; tanda — bukan berarti qty nol.</p>
        @endif
        @if ($boqActual['grand']['price_missing_count'] > 0)
            <p class="rounded-2xl border border-amber-100 bg-amber-50 p-4 text-xs leading-5 text-amber-800 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-300">{{ $boqActual['grand']['price_missing_count'] }} designator belum memiliki harga. Nilai yang tersedia belum mencakup seluruh item.</p>
        @endif
        <div class="overflow-hidden rounded-2xl border border-ink-100 bg-white shadow-sm dark:border-ink-800 dark:bg-ink-900">
            <table id="technician-boq-review-table" class="w-full table-fixed text-left text-[11px]">
                <thead class="bg-ink-50 text-[10px] font-bold text-ink-500 dark:bg-ink-800">
                    <tr><th scope="col" class="{{ $showPlan ? 'w-[40%]' : 'w-[55%]' }} px-3 py-3">Item Designator</th>@if ($showPlan)<th scope="col" class="px-2 py-3 text-right">BOQ Plan</th>@endif<th scope="col" class="px-2 py-3 text-right">BOQ Actual</th></tr>
                </thead>
                <tbody class="divide-y divide-ink-100 dark:divide-ink-800">
                    @forelse ($boqActual['lines'] as $line)
                        @php($planLine = $planByCode->get(mb_strtoupper(trim($line['designator_code']))))
                        <tr>
                            <td class="px-3 py-3 align-top">
                                <p class="break-all font-extrabold leading-4">{{ $line['designator_code'] }}</p>
                                <p class="mt-1 break-words text-[9px] text-ink-500 dark:text-ink-400">{{ $line['type'] }} · {{ $line['unit'] }}</p>
                                <details class="mt-1.5 text-[10px] leading-4 text-ink-500 dark:text-ink-400">
                                    <summary class="cursor-pointer font-semibold text-brand-600 dark:text-brand-400">Lihat uraian</summary>
                                    <p class="mt-1 break-words">{{ $line['designator_name'] }}</p>
                                </details>
                            </td>
                            @if ($showPlan)
                                <td class="px-2 py-3 text-right align-top">
                                    <p class="text-[9px] text-ink-400">Qty Plan</p><p class="break-all text-xs font-bold tabular-nums">{{ $planLine ? number_format($planLine['qty'], 0, ',', '.') : '—' }}</p>
                                    <p class="mt-2 text-[9px] text-ink-400">Nilai Plan</p><p class="break-words font-semibold tabular-nums">{{ $money($planLine['total_plan'] ?? null) }}</p>
                                    @unless ($planLine)<p class="mt-1 text-[9px] text-ink-400">Tidak ada di Plan</p>@endunless
                                </td>
                            @endif
                            <td class="px-2 py-3 text-right align-top">
                                <p class="text-[9px] text-ink-400">Qty Actual</p><p class="break-all text-xs font-bold tabular-nums">{{ $line['qty_actual'] === null ? '—' : number_format($line['qty_actual'], 0, ',', '.') }}</p>
                                <p class="mt-2 text-[9px] text-ink-400">Nilai Actual</p><p class="break-words font-bold tabular-nums text-emerald-700 dark:text-emerald-300">{{ $money($line['total_actual']) }}</p>
                                @if ($line['qty_actual'] === null)<p class="mt-1 text-[9px] text-amber-700 dark:text-amber-300">Belum direkap</p>
                                @elseif ($line['price_missing'])<p class="mt-1 text-[9px] text-amber-700 dark:text-amber-300">Harga belum tersedia</p>@endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="{{ $showPlan ? 3 : 2 }}" class="p-6 text-center text-ink-500">Belum ada item BOQ atau reservasi material.</td></tr>
                    @endforelse
                </tbody>
                <tfoot class="border-t border-ink-200 bg-ink-50 font-extrabold dark:border-ink-700 dark:bg-ink-800">
                    <tr><th scope="row" class="px-3 py-3">{{ $partialActual ? 'Total tersedia' : 'Total' }}</th>@if ($showPlan)<td class="break-words px-2 py-3 text-right tabular-nums">{{ $boqPlan['has_plan'] ? $money($boqPlan['grand']['total_plan']) : '—' }}</td>@endif<td data-testid="boq-review-total-actual" class="break-words px-2 py-3 text-right tabular-nums">{{ $hasActualValue ? $money($boqActual['grand']['total_actual']) : '—' }}</td></tr>
                </tfoot>
            </table>
        </div>
        <p class="px-1 text-[10px] leading-5 text-ink-500 dark:text-ink-400">{{ $partialActual ? 'Total tersedia hanya mencakup item yang sudah direkap dan memiliki harga. ' : '' }}Tanda — berarti data belum tersedia, bukan 0. Ketuk Lihat uraian untuk detail item.</p>
</section>
@endsection
