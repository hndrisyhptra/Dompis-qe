@extends('layouts.app')

@section('title', 'Approval Evidence')

@section('content')
<div class="max-w-5xl mx-auto">
    <div class="mb-6">
        <h1 class="text-xl font-bold text-ink-900 dark:text-ink-50">Approval Evidence</h1>
        <p class="text-sm text-ink-500 dark:text-ink-400 mt-1">Evidence yang menunggu review.</p>
    </div>

    <form method="GET" action="{{ route('evidence-approval.index') }}" class="flex gap-3 mb-4">
        <select name="step" class="rounded-lg border border-ink-100 dark:border-ink-700 bg-white dark:bg-ink-800 px-3.5 py-2.5 text-sm text-ink-900 dark:text-ink-50 shadow-sm focus:outline-none focus:ring-2 focus:ring-brand-500/30 focus:border-brand-500 transition">
            <option value="">Semua Step</option>
            @foreach (\App\Enums\EvidenceStep::cases() as $s)
                <option value="{{ $s->value }}" @selected($step === $s->value)>{{ $s->label() }}</option>
            @endforeach
        </select>
        <button type="submit" class="rounded-lg border border-ink-100 dark:border-ink-700 bg-white dark:bg-ink-800 px-4 py-2.5 text-sm font-medium text-ink-700 dark:text-ink-200 hover:bg-ink-50 dark:hover:bg-ink-700 transition">
            Filter
        </button>
    </form>

    <x-table>
        <thead class="bg-ink-50 dark:bg-ink-800">
            <tr>
                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-ink-500 dark:text-ink-400">LOP</th>
                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-ink-500 dark:text-ink-400">Step / Tipe</th>
                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-ink-500 dark:text-ink-400">Teknisi</th>
                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-ink-500 dark:text-ink-400">Tanggal</th>
                <th class="px-4 py-3"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-ink-100 dark:divide-ink-700">
            @forelse ($evidences as $evidence)
                <tr>
                    <td class="px-4 py-3 text-ink-900 dark:text-ink-50 font-medium">{{ $evidence->lop->kode_lop }}</td>
                    <td class="px-4 py-3 text-ink-600 dark:text-ink-300">
                        {{ $evidence->step->label() }} · {{ $evidence->type->label() }}
                        @if ($evidence->designator)
                            <span class="text-ink-400 dark:text-ink-500">({{ $evidence->designator->code }})</span>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-ink-600 dark:text-ink-300">{{ $evidence->uploader?->name }}</td>
                    <td class="px-4 py-3 text-ink-600 dark:text-ink-300">{{ $evidence->created_at->format('d M Y H:i') }}</td>
                    <td class="px-4 py-3 text-right">
                        <a href="{{ route('evidence-approval.show', $evidence) }}" class="text-sm text-brand-600 dark:text-brand-400 hover:text-brand-700 dark:hover:text-brand-300 font-medium">
                            Review
                        </a>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="px-4 py-8 text-center text-ink-400 dark:text-ink-500 text-sm">Tidak ada evidence menunggu review.</td>
                </tr>
            @endforelse
        </tbody>
    </x-table>

    <div class="mt-4">
        {{ $evidences->links() }}
    </div>
</div>
@endsection
