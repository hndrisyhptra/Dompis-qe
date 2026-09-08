@extends('layouts.app')

@section('title', 'History LOP')

@section('content')
<div class="max-w-6xl mx-auto">
    <div class="mb-6">
        <a href="{{ route('lop.index') }}" class="text-sm font-medium text-ink-600 dark:text-ink-300 hover:text-ink-900 dark:hover:text-ink-50">
            &larr; Kembali ke Active LOP
        </a>
        <h1 class="text-xl font-bold text-ink-900 dark:text-ink-50 mt-2">Inbox — History</h1>
        <p class="text-sm text-ink-500 dark:text-ink-400 mt-1">LOP yang sudah selesai.</p>
    </div>

    <x-table>
        <thead class="bg-ink-50 dark:bg-ink-800">
            <tr>
                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-ink-500 dark:text-ink-400">Incident</th>
                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-ink-500 dark:text-ink-400">Nama LOP</th>
                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-ink-500 dark:text-ink-400">Program</th>
                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-ink-500 dark:text-ink-400">Status</th>
                <th class="px-4 py-3"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-ink-100 dark:divide-ink-700">
            @forelse ($lops as $lop)
                <tr>
                    <td class="px-4 py-3 text-ink-900 dark:text-ink-50 font-medium">{{ $lop->incident }}</td>
                    <td class="px-4 py-3 text-ink-600 dark:text-ink-300">{{ $lop->nama_lop }}</td>
                    <td class="px-4 py-3">
                        <x-badge variant="neutral">{{ $lop->program_type->label() }}</x-badge>
                    </td>
                    <td class="px-4 py-3">
                        <x-badge :variant="$lop->status_lop->badgeVariant()">{{ $lop->status_lop->label() }}</x-badge>
                    </td>
                    <td class="px-4 py-3 text-right">
                        <div class="flex items-center justify-end gap-3">
                            @if ($lop->status_lop === \App\Enums\LopStatus::COMPLETED)
                                <a href="{{ route('lop.evidence-archive', $lop) }}" class="inline-flex items-center gap-1.5 rounded-lg bg-brand-600 px-3 py-1.5 text-xs font-bold text-white transition hover:bg-brand-700">
                                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
                                    Download Evidence
                                </a>
                            @endif
                            <a href="{{ route('lop.show', $lop) }}" class="text-sm font-medium text-brand-600 hover:text-brand-700 dark:text-brand-400 dark:hover:text-brand-300">Detail</a>
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="px-4 py-8 text-center text-ink-400 dark:text-ink-500 text-sm">Belum ada riwayat LOP.</td>
                </tr>
            @endforelse
        </tbody>
    </x-table>

    <div class="mt-4">
        {{ $lops->links() }}
    </div>
</div>
@endsection
