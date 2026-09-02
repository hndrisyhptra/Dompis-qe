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
                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-ink-500 dark:text-ink-400">WBS</th>
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
                        <x-badge variant="neutral">{{ $lop->wbs_type->label() }}</x-badge>
                    </td>
                    <td class="px-4 py-3">
                        <x-badge :variant="$lop->status_lop->badgeVariant()">{{ $lop->status_lop->label() }}</x-badge>
                    </td>
                    <td class="px-4 py-3 text-right">
                        <a href="{{ route('lop.show', $lop) }}" class="text-sm text-brand-600 dark:text-brand-400 hover:text-brand-700 dark:hover:text-brand-300 font-medium">
                            Detail
                        </a>
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
