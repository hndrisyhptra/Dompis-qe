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
                        <div class="flex items-center justify-end gap-1.5">
                            @if ($lop->status_lop === \App\Enums\LopStatus::COMPLETED)
                                <x-table-action label="Download evidence (.zip)" tone="success" :href="route('lop.evidence-archive', $lop)">
                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
                                </x-table-action>
                            @endif
                            <x-table-action label="Detail LOP" onclick="document.getElementById('lop-detail-{{ $lop->id_qe_lops }}').showModal()">
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12s3.75-6.75 9.75-6.75S21.75 12 21.75 12 18 18.75 12 18.75 2.25 12 2.25 12Z" /><circle cx="12" cy="12" r="2.25" /></svg>
                            </x-table-action>
                            @can('update', $lop)
                                <x-table-action label="Edit LOP" tone="primary" :href="route('lop.edit', $lop)">
                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 16.5V12a2.25 2.25 0 0 0-2.25-2.25h-6A2.25 2.25 0 0 0 6 12v4.5A2.25 2.25 0 0 0 8.25 18.75h6A2.25 2.25 0 0 0 16.5 16.5Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M12 9V7.5A1.5 1.5 0 0 0 10.5 6h-3A1.5 1.5 0 0 0 6 7.5V9"/><path stroke-linecap="round" stroke-linejoin="round" d="m12 12.75 1.5 1.5 3-3"/></svg>
                                </x-table-action>
                            @endcan
                            <x-table-action label="Tracking Riwayat" tone="info" onclick="document.getElementById('lop-tracking-{{ $lop->id_qe_lops }}').showModal()">
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6l4 2m5-2a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
                            </x-table-action>
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

    @foreach ($lops as $lop)
        <x-lop-detail-modal :id="'lop-detail-'.$lop->id_qe_lops" :$lop />
        <x-lop-tracking-modal :id="'lop-tracking-'.$lop->id_qe_lops" :$lop />
    @endforeach

    <div class="mt-4">
        {{ $lops->links() }}
    </div>
</div>
@endsection
