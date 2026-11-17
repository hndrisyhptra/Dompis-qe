@props(['value', 'title', 'filters' => [], 'suffix' => '', 'muteZero' => false])
@php($isZero = $muteZero && (float) $value === 0.0)
@if ($isZero)
    <span title="Tidak ada LOP — {{ $title }}" {{ $attributes->class('font-extrabold tabular-nums text-slate-400 dark:text-slate-500') }}>{{ $value }}{{ $suffix }}</span>
@else
    <button type="button" @click="open(@js($title), @js($filters))" title="Buka daftar LOP — {{ $title }}" {{ $attributes->class([
        'font-extrabold tabular-nums transition hover:text-brand-600 hover:underline focus:outline-none focus-visible:rounded focus-visible:ring-2 focus-visible:ring-brand-500/40 dark:hover:text-brand-400',
        'text-slate-900 dark:text-white' => $muteZero,
    ]) }}>{{ $value }}{{ $suffix }}</button>
@endif
